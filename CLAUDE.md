# Quel io - API

PHP 8 REST API for time tracking with Kelio integration.

## Tech Stack

- **Language**: PHP 8.0+
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
- Test login: `curl -X POST http://localhost:8080/ -d "username=user&password=pass"`
- Access raw data: `GET /data.json` (requires admin credentials)

## API Endpoints

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| GET | `/` | None | Display login form (if enabled) |
| POST | `/` | Token or Credentials | Login & fetch hours |
| POST | `/?action=update_preferences` | Token | Update user preferences |
| GET | `/icon.svg` | None | Dynamic PWA icon |
| GET | `/manifest.json` | None | PWA manifest |
| GET/POST | `/data.json` | Admin | Raw data access |

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
- `pause_time` - Break duration (default: 7 minutes)
- `start_limit_minutes` / `end_limit_minutes` - Work day boundaries
- `rate_limit_max_attempts` - Failed login attempts (default: 5)
- `debug_mode` - Enable verbose errors and pretty JSON

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

## Notes

- Kelio limitation: API returns max 4 items per request (3 requests needed)
- Token format includes signature to prevent tampering
- File locking prevents data corruption during concurrent access
- Current day handled specially (incomplete day with odd hours)
- Break logic: conditionally adds morning/afternoon breaks based on thresholds
