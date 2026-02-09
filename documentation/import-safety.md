# Import Safety Configuration

## Overview

The import process includes safety measures to prevent accidental data loss when the API fails, returns unexpected results, or when the number of objects to delete would be disproportionately high.

## Configuration

Add the following under the `data` key in your config (e.g. `pm-config.php`):

```php
'data' => [
    // ... existing configuration ...

    'import' => [
        // Maximum allowed ratio of objects to delete as orphans (0.0 to 1.0).
        // If (orphans to delete / total in DB) exceeds this, orphan removal is aborted.
        'max_orphan_delete_ratio' => 0.5,

        // When true, the ratio check is skipped and orphan removal always runs.
        // Use only when you intentionally expect a large change (e.g. full re-sync).
        'force_orphan_removal' => false,
    ],
],
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `max_orphan_delete_ratio` | float | `0.5` | Maximum fraction of existing media objects that may be deleted as orphans in one run. E.g. `0.5` = 50%. If the computed ratio is higher (e.g. API returned far fewer IDs than expected), orphan removal is aborted and an error is logged. |
| `force_orphan_removal` | bool | `false` | When `true`, the ratio check is disabled and orphan removal always runs. Set to `true` only when a large deletion is intended (e.g. after removing many objects in Pressmind). |

## Safety Behaviour (No Config Required)

- **API failure**: If ID retrieval from the API fails (exception or non-200), orphan removal is skipped entirely. No objects are deleted.
- **Empty import list**: If no objects were imported in this run (e.g. API returned no results), orphan removal is skipped. No objects are deleted.
- **Transaction rollback**: Each media object import runs inside a database transaction. If any step fails (including custom import hooks), the transaction is rolled back and the object is left unchanged. Rollbacks are logged with full exception details.

## Logging

- Skipped orphan removal (API not successful or empty list) is logged with `TYPE_ERROR` to the `import` log.
- Aborted orphan removal due to ratio threshold is logged with the computed ratio and the threshold.
- Transaction rollbacks are logged with message, file, line and stack trace to the `import` log and added to the import errors array.
