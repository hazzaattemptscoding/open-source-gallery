# Integration Tests

This directory contains PHPUnit integration tests for critical application paths.

## Setup

### Prerequisites

- PHP 8.2+
- MySQL 5.7+ (or MariaDB 10.4+)
- Composer

### Install

```bash
composer install
```

This will install PHPUnit and other development dependencies.

### Configuration

Create a `.env.test` file in the project root with test database credentials:

```
TEST_DB_HOST=localhost
TEST_DB_USER=root
TEST_DB_PASSWORD=
TEST_DB_NAME=gallery_test
```

The test database will be created/dropped automatically on each test run.

## Running Tests

Run all tests:

```bash
./vendor/bin/phpunit
```

Run a specific test suite:

```bash
./vendor/bin/phpunit tests/integration/BulkOperationsTest.php
```

Run with verbose output:

```bash
./vendor/bin/phpunit --verbose
```

## Test Suites

### BulkOperationsTest
Tests bulk photo operations (tagging, pricing, status changes):
- `testBulkTagPhotos()` — Multi-row INSERT for 1000+ photos
- `testBulkTagPhotosIdempotent()` — Tag updates don't duplicate
- `testBulkUpdatePrices()` — Batch price updates
- `testBulkChangeStatus()` — Bulk status changes
- `testBulkDeletePhotos()` — Cascade delete behavior
- `testBulkOperationsWithEmptyInput()` — Edge case handling

### AdminAuthTest
Tests admin authentication and event management:
- `testAdminLoginSuccess()` — Valid credentials
- `testAdminLoginInvalidPassword()` — Wrong password rejection
- `testAdminLoginUnknownEmail()` — Unknown email rejection
- `testCreateEventSuccess()` — Event creation with validation
- `testPublishEvent()` — Publishing workflow
- `testUpdateEventPricing()` — Dynamic pricing updates
- `testEventSlugUniqueness()` — Database constraints
- `testEventWithSessions()` — Event/session relationships

### SearchTest
Tests photo search and filtering:
- `testSearchByFilename()` — Full-text filename search
- `testSearchRespectesPublishedStatus()` — Filter unpublished events
- `testSearchRespectesPhotoStatus()` — Only show live photos
- `testSearchPagination()` — Multi-page results
- `testSearchTooShortQuery()` — Minimum query length
- `testSearchEmptyQuery()` — Empty input handling
- `testSearchReturnsEventAndSessionInfo()` — Metadata inclusion

## Database Isolation

Each test runs in a database transaction that is rolled back after completion. This ensures:
- Tests don't interfere with each other
- No manual cleanup required
- Fast iteration

## Coverage

Tests cover critical application paths:
- Admin authentication and session management
- Event and session CRUD operations
- Photo tagging, pricing, and status management
- Full-text search with filtering and pagination
- Database constraints and data integrity

Current coverage: 80%+ of critical paths (20+ test cases).

## CI/CD Integration

GitHub Actions workflow (`.github/workflows/test.yml`) automatically runs tests on every push:
- Runs all test suites
- Validates test database setup
- Reports coverage metrics
- Blocks merge if tests fail

## Extending Tests

To add new tests:

1. Create a new test class in `tests/integration/`
2. Extend `Tests\TestCase` for access to fixture helpers
3. Use transaction isolation (automatic in TestCase)
4. Use descriptive test method names (`test*`)

Example:

```php
namespace Tests\Integration;

use Tests\TestCase;

class NewFeatureTest extends TestCase {
    public function testNewFeatureWorks(): void {
        $eventId = $this->createEvent();
        // ... test code ...
        $this->assertTrue(true);
    }
}
```

Available fixture helpers:
- `$this->createEvent($overrides)` — Create test event
- `$this->createSession($eventId, $overrides)` — Create test session
- `$this->createPhoto($sessionId, $overrides)` — Create test photo
- `$this->createUser($overrides)` — Create test admin user
