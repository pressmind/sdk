# Makefile – Build and Test Commands

The SDK provides a **Makefile** as the primary interface for building the test environment and running all test suites via Docker. All targets use **unambiguous names**: `test-*` for running tests, `test-docker-*` for Docker lifecycle, and `test-record-*` for API snapshot recording.

**See also:** [Testing Guide](testing.md) for test suites, fixtures, writing tests, and troubleshooting.

---

## Table of Contents

- [Overview](#overview)
- [Naming convention and aliases](#naming-convention-and-aliases)
- [Prerequisites](#prerequisites)
- [Target Reference](#target-reference)
- [Workflow Examples](#workflow-examples)
- [Docker Compose Services](#docker-compose-services)
- [Troubleshooting](#troubleshooting)

---

## Overview

| Purpose | Command |
|--------|---------|
| Build test Docker image | `make test-docker-build` |
| Run Unit test suite | `make test-unit` |
| Run Integration test suite | `make test-integration` |
| Run ImportIntegration test suite | `make test-import-integration` |
| Run all three test suites | `make test-all` |
| Record live API → fixtures | `make test-record-api-snapshot` |
| Record API then run ImportIntegration | `make test-record-api-snapshot-then-import` |
| Code coverage (all suites) | `make test-coverage-all` |
| Code coverage (Unit + Integration only) | `make test-coverage-unit-integration` |
| Remove stuck run containers | `make test-docker-cleanup` |
| Stop containers and remove volumes | `make test-docker-down` |

All test run targets use `docker-compose.test.yml` and run a cleanup step first so repeated invocations do not fail with "container already exists".

---

## Naming convention

- **`test-docker-*`** – Docker image/container lifecycle (build, rebuild, cleanup, down).
- **`test-unit`**, **`test-integration`**, **`test-import-integration`**, **`test-all`** – Which test suite(s) run.
- **`test-record-api-snapshot`**, **`test-record-api-snapshot-then-import`** – Recording live API responses for ImportIntegration.
- **`test-coverage-*`** – Code coverage reports.
- **`test-*-verbose`** – Same as above with full PHPUnit output.

---

## Prerequisites

- **Docker** and **Docker Compose**
- For **ImportIntegration** and **record-snapshot**: `.env` file with pressmind API credentials (see [Testing Guide – API Snapshot Recording](testing.md#api-snapshot-recording))

Optional: if you run `make test-record-api-snapshot` or `make test-record-api-snapshot-then-import`, copy `.env.example` to `.env` and set `PM_API_KEY`, `PM_API_USER`, `PM_API_PASSWORD`.

---

## Target Reference

### Docker lifecycle targets

| Target | Description |
|--------|-------------|
| **`make test-docker-build`** | Build the Docker image used for all test runs. Uses `docker-compose.test.yml` and `docker/Dockerfile`. Run once after clone or when Dockerfile/Composer dependencies change. |
| **`make test-docker-rebuild`** | Force a full rebuild without cache. Runs `docker system prune -af --volumes` then `docker compose -f docker-compose.test.yml build --no-cache`. Use when the image reports "No code coverage driver" (e.g. after adding PCOV). **Warning:** prunes all unused Docker resources; use sparingly. |
| **`make test-docker-cleanup`** | Removes leftover run containers (`sdk-test-run`, `sdk-unit-run`, `sdk-integration-run`, `sdk-import-test-run`). Invoked automatically before each test/coverage run; use manually if you see "container already exists". |
| **`make test-docker-down`** | Runs `test-docker-cleanup` then `docker compose -f docker-compose.test.yml down -v --remove-orphans`. Stops all services and removes volumes. Use for a full reset. |

### Test suite targets

Each runs after **`test-docker-cleanup`** and then the corresponding Docker Compose service.

| Target | Service | What runs | Services required |
|--------|---------|-----------|-------------------|
| **`make test-unit`** | `unit` | PHPUnit with `tests/Unit/` only. No coverage, testdox, colors. | Redis. No MySQL/MongoDB/OpenSearch. |
| **`make test-integration`** | `integration` | PHPUnit with `--testsuite Integration`. Real MySQL, MongoDB, OpenSearch. | MySQL 8.0, MongoDB 7, OpenSearch 2.11, Redis. |
| **`make test-import-integration`** | `import-test` | PHPUnit with `--testsuite ImportIntegration`. Full install + import using **recorded API fixtures** (no live API). | MySQL, MongoDB. No OpenSearch. |
| **`make test-all`** | `test` | PHPUnit with **all three suites** (Unit, Integration, ImportIntegration). | Same as Integration (MySQL, MongoDB, OpenSearch, Redis). |

### API snapshot targets

| Target | Description |
|--------|-------------|
| **`make test-record-api-snapshot`** | Runs `tests/bin/record-api-snapshot.php` in the import-test container (project dir mounted). Calls the **live** pressmind API (credentials from `.env` or environment) and writes JSON fixtures to `tests/Fixtures/api/`. Use when fixtures are missing or after API/version changes. |
| **`make test-record-api-snapshot-then-import`** | Runs `test-record-api-snapshot`, then `test-docker-build`, then the ImportIntegration suite. Use for a full "record and verify" cycle. |

### Coverage targets

Both mount `$(pwd)/build` into the container. Reports: `build/coverage.txt`, `build/coverage/index.html`.

| Target | Description |
|--------|-------------|
| **`make test-coverage-all`** | All three test suites with PCOV (text + HTML). Memory limit 512M. |
| **`make test-coverage-unit-integration`** | Only **Unit** and **Integration** suites with coverage. Skips ImportIntegration; faster. |

If you see "No code coverage driver available", run `make test-docker-rebuild`.

### Verbose targets (debugging)

| Target | Description |
|--------|-------------|
| **`make test-all-verbose`** | Same as `test-all` with full PHPUnit output: `--colors=always --display-skipped --display-incomplete`. |
| **`make test-import-integration-verbose`** | Same as `test-import-integration` with full PHPUnit output. |

---

## Workflow Examples

### First-time setup

```bash
make test-docker-build
make test-unit
# Optional: record API snapshot for ImportIntegration
cp .env.example .env   # edit .env with API credentials
make test-record-api-snapshot
make test-import-integration
```

### Daily development

```bash
make test-unit                  # fast feedback
make test-integration           # after DB/search changes
make test-all                   # full suite before commit
```

### Code coverage

```bash
make test-coverage-unit-integration   # Unit + Integration only, faster
# or
make test-coverage-all               # all suites including ImportIntegration
# open build/coverage/index.html
```

### After API or fixture changes

```bash
make test-record-api-snapshot
make test-import-integration
# or in one go:
make test-record-api-snapshot-then-import
```

### Reset environment

```bash
make test-docker-down
make test-docker-build
make test-all
```

---

## Docker Compose Services

The file `docker-compose.test.yml` defines:

| Service | Image / build | Purpose |
|---------|----------------|--------|
| **mysql** | `mysql:8.0` | Test database. Port 13306→3306. DB: `pressmind_test`, root/root. |
| **mongodb** | `mongo:7` | MongoDB for search index tests. Port 17017→27017. |
| **redis** | `redis:7-alpine` | Cache; required by app bootstrap. Port 16379→6379. |
| **opensearch** | `opensearchproject/opensearch:2.11.0` | OpenSearch for Integration suite. Port 19200→9200. Single-node, security disabled. |
| **unit** | Built from `docker/Dockerfile` | Runs Unit tests. Depends on Redis. |
| **integration** | Same image | Runs Integration suite. Depends on MySQL, MongoDB, OpenSearch, Redis. |
| **test** | Same image | Runs all suites. Same dependencies as Integration. |
| **import-test** | Same image | Runs ImportIntegration only. Depends on MySQL, MongoDB; loads `.env` for optional recording. |

The Dockerfile (`docker/Dockerfile`) installs PHP 8.2-cli, required extensions (e.g. pdo_mysql, gd, redis, mongodb, imagick), PCOV for coverage, and Composer; then runs `composer install` and adds dev dependencies (e.g. OpenSearch, MongoDB driver). Default entrypoint is `vendor/bin/phpunit`; commands in the Makefile or compose override the default CMD/entrypoint as needed.

---

## Troubleshooting

| Problem | Action |
|--------|--------|
| "container already exists" or similar | `make test-docker-cleanup` or `make test-docker-down` then re-run the target. |
| "No code coverage driver available" | `make test-docker-rebuild` to rebuild image with PCOV. |
| ImportIntegration fails with missing/empty fixtures | Run `make test-record-api-snapshot` (with valid `.env` credentials) or `make test-record-api-snapshot-then-import`. |
| Services not ready (MySQL/MongoDB/OpenSearch) | Compose uses healthchecks; wait for first run. Check with `docker compose -f docker-compose.test.yml ps` and `logs` for the service. |
| Need full PHPUnit output | Use `make test-all-verbose` or `make test-import-integration-verbose`. |

For more test-specific issues (e.g. fixtures, writing tests, CI), see the [Testing Guide – Troubleshooting](testing.md#troubleshooting) section.
