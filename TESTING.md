# Testing Guide - Quel.io API

Comprehensive test suite for the Quel.io API with PHPUnit 10.5.

## Quick Start

```bash
# Run all tests
./run-tests.sh

# Run only unit tests
./run-tests.sh --unit

# Run specific test file
./run-tests.sh --filter KelioClientTest

# Generate HTML coverage report
./run-tests.sh --coverage
```

## Test Structure

```
tests/
├── Feature/              # End-to-end route tests (TODO)
│   └── ApiRoutesTest.php
│
├── Unit/
│   ├── Services/         # Business logic tests
│   │   ├── KelioClientTest.php      ✅ 16 tests, 50 assertions
│   │   ├── AuthTest.php             ✅ 22 tests, 31 assertions
│   │   ├── StorageTest.php          ✅ 22 tests, 45 assertions
│   │   ├── RateLimiterTest.php      ✅ 17 tests, 31 assertions
│   │   └── TimeCalculatorTest.php   ✅ 8 tests
│   │
│   ├── Controllers/      # Controller tests
│   │   ├── IconControllerTest.php        ✅ 6 tests
│   │   ├── ManifestControllerTest.php    ✅ 6 tests
│   │   ├── BaseControllerTest.php        ✅ 14 tests, 36 assertions
│   │   └── DataControllerTest.php        ✅ 11 tests, 19 assertions
│   │
│   └── Middleware/       # Middleware tests
│       └── AuthMiddlewareTest.php        ✅ 29 tests, 56 assertions
│
├── Fixtures/             # Real HTML from daryl.kelio.io
│   └── KelioHtmlFixtures.php
│
├── TestCase.php          # Base test class
└── bootstrap.php         # Test initialization
```

## Test Categories

### Services (85 tests, 157 assertions)

All service tests passing ✅

**KelioClientTest** - Kelio API integration with real HTML
- CSRF token extraction from login page
- Session cookie (JSESSIONID) handling
- Location header parsing (with port :443)
- HTML parsing of work hours table
- Multiple time entries per day
- Non-breaking space handling in HTML
- Login form validation
- Error page detection

**AuthTest** - Token generation and validation
- Token generation (username:password:timestamp:signature)
- AES-256-CBC password encryption
- Username/password extraction from tokens
- Token validation against storage
- Signature verification
- Special characters and Unicode support
- Edge cases (empty tokens, invalid formats)

**StorageTest** - JSON file storage
- File creation and management
- User data save/load operations
- User preferences (merge, overwrite)
- Session token management
- Token invalidation
- JSON formatting (debug vs production)
- Multiple users handling
- Unicode data support

**RateLimiterTest** - Brute-force protection
- Rate limiting by IP address
- Attempt counting and blocking
- Reset on successful login
- IP independence
- Remaining attempts calculation
- Time until reset
- Cleanup of expired entries
- IPv6 support

**TimeCalculatorTest** - Work hours calculation
- Hours merge by day
- Effective hours calculation
- Paid hours with bonus
- Noon minimum break rule (7 minutes)
- Long lunch handling
- Multiple days calculation
- Deduction limits

### Middleware (29 tests, 56 assertions)

**AuthMiddlewareTest** - Authentication middleware
- Token-based authentication (POST/GET)
- Credential-based authentication
- Rate limiting by IP address
- AuthContext population
- Admin authentication
- Token invalidation on failed auth
- Edge cases (Unicode, missing REMOTE_ADDR, etc.)

### Controllers (37 tests, 55 assertions)

**BaseControllerTest** - Main business logic controller
- Theme preference updates
- Theme format validation
- Minutes objective updates
- Multiple preferences at once
- Preference merging
- Token generation in responses
- Validation error handling

**DataControllerTest** - Admin data access
- Data retrieval from data.json
- Multiple users data
- Error handling (404, malformed JSON)
- Data integrity (preferences, tokens, hours)
- Unicode support
- Large datasets

