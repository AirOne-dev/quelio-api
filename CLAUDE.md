# Quel io - API

PHP 8 REST API for time tracking with Kelio integration.

## Tech Stack

- **Language**: PHP 8.1+ (required for PHPUnit 10.5)
- **Architecture**: MVC with Dependency Injection
- **HTTP Client**: cURL for Kelio integration
- **Data Storage**: JSON file-based (no database)
- **Required Extensions**: cURL, DOM, OpenSSL

## Project Structure

```
api/
├── index.php              # Single entry point
├── config.php             # Configuration (encrypted credentials)
├── data.json              # Local cache (user data + tokens)
└── src/
    ├── core/             # Framework (Autoloader, Container, Router)
    ├── http/             # HTTP layer (JsonResponse, AuthContext)
    ├── controllers/      # API endpoints
    ├── middleware/       # Authentication & rate limiting
    └── services/         # Business logic
```

## Common Commands

- Start dev server: `php -S localhost:8080` (or use Docker)
- Run tests: `./run-tests.sh` (uses Docker, no local PHP needed)
- Run specific test: `./run-tests.sh --filter TimeCalculatorTest`
- Generate coverage: `./run-tests.sh --coverage`
- Test login: `curl -X POST http://localhost:8080/ -d "username=user&password=pass"`
- Access raw data: `GET /data.json` (requires admin credentials)

## API Endpoints

| Method | Route | Auth | Purpose | Response Format |
|--------|-------|------|---------|-----------------|
| GET | `/` | None | Display login form (if enabled) | HTML or JSON error |
| POST | `/` | Token or Credentials | Login & fetch hours | User data (full structure) |
| POST | `/?action=update_preferences` | Token | Update user preferences | User data (full structure) |
| GET | `/icon.svg` | None | Dynamic PWA icon | SVG |
| GET | `/manifest.json` | None | PWA manifest | JSON manifest |
| GET/POST | `/data.json` | Admin | Raw data access | All user data |

### API Response Format

**Success Responses** (POST `/` and POST `/?action=update_preferences`):
Returns complete user data structure (same format as `data.json`):
```json
{
  "preferences": {
    "theme": "ocean",
    "minutes_objective": 480
  },
  "token": "base64:encrypted:timestamp:signature",
  "weeks": {
    "2026-w-03": {
      "days": {
        "13-01-2026": {
          "hours": ["08:30", "12:00", "13:00", "18:30"],
          "breaks": {
            "morning": "00:00",
            "noon": "01:00",
            "afternoon": "00:00"
          },
          "effective_to_paid": [
            "+ 00:07 => morning break",
            "+ 00:07 => afternoon break"
          ],
          "effective": "09:00",
          "paid": "09:14"
        }
      },
      "total_effective": "09:00",
      "total_paid": "09:14"
    }
  }
}
```

**Error Responses**:
All errors use the `error` field (singular):
```json
{
  "error": "Error message"
}
```

With optional additional fields:
```json
{
  "error": "Validation failed",
  "fields": {
    "theme": "Invalid format",
    "minutes_objective": "Must be > 0"
  }
}
```

Or with context:
```json
{
  "error": "Failed to fetch data from Kelio",
  "token_invalidated": true
}
```

## Development Patterns

### Routing
- Convention-based action dispatching: `?action=update_preferences` → `updatePreferencesAction()`
- Route registration in index.php: `$router->post('/', BaseController::class)`
- Middleware: `->middleware(AuthMiddleware::class)`

### Authentication
Two strategies via AuthMiddleware:
1. **Token-based**: Extract username/password from encrypted token
2. **Credential-based**: Validate against Kelio API once

Format: `base64(username):base64(encrypted_password):timestamp:signature`

### Dependency Injection
- Services registered in ServiceProvider
- Constructor injection with autowiring
- Access via container: `$container->get(Auth::class)`

### Data Persistence
- File-based JSON storage with file locking
- Multiple fallback locations (./data.json, ../data.json, /tmp/)
- Pretty-print in debug mode
- Weekly-based data structure with full historical tracking
- Each day contains: hours, breaks, effective/paid times, transformation logs

**Storage Structure** (`data.json`):
```json
{
  "username": {
    "preferences": { "theme": "ocean" },
    "token": "encrypted_token_string",
    "weeks": {
      "YYYY-w-WW": {
        "days": {
          "DD-MM-YYYY": {
            "hours": ["HH:MM", ...],
            "breaks": { "morning": "HH:MM", "noon": "HH:MM", "afternoon": "HH:MM" },
            "effective_to_paid": ["transformation messages"],
            "effective": "HH:MM",
            "paid": "HH:MM"
          }
        },
        "total_effective": "HH:MM",
        "total_paid": "HH:MM"
      }
    }
  }
}
```

