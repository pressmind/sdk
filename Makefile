# SDK Test Makefile – all targets prefixed for clarity (test-* / test-docker-*).
# See documentation/makefile.md for full reference.
.PHONY: test-docker-cleanup test-docker-build test-docker-rebuild test-docker-down \
	test-unit test-integration test-import-integration test-all \
	test-record-api-snapshot test-record-api-snapshot-then-import \
	test-coverage-all test-coverage-unit-integration \
	test-all-verbose test-import-integration-verbose

# --- Docker lifecycle (run first to avoid "container already exists") ---
test-docker-cleanup:
	@docker ps -a --filter "name=sdk-test-run" --filter "name=sdk-unit-run" --filter "name=sdk-integration-run" --filter "name=sdk-import-test-run" -q 2>/dev/null | xargs -r docker rm -f >/dev/null 2>&1 || true

test-docker-build:
	docker compose -f docker-compose.test.yml build

# Force full rebuild (e.g. after adding PCOV). Prunes unused Docker resources first.
test-docker-rebuild:
	docker system prune -af --volumes
	docker compose -f docker-compose.test.yml build --no-cache

test-docker-down: test-docker-cleanup
	docker compose -f docker-compose.test.yml down -v --remove-orphans

# --- Test suites (each runs in Docker after cleanup) ---
test-unit: test-docker-cleanup
	docker compose -f docker-compose.test.yml run --rm unit

test-integration: test-docker-cleanup
	docker compose -f docker-compose.test.yml run --rm integration

test-import-integration: test-docker-cleanup
	docker compose -f docker-compose.test.yml run --rm import-test

test-all: test-docker-cleanup
	docker compose -f docker-compose.test.yml run --rm test

# --- API snapshot: record live Pressmind API responses to tests/Fixtures/api/ ---
test-record-api-snapshot: test-docker-cleanup
	@echo ""
	@echo "═══════════════════════════════════════════════════════════════"
	@echo "  Recording API Snapshot (live API → tests/Fixtures/api/)"
	@echo "═══════════════════════════════════════════════════════════════"
	@echo ""
	docker compose -f docker-compose.test.yml run --rm --entrypoint php -v "$$(pwd):/app" import-test tests/bin/record-api-snapshot.php

# Record snapshot, rebuild image, then run ImportIntegration suite
test-record-api-snapshot-then-import: test-record-api-snapshot test-docker-build
	@echo ""
	@echo "═══════════════════════════════════════════════════════════════"
	@echo "  Running ImportIntegration Tests"
	@echo "═══════════════════════════════════════════════════════════════"
	@echo ""
	docker compose -f docker-compose.test.yml run --rm import-test

# --- Code coverage (PCOV; output in build/coverage.txt and build/coverage/) ---
test-coverage-all: test-docker-cleanup
	@mkdir -p build
	docker compose -f docker-compose.test.yml run --rm --entrypoint vendor/bin/phpunit -v "$$(pwd)/build:/app/build" test -d memory_limit=512M --testdox --coverage-text --colors=always --display-skipped --display-incomplete
	@echo ""
	@echo "Text report: build/coverage.txt"
	@echo "HTML report: build/coverage/index.html"

# Unit + Integration only (no ImportIntegration); faster
test-coverage-unit-integration: test-docker-cleanup
	@mkdir -p build
	docker compose -f docker-compose.test.yml run --rm --entrypoint vendor/bin/phpunit -v "$$(pwd)/build:/app/build" test --testsuite "Unit,Integration" -d memory_limit=512M --testdox --coverage-text --colors=always --display-skipped --display-incomplete
	@echo ""
	@echo "Text report: build/coverage.txt"
	@echo "HTML report: build/coverage/index.html"

# --- Verbose (full PHPUnit output for debugging) ---
test-all-verbose: test-docker-cleanup
	docker compose -f docker-compose.test.yml run --rm --entrypoint vendor/bin/phpunit test --colors=always --display-skipped --display-incomplete

test-import-integration-verbose: test-docker-cleanup
	docker compose -f docker-compose.test.yml run --rm --entrypoint vendor/bin/phpunit import-test --testsuite ImportIntegration --colors=always --display-skipped --display-incomplete
