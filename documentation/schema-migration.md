# Schema Migration

## Overview

The SDK follows a single strategy during import: **add new columns, skip missing**. New API fields get database columns (and optional class update) so you can use new data immediately; fields that are missing or obsolete are skipped so the import and templates do not break.

## Strategy

- **New columns**: When the API sends new fields, columns are added (and the PHP class updated when `mode` is `auto`). Data is stored and available in templates right away.
- **Missing / obsolete**: No columns are dropped during import. When persisting, only columns that exist in the table are written. Obsolete fields are detected and logged only; optional cleanup (DROP COLUMN, class regeneration) is done via the Database Integrity Check CLI.

## How It Works

**When a new field is added in Pressmind:**

1. **Detection**: During import, the `SchemaMigrator` compares the API response fields with the local class definition
2. **Database Migration** (if `mode` is `auto`): Missing columns are added via `ALTER TABLE ADD COLUMN`
3. **Dynamic Properties**: New field values are stored in `_dynamic_properties` and persisted to the database
4. **Class Update**: The PHP class file is updated asynchronously for future requests (when `mode` is `auto`)

**When a field is removed in Pressmind:**

5. **Detection**: The migrator detects fields that exist in the local class but are no longer in the API response (obsolete)
6. **Logging**: Obsolete fields are logged when `log_changes` is true. **No columns are dropped during import**
7. **INSERT**: When saving object data, only columns that exist in the table are written. So if the class has more properties than the table (e.g. obsolete), the import does not break with "Unknown column"

**No restart required** for new fields – the import continues in the same request using dynamic properties.

## Configuration

Add the following to your `pm-config.php` under the `data` key:

```php
'data' => [
    // ... existing configuration ...

    'schema_migration' => [
        'mode' => 'log_only',   // 'log_only', 'auto', 'abort'
        'log_changes' => true, // Log all schema changes
    ],
],
```

### Modes

| Mode | Description |
|------|-------------|
| `log_only` (default) | Logs a warning about new/missing fields, ignores new fields for persistence, and continues the import. Obsolete fields are only logged; no columns are dropped. |
| `auto` | For **new** fields: adds missing database columns and updates the PHP class file so new data is available immediately. Obsolete fields are only logged; no DROP during import. |
| `abort` | Throws an exception when a **missing** (new) field is detected. Obsolete fields are only logged (no abort). |

Obsolete (removed in API) columns are never dropped during import. Use the Database Integrity Check CLI if you want to drop columns and regenerate the MediaType class.

### Logging

When `log_changes` is `true`, schema-related events are logged to the `schema_migration` log file:

- Detected missing fields
- Detected obsolete (removed in API) fields
- Added database columns
- Updated PHP class files
- Any errors during migration

## Example

### Default behavior (log_only mode)

```
[WARNING] SchemaMigrator: Schema mismatch for ObjectType 123 (log_only mode). Ignoring fields: new_field_default
```

The import continues, but the new field is not saved. Check the log file and run ObjectTypeScaffolder or enable `auto` mode.

### With abort mode

```
Exception: Schema mismatch for ObjectType 123. Missing fields: new_field_default.
Run ObjectTypeScaffolder or set schema_migration.mode to "auto".
```

### With auto mode (new field)

```
[INFO] SchemaMigrator: Detected 1 missing fields for ObjectType 123: new_field_default
[INFO] SchemaMigrator: Added column new_field_default (LONGTEXT) to objectdata_123
[INFO] SchemaMigrator: Updated PHP class file for ObjectType 123
[INFO] SchemaMigrator: Successfully added missing fields for ObjectType 123
```

### Obsolete fields (removed in API)

With `log_changes` enabled, obsolete fields are logged only. No columns are dropped during import:

```
[INFO] SchemaMigrator: Detected 2 obsolete (removed in API) fields for ObjectType 2445: sparvorteil_covid_19_stoerer_text, sparvorteil_covid_19_legende
```

The import continues; when saving object data, only columns that exist in the table are written, so "Unknown column" does not occur. Optional cleanup (drop columns, regenerate class) is done via the Database Integrity Check CLI.

## Affected Classes

- `Pressmind\System\SchemaMigrator` - Detects missing/obsolete fields; adds new columns when `mode` is `auto`; obsolete are only logged
- `Pressmind\ORM\Object\MediaType\AbstractMediaType` - Dynamic properties support; INSERT only uses existing table columns so import does not break when class has obsolete properties
- `Pressmind\Import\MediaObjectData` - Calls SchemaMigrator before import

## Breaking Changes

The default mode changed from implicit `abort` (exception on schema mismatch) to `log_only` (log warning, ignore new fields, continue import). This is less disruptive but means new fields are silently ignored until manually handled. Set `mode: 'abort'` explicitly if you want the previous strict behavior.

## Recommendations

- **Development**: Use `mode: 'auto'` for convenience - new fields are automatically added
- **Staging**: Use `mode: 'auto'` to test with full data including new fields
- **Production**: Default `mode: 'log_only'` is safe - monitor the log file for new fields and run ObjectTypeScaffolder periodically, or switch to `mode: 'auto'` for fully automatic handling

## Limitations

- **New fields** are added automatically when `mode` is `auto`
- **Removed/obsolete fields**: During import, obsolete fields are only logged; no columns are dropped. Use the Database Integrity Check CLI for optional cleanup
- Field **type changes** are not migrated (requires manual intervention)
- Relation-type fields (picture, objectlink, etc.) are handled but may require additional scaffolding for full functionality
