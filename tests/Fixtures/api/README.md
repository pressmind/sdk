# API Fixtures for ImportIntegration Tests

This directory holds recorded Pressmind API responses for offline replay.

**Do not commit fixture JSON files.** They are generated locally and may contain customer/content data from the live API. Fixtures are local-only; they are not provided by the repository or CI.

**When the Pressmind API version changes** (see `Client::WEBCORE_API_VERSION` in the SDK), the snapshot must be re-recorded. The ImportIntegration tests will fail with a clear version-mismatch message until you run `php tests/bin/record-api-snapshot.php` again.

## Recording (one-time)

Run from SDK project root. Credentials are read from `.env` (copy `.env.example` to `.env`, fill in values, never commit `.env`) or from the environment:

```bash
cp .env.example .env
# Edit .env: PM_API_KEY=... PM_API_USER=... PM_API_PASSWORD=...
php tests/bin/record-api-snapshot.php
```

Or pass credentials via environment / config file:

```bash
PM_API_KEY=... PM_API_USER=... PM_API_PASSWORD=... php tests/bin/record-api-snapshot.php
php tests/bin/record-api-snapshot.php --config=path/to/pm-config.php
```

This creates:

- `ObjectType_getAll_*.json`, `ObjectType_getById_*.json`
- `Text_search_*.json`, `Text_getById_*.json`
- `snapshot_meta.json` (recording_date, api_version – tests require api_version to match the SDK)

## Replay

`Pressmind\REST\ReplayClient` loads these files and applies a date offset (`NOW - recording_date`) so tests remain valid over time.

If this directory is empty (no fixture JSON files besides `snapshot_meta.json`), run the recording script first; otherwise ImportIntegration tests will fail with "Replay fixture not found".