### Error Handling
- JsonResponse::success() and ::serverError()
- HTTP status codes: 200, 400, 401, 404, 422, 429, 500
- Exception details in debug mode only

## Code Style

- PSR-4 autoloading
- Type declarations on all methods
- Snake_case for actions, camelCase for methods
- Early returns for validation
- Dependency injection over global state

## Key Files

- `src/core/Router.php` - HTTP routing with middleware support
- `src/core/Container.php` - Dependency injection container
- `src/services/Auth.php` - Token generation and validation
- `src/services/KelioClient.php` - Kelio API integration
- `src/services/Storage.php` - JSON data persistence
- `src/middleware/AuthMiddleware.php` - Authentication & rate limiting

## Configuration

Critical settings in config.php:
- `kelio_url` - Kelio instance URL
- `encryption_key` - AES-256 key (32+ characters)
- `admin_username` / `admin_password` - Admin credentials
- `pause_time` - Break bonus duration for morning/afternoon (default: 7 minutes)
- `noon_minimum_break` - Minimum mandatory lunch break (default: 60 minutes / 1 hour)
- `noon_break_start` / `noon_break_end` - Lunch break window (12h00-14h00)
- `start_limit_minutes` / `end_limit_minutes` - Work day boundaries
- `morning_break_threshold` / `afternoon_break_threshold` - Auto-break thresholds
- `rate_limit_max_attempts` - Failed login attempts (default: 5)
- `rate_limit_window` - Rate limit window in seconds (default: 300)
- `debug_mode` - Enable verbose errors and pretty JSON

## Business Rules

### Time Calculation (TimeCalculator)
1. **Noon Minimum Break**: If work spans lunch period (12h00-14h00), minimum 1 hour break is enforced
2. **Automatic Breaks**: Morning/afternoon break bonuses added based on thresholds (7 min each if working past 11h00 and 16h00)
3. **Paid Hours**: Effective hours + configured pause_time bonuses
4. **Break Deduction Limit**: Never deduct more than the total pause_time bonuses earned

Example:
- Work: 08:30-12:00, 12:47-18:30 (9h13 effective, 47min lunch break < 1h minimum)
- Gained time: 1h00 (minimum) - 0h47 (actual) = 0h13
- Bonuses: 7min (morning) + 7min (afternoon) = 14min
- Deduction: min(13min gained, 14min bonus) = 13min
- Result: 9h13 effective, 9h14 paid (9h13 + 14min bonus - 13min deduction)

## Security Notes

- Passwords encrypted with AES-256-CBC in tokens
- Rate limiting: 5 attempts per 300 seconds per IP
- CSRF protection via Kelio's token system
- Security headers in all JSON responses
- Token invalidation on password change
- Admin endpoints use separate credentials

## Production Checklist

1. Change `encryption_key` to secure random string (32+ chars)
2. Change `admin_password` to strong password
3. Set `enable_form_access` to false (API-only mode)
4. Set `debug_mode` to false
5. Enable SSL verification for Kelio connection
6. Ensure data.json is writable by web server (0664)
7. Set config.php permissions to 600
8. Configure web server URL rewriting (.htaccess or nginx.conf)

## Testing

- **Framework**: PHPUnit 10.5 via Docker (no local PHP needed)
- **Coverage**: > 80% code coverage
- **Status**: 242 tests, 590 assertions - 100% passing
- **Structure**:
  - Feature Tests: End-to-end router integration
  - Unit Tests: Services, controllers, middleware
  - Fixtures: Real HTML from Kelio API (not mocks)
  - Multi-week data validation tests

Quick test commands:
```bash
./run-tests.sh              # All tests (242 tests)
./run-tests.sh --unit       # Unit tests only
./run-tests.sh --filter AuthTest  # Specific test
./run-tests.sh --coverage   # Generate HTML coverage report
```

Test categories:
- Services: Auth, Storage, KelioClient, RateLimiter, TimeCalculator (with weekly data tests)
- Controllers: Base (with multi-week data tests), BaseGuest, Data, Icon, Manifest
- Middleware: AuthMiddleware (token & credential auth)
- Feature: ApiRoutesTest (all HTTP routes end-to-end)

## Notes

- Kelio limitation: API returns max 4 items per request (3 requests needed)
- Token format includes signature to prevent tampering
- File locking prevents data corruption during concurrent access
- Current day handled specially (incomplete day with odd hours)
- Break logic: conditionally adds morning/afternoon breaks based on thresholds
- Tests run in Docker - no local PHP installation required
- **Weekly data structure**: Data organized by ISO week numbers (YYYY-w-WW format)
- **Historical tracking**: All weeks are preserved in storage for full history
- **Multi-week support**: API can return data spanning multiple weeks in single response
- **Detailed breakdowns**: Each day includes break tracking and effective-to-paid transformation logs
