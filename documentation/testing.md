# Testing Guide

This document describes how to run, write, and maintain tests for the pressmind SDK.

**Related documentation:**

- **[Makefile Reference](makefile.md)** – All `make` targets with unambiguous names (test-docker-*, test-unit, test-integration, test-import-integration, test-all, test-record-api-snapshot, test-coverage-*, etc.) and workflow examples.
- **[Code Coverage Summary](code-coverage-summary.md)** – Current coverage metrics and areas to improve.

---

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Test Suites](#test-suites)
- [Running Tests with Docker (recommended)](#running-tests-with-docker-recommended)
- [Running Tests without Docker](#running-tests-without-docker)
- [API Snapshot Recording](#api-snapshot-recording)
- [Code Coverage](#code-coverage)
- [Writing Tests](#writing-tests)
- [Fixtures](#fixtures)
- [CI / GitHub Actions](#ci--github-actions)
- [Troubleshooting](#troubleshooting)

---

## Overview

The SDK uses [PHPUnit 10](https://phpunit.de/documentation.html) and is organized into three test suites:

| Suite               | Purpose                              | Services Required         | Run Time   |
|---------------------|--------------------------------------|---------------------------|------------|
| **Unit**            | Fast, isolated tests (mocked DB/API) | None                      | ~1 second  |
| **Integration**     | Real MySQL, MongoDB, OpenSearch      | MySQL, MongoDB, OpenSearch | ~30 seconds |
| **ImportIntegration** | Full import pipeline with API replay | MySQL, MongoDB            | ~2 minutes |

All test files live under `tests/` with the namespace `Pressmind\Tests\{Suite}\...`.

---

## Requirements

### Docker (recommended for all suites)

- Docker & Docker Compose
- `.env` file with pressmind API credentials (required for recording API snapshots)

### Without Docker (Unit tests only)

- PHP 8.2+ with extensions: `json`, `curl`, `bcmath`, `pdo`, `pdo_mysql`, `mbstring`, `gd`, `zlib`, `fileinfo`, `redis`, `imagick`
- Composer

---

## Quick Start

### First-time Setup

```bash
# 1. Build Docker images
make test-docker-build

# 2. Run Unit tests (no services needed)
make test-unit

# 3. For ImportIntegration: set up API credentials and record a snapshot
cp .env.example .env
# Edit .env and fill in PM_API_KEY, PM_API_USER, PM_API_PASSWORD

# 4. Record API snapshot (calls live pressmind API, saves responses as JSON fixtures)
make test-record-api-snapshot

# 5. Now run ImportIntegration tests (uses recorded fixtures, no live API needed)
make test-import-integration

# 6. Or run everything at once
make test-all
```

### Day-to-day Usage

```bash
make test-unit                        # Fast feedback loop during development
make test-integration                  # After DB/search changes
make test-import-integration           # After import logic changes
make test-all                          # Full suite before committing
make test-coverage-all                 # Generate coverage report (or test-coverage-unit-integration)
```

---

## Test Suites

### Unit Tests

Isolated tests that mock all external dependencies (database, API, filesystem). No services required.

```bash
make test-unit             # Docker
composer test              # Local (no Docker)
```

### Integration Tests

Require running MySQL, MongoDB, and OpenSearch. Test real database operations, search indexing, and query building. Docker starts all services automatically.

```bash
make test-integration      # Docker (recommended)
composer test:integration  # Local (requires running services)
```

### ImportIntegration Tests

Run a full SDK install and import cycle using **recorded API responses** (replay fixtures). This suite verifies the entire pipeline end-to-end without needing a live pressmind API connection.

**Important:** These tests require API fixtures in `tests/fixtures/api/`. If the fixture directory is empty or outdated, you must record a snapshot first (see [API Snapshot Recording](#api-snapshot-recording)).

```bash
make test-import-integration   # Docker (recommended)
composer test:import           # Local (requires running MySQL + MongoDB)
```

### All Suites

```bash
make test-all              # Docker: all three suites
composer test:all          # Local: all three suites
```

---

## Running Tests with Docker (recommended)

Docker Compose provides all services pre-configured: MySQL 8.0, MongoDB 7, OpenSearch 2.11. The **Makefile** is the primary interface.

For a **full reference** of every Make target with unambiguous names, see **[Makefile Reference](makefile.md)**.

### Available Make Targets (summary)

| Target | Description |
|--------|-------------|
| `make test-docker-build` | Build Docker images for tests |
| `make test-docker-rebuild` | Force full rebuild (no cache; e.g. after adding PHP extensions) |
| `make test-unit` | Unit test suite only |
| `make test-integration` | Integration test suite (MySQL + MongoDB + OpenSearch) |
| `make test-import-integration` | ImportIntegration suite (MySQL + MongoDB, uses fixtures) |
| `make test-all` | All three test suites |
| `make test-record-api-snapshot` | Record live API responses to `tests/Fixtures/api/` |
| `make test-record-api-snapshot-then-import` | Record snapshot, rebuild, then run ImportIntegration |
| `make test-coverage-all` | All suites with PCOV code coverage (text + HTML) |
| `make test-coverage-unit-integration` | Unit + Integration with coverage (faster) |
| `make test-all-verbose` | All suites with full PHPUnit output |
| `make test-import-integration-verbose` | ImportIntegration with full output (debugging) |
| `make test-docker-down` | Stop and remove all containers and volumes |
| `make test-docker-cleanup` | Remove leftover/stuck run containers |

### Environment Variables

Docker Compose sets all required variables automatically. For local runs, defaults come from `phpunit.xml` and `tests/bootstrap.php`:

| Variable         | Default (Docker)             | Description        |
|------------------|------------------------------|--------------------|
| `DB_HOST`        | `mysql`                      | MySQL host         |
| `DB_NAME`        | `pressmind_test`             | MySQL database     |
| `DB_USER`        | `root`                      | MySQL user         |
| `DB_PASS`        | `root`                      | MySQL password     |
| `MONGODB_URI`    | `mongodb://mongodb:27017`   | MongoDB connection |
| `MONGODB_DB`     | `pressmind_test`             | MongoDB database   |
| `OPENSEARCH_URI` | `http://opensearch:9200`     | OpenSearch URL     |

---

## Running Tests without Docker

For **Unit tests**, no setup is needed beyond Composer:

```bash
composer install --no-scripts
composer test
```

For **Integration/ImportIntegration tests**, you need local MySQL, MongoDB (and optionally OpenSearch) running. Override the default connection settings via environment variables or in a local `phpunit.xml`.

### Composer Scripts

| Script                    | Description                              |
|---------------------------|------------------------------------------|
| `composer test`           | Unit tests                               |
| `composer test:integration` | Integration tests                      |
| `composer test:import`    | ImportIntegration tests                  |
| `composer test:all`       | All suites                               |
| `composer test:coverage`  | Unit tests with HTML coverage report     |

### Running Individual Tests

```bash
# Single test class
vendor/bin/phpunit --no-coverage tests/Unit/Search/Condition/BookingStateTest.php

# Single test method
vendor/bin/phpunit --no-coverage --filter testConstructorAndGetters tests/Unit/Search/Condition/BookingStateTest.php

# All tests matching a pattern
vendor/bin/phpunit --no-coverage --filter "MongoDB"
```

---

## API Snapshot Recording

The ImportIntegration suite does **not** call the live pressmind API. Instead, it replays pre-recorded API responses from `tests/fixtures/api/`. This makes the tests deterministic, fast, and independent of network availability.

### How It Works

1. `record-api-snapshot.php` connects to the live pressmind REST API
2. It calls endpoints like `ObjectType/getAll`, `Text/search`, `Text/getById` for all media object types
3. Each API response is saved as a JSON file in `tests/fixtures/api/`
4. During tests, `ReplayClient` serves these fixtures instead of making real HTTP requests

### When to Record a New Snapshot

- **First-time setup**: fixtures directory is empty
- **API changes**: pressmind REST API has new fields or changed response format
- **New media object types**: types were added in the pressmind backend
- **Data refresh**: you want tests to run against current production data

### Recording a Snapshot

```bash
# 1. Ensure .env has valid API credentials
cp .env.example .env
# Edit .env:
#   PM_API_KEY=your-key
#   PM_API_USER=your-user
#   PM_API_PASSWORD=your-password

# 2. Record (via Docker, recommended)
make test-record-api-snapshot

# 3. Or record and immediately run ImportIntegration tests
make test-record-api-snapshot-then-import
```

**Alternative: use a pm-config.php** (reads credentials from config file):

```bash
php tests/bin/record-api-snapshot.php --config=path/to/pm-config.php
```

The recording script outputs a summary:

```
  Snapshot Summary
  ───────────────────────────────────────────────────────────
    Object Types:     11
    Media Objects:    247 recorded
    Fixture Files:    412 (18.3 MB)
    Duration:         42.1s
    Output:           tests/fixtures/api
  ───────────────────────────────────────────────────────────
```

### Generating Touristic Fixtures

Touristic fixtures (cheapest price data) are generated from a live database and anonymized:

```bash
# Via Docker
docker compose -f docker-compose.test.yml run --rm import-test php tests/bin/generate-fixtures.php <id_media_object> <scenario_name>

# Local
DB_HOST=localhost DB_NAME=pressmind_test php tests/bin/generate-fixtures.php 12345 summer_tour
```

Output: `tests/fixtures/touristic/scenario_<name>.json` — dates are converted to relative offsets so fixtures remain valid over time.

---

## Code Coverage

Coverage reports are generated in `build/` using PCOV (included in the Docker image).

### Generate Coverage

```bash
# Full coverage (all suites, Docker, recommended)
make test-coverage-all

# Quick coverage (Unit + Integration only, faster)
make test-coverage-unit-integration

# Local (requires Xdebug or PCOV)
composer test:coverage
```

### Output

- **Text report:** `build/coverage.txt`
- **HTML report:** `build/coverage/index.html` (open in browser)

### Current Status

See [code-coverage-summary.md](code-coverage-summary.md) for the latest coverage metrics (Unit + Integration), well-covered areas, and core logic that still needs tests.

---

## Writing Tests

### Directory Structure

```
tests/
├── bootstrap.php                        # Autoloader, ENV defaults, constants
├── bin/
│   ├── record-api-snapshot.php          # Record live API → fixtures/api/
│   └── generate-fixtures.php            # Generate touristic fixtures from DB
├── fixtures/
│   ├── api/                             # Recorded API responses (~400 JSON files)
│   ├── touristic/                       # Price/date fixtures (date-relative)
│   └── mongodb/                         # MongoDB search document fixtures
├── Unit/                                # Isolated tests, no services needed
│   ├── AbstractTestCase.php             # Base class: mocked DB + config
│   └── ...
├── Integration/                         # Real DB tests
│   ├── AbstractIntegrationTestCase.php  # Base class: real MySQL + MongoDB
│   ├── FixtureLoader.php                # Helper: load SQL/JSON fixtures
│   └── ...
└── ImportIntegration/                   # Full pipeline tests
    ├── AbstractImportTestCase.php       # Base class: install + import via replay
    ├── _app/                            # Generated app dir (pm-config, scaffolded classes)
    └── ...
```

### Naming Conventions

- One test class per production class: `src/Pressmind/Foo/Bar.php` → `tests/Unit/Foo/BarTest.php`
- Namespace mirrors directory: `Pressmind\Tests\Unit\Foo\BarTest`
- Test methods: `testMethodName()` or `testMethodNameWithSpecificCondition()`

### Base Classes

| Base Class | When to Use |
|------------|-------------|
| `PHPUnit\Framework\TestCase` | Simple unit tests without Registry/DB needs. |
| `Pressmind\Tests\Unit\AbstractTestCase` | Unit tests that need mocked DB adapter and config via Registry. |
| `Pressmind\Tests\Integration\AbstractIntegrationTestCase` | Integration tests with real MySQL and MongoDB. |
| `Pressmind\Tests\ImportIntegration\AbstractImportTestCase` | Tests that need a fully imported dataset (extends AbstractIntegrationTestCase). |

### Example: Unit Test

```php
<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\BookingState;

class BookingStateTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $condition = new BookingState('state_var', [1, 3]);
        $this->assertSame(6, $condition->getSort());
        $this->assertEmpty($condition->getValues());
    }

    public function testGetConditionReturnsValidSQL(): void
    {
        $condition = new BookingState('booking_state', [0, 1, 2]);
        $sql = $condition->getCondition();
        $this->assertStringContainsString('booking_state', $sql);
    }
}
```

### Example: Unit Test with Mocked DB

```php
<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\Tests\Unit\AbstractTestCase;

class AbstractObjectTest extends AbstractTestCase
{
    public function testCreateSetsProperties(): void
    {
        // setUp() already configured Registry with mock DB and config
        $object = new ConcreteTestObject();
        $object->id = 42;
        $this->assertSame(42, $object->id);
    }
}
```

### Guidelines

1. **Unit tests must not access real databases.** Use `AbstractTestCase` which provides a mocked DB adapter.
2. **Avoid duplicate tests.** Check existing tests before adding new ones.
3. **Clean up in `tearDown()`.** Integration tests must leave the database clean.
4. **Use data providers** for multiple input/output combinations:

```php
public static function priceDataProvider(): array
{
    return [
        'positive price' => [100.50, '100,50 €'],
        'zero price'     => [0.00, '0,00 €'],
        'negative price' => [-50.00, '-50,00 €'],
    ];
}

/**
 * @dataProvider priceDataProvider
 */
public function testFormatPrice(float $input, string $expected): void
{
    $this->assertSame($expected, PriceHandler::format($input, 'de'));
}
```

---

## Fixtures

### API Replay Fixtures (`tests/fixtures/api/`)

Recorded JSON responses from the pressmind REST API. Used by `ImportIntegration` tests via `ReplayClient` to run the full import offline. Fixture data is generated locally only and is not provided by the repository or CI; for data protection reasons, fixture JSON files must not be committed.

See [API Snapshot Recording](#api-snapshot-recording) for how to create and update these fixtures.

### Touristic Fixtures (`tests/fixtures/touristic/`)

Pre-computed price/date fixtures with relative date offsets (so they stay valid over time). Generated from a live database via `tests/bin/generate-fixtures.php`. Customer data is anonymized automatically.

### MongoDB Fixtures (`tests/fixtures/mongodb/`)

Sample MongoDB search documents for search query tests.

---

## CI / GitHub Actions

The CI workflow (`.github/workflows/tests.yml`) runs on push/PR to `master` and `development`. It is aligned with the Docker test setup (`docker-compose.test.yml`): same services and env vars for the Integration suite.

| Job                 | Services                                      | Suite       | PHP Extensions               |
|---------------------|-----------------------------------------------|-------------|------------------------------|
| **Unit Tests**      | None                                          | Unit        | ..., redis, imagick          |
| **Integration Tests** | MySQL 8.0, MongoDB 7, Redis 7, OpenSearch 2.11 | Integration | ..., redis, mongodb, imagick |

Env vars for Integration in CI: `DB_*`, `MONGODB_*`, `OPENSEARCH_URI`, `REDIS_HOST`, `REDIS_PORT` (same semantics as Docker).

ImportIntegration tests are not run in CI because they depend on recorded API fixtures and have a longer runtime. Run them locally via `make test-import-integration` or `make test-record-api-snapshot-then-import`.

---

## Troubleshooting

### "No code coverage driver available"

The Docker image includes PCOV. Use `make test-coverage-all` for coverage reports. If the error persists, rebuild the Docker image:

```bash
make test-docker-rebuild
```

For local coverage, install Xdebug or PCOV. The `composer test` scripts use `--no-coverage` by default to avoid this warning.

### Tests fail with "Class not found"

Regenerate the autoloader:

```bash
composer dump-autoload
```

### Integration tests cannot connect to services

Check that Docker services are running:

```bash
docker compose -f docker-compose.test.yml ps
docker compose -f docker-compose.test.yml logs mysql
docker compose -f docker-compose.test.yml logs mongodb
docker compose -f docker-compose.test.yml logs opensearch
```

### ImportIntegration tests fail with empty/missing fixtures

You need to record an API snapshot first:

```bash
# 1. Set up credentials
cp .env.example .env
# Fill in PM_API_KEY, PM_API_USER, PM_API_PASSWORD

# 2. Record
make test-record-api-snapshot

# 3. Then run
make test-import-integration
```

Or do both in one step:

```bash
make test-record-api-snapshot-then-import
```

### PHPUnit version mismatch

The SDK requires PHPUnit 10+. Check your version:

```bash
vendor/bin/phpunit --version
```

If PHPUnit 9 is installed, run `composer update phpunit/phpunit`.

### Skipped tests (S in output)

Some tests are skipped when optional services (OpenSearch, Redis) are unavailable. This is expected in local development. Run via Docker (`make test`) for the full suite with all services.

### Stuck Docker containers

If you see errors about existing containers:

```bash
make test-docker-cleanup   # Remove stuck containers
make test-docker-down      # Full cleanup: stop all, remove volumes
```