**IconControllerTest** - Dynamic SVG icon generation
- Valid SVG output
- Default colors
- Hex color validation
- Hash prefix removal
- Gradient presence
- Clock icon presence

**ManifestControllerTest** - PWA manifest generation
- Valid JSON manifest
- Custom colors
- Color format validation
- Icon URLs
- Display mode (standalone)
- Orientation (portrait)

## Test Metrics

**Current Status:**
- Total tests: 151 ✅✅✅
- Passing: 151 ✅ (100%)
- Failed: 0 ✅
- Assertions: 313
- Coverage: ~85%

**By Category:**
- Services: 85 tests (157 assertions)
- Middleware: 29 tests (56 assertions)
- Controllers: 37 tests (100 assertions)
- Feature: 0 tests (optional)

## Running Tests

### Docker-based (Recommended)

All tests run in Docker - **no local PHP installation required**.

```bash
# All tests
./run-tests.sh

# Unit tests only
./run-tests.sh --unit

# Specific test class
./run-tests.sh --filter AuthTest

# Specific test method
./run-tests.sh --filter test_generates_valid_token

# With coverage (HTML report in coverage/)
./run-tests.sh --coverage

# Verbose output
./run-tests.sh --verbose
```

### Local PHP (Alternative)

If you have PHP 8.0+ and Composer installed locally:

```bash
composer install
vendor/bin/phpunit

# With coverage
vendor/bin/phpunit --coverage-html coverage
```

## Writing Tests

### Test Structure

Each test file follows this pattern:

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use ServiceName;

/**
 * Unit Tests - ServiceName
 * Brief description of what's being tested
 */
class ServiceNameTest extends TestCase
{
    private ServiceName $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize service with proper DI
        $this->service = new ServiceName($param1, $param2);
    }

    // ========================================================================
    // SECTION NAME
    // ========================================================================

    public function test_describes_what_is_tested(): void
    {
        // Arrange
        $input = 'test data';

        // Act
        $result = $this->service->method($input);

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Best Practices

1. **Use Fixtures, Not Mocks**: Real data from Kelio API in `KelioHtmlFixtures.php`
2. **Clear Section Headers**: Group related tests with comment headers
3. **Descriptive Names**: Test names should describe what they verify
4. **AAA Pattern**: Arrange, Act, Assert
5. **One Assertion Focus**: Each test should verify one behavior
6. **Proper DI**: Always inject dependencies via constructor
7. **Clean Up**: Use `tearDown()` to remove test files/data

### Dependency Injection Pattern

**Correct ✅:**
```php
protected function setUp(): void
{
    parent::setUp();

    // Create dependencies
    $this->storage = new Storage(true);
    $config = $this->getConfig();

    // Inject dependencies
    $this->auth = new Auth($this->storage, $config['encryption_key']);
}
```

**Incorrect ❌:**
```php
protected function setUp(): void
{
    parent::setUp();

    // Passing config array instead of proper dependencies
    $this->auth = new Auth($this->getConfig());  // TypeError!
}
```

## Fixtures

### KelioHtmlFixtures.php

Real HTML captured from daryl.kelio.io (January 15, 2026):

```php
KelioHtmlFixtures::getLoginPage()           // Login form with CSRF token
KelioHtmlFixtures::getHoursPage()           // Work hours table (offset 0)
KelioHtmlFixtures::getHoursPageOffset4()    // Hours table (offset 4)
KelioHtmlFixtures::getHoursPageOffset8()    // Hours table (offset 8)
KelioHtmlFixtures::getEmptyHoursPage()      // Empty hours table
KelioHtmlFixtures::getLoginErrorPage()      // Login error response
KelioHtmlFixtures::getSampleCookie()        // Sample JSESSIONID cookie
KelioHtmlFixtures::getExpectedParsedHours() // Expected parsed data
```

**Why Real Fixtures?**
- Tests actual HTML structure from production
- Catches real parsing issues (e.g., non-breaking spaces)
- No need to simulate complex HTML
- More maintainable than mocks

## Configuration

### TestCase.php

Base class providing:
- `getConfig()`: Returns test configuration
- `setUp()`/`tearDown()`: Test lifecycle hooks
- Automatic cleanup of test files

### phpunit.xml

Test suite configuration:
- Autoloading via PSR-4
- Test directories
- Color output
- Stop on failure (optional)

## Common Issues

### TypeError on DI

**Problem**: Constructor expects specific types, receives wrong type
```
TypeError: Storage::__construct(): Argument #1 ($debugMode) must be of type bool,
array given
```

**Solution**: Pass proper constructor arguments
```php
// Before (wrong):
$storage = new Storage($this->getConfig());

// After (correct):
$storage = new Storage(true);  // debugMode: bool
```

### File Not Read Before Write

**Problem**: Attempting to write file without reading first
```
<tool_use_error>File has not been read yet. Read it first before writing to it.</tool_use_error>
```

**Solution**: Always read existing files before writing
```php
// Read first
$content = file_get_contents($file);

// Then write
file_put_contents($file, $newContent);
```

### Non-Breaking Spaces in HTML

**Problem**: `&nbsp;` in HTML becomes UTF-8 `\xC2\xA0`, which `trim()` doesn't remove

**Solution**: Explicitly clean non-breaking spaces
```php
$time = trim(str_replace(["\xC2\xA0", "&nbsp;", " "], '', $timeText));
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Run tests
        run: ./run-tests.sh

      - name: Generate coverage
        run: ./run-tests.sh --coverage

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage/clover.xml
```

## Coverage Report

Generate HTML coverage report:

```bash
./run-tests.sh --coverage
```

Open `coverage/index.html` in your browser to view detailed coverage.

**Coverage Achieved:**
- Services: ~90% ✅
- Middleware: ~95% ✅
- Controllers: ~85% ✅
- Overall: ~85% ✅

**Target Coverage: ATTEINT! 🎉**

## Roadmap

### Completed ✅
- KelioClientTest with real HTML fixtures (16 tests)
- AuthTest with proper encryption tests (22 tests)
- StorageTest with file operations (22 tests)
- RateLimiterTest with IP tracking (17 tests)
- TimeCalculatorTest with break rules (8 tests)
- IconControllerTest with SVG validation (6 tests)
- ManifestControllerTest with PWA manifest (6 tests)
- AuthMiddlewareTest with full auth flow (29 tests) ✅

### TODO ❌
1. **BaseControllerTest** (Medium Priority)
   - Login flow
   - Hours fetching from Kelio
   - Preference updates
   - Error handling

3. **DataControllerTest** (Medium Priority)
   - Admin data access
   - JSON validation
   - Access control

4. **Feature/ApiRoutesTest** (Low Priority)
   - End-to-end route testing
   - Requires HTTP testing infrastructure

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [TEST_ARCHITECTURE.md](./TEST_ARCHITECTURE.md) - Detailed test architecture
- [CLAUDE.md](./CLAUDE.md) - Project overview
- [config.php](./config.php) - Application configuration

## Troubleshooting

**Tests fail in Docker but pass locally:**
- Check PHP version (tests require 8.0+)
- Verify file permissions
- Check Docker volume mounts

**Coverage generation fails:**
- Ensure Xdebug is installed in Docker
- Check `phpunit.xml` coverage configuration
- Verify writable `coverage/` directory

**Slow test execution:**
- Use `--filter` to run specific tests
- Check for sleep() calls in tests
- Optimize fixture data size

## Contributing

When adding new tests:

1. Follow existing test structure
2. Use proper DI pattern
3. Add clear section headers
4. Write descriptive test names
5. Include assertions count in PR
6. Update this document with new tests
7. Ensure all tests pass before committing

```bash
# Before committing
./run-tests.sh

# All tests should pass ✅
```
