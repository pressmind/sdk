# Schema Migration

## Overview

The SDK now supports automatic schema migration when new fields are added to Object Types (MediaTypes) in Pressmind. This eliminates the need for manual scaffolding when Pressmind adds new fields.

## How It Works

When a new field is added in Pressmind:

1. **Detection**: During import, the `SchemaMigrator` compares the API response fields with the local class definition
2. **Database Migration**: Missing columns are added via `ALTER TABLE ADD COLUMN`
3. **Dynamic Properties**: New field values are stored in `_dynamic_properties` and persisted to the database
4. **Class Update**: The PHP class file is updated asynchronously for future requests

**No restart required** - the import continues in the same request using dynamic properties.

## Configuration

Add the following to your `pm-config.php` under the `data` key:

```php
'data' => [
    // ... existing configuration ...
    
    'schema_migration' => [
        'mode' => 'log_only',   // 'log_only', 'auto', 'abort'
        'log_changes' => true,  // Log all schema changes
    ],
],
```

### Modes

| Mode | Description |
|------|-------------|
| `log_only` (default) | Logs a warning about the schema mismatch, ignores new fields, and continues the import with known fields. No data loss for existing fields. |
| `auto` | Automatically adds missing database columns and updates the PHP class file. The import continues without interruption. All data including new fields is saved. |
| `abort` | Throws an exception when a schema mismatch is detected. Requires manual scaffolding to continue. |

### Logging

When `log_changes` is `true`, all schema changes are logged to the `schema_migration` log file:

- Detected missing fields
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

### With auto mode

```
[INFO] SchemaMigrator: Detected 1 missing fields for ObjectType 123: new_field_default
[INFO] SchemaMigrator: Added column new_field_default (LONGTEXT) to objectdata_123
[INFO] SchemaMigrator: Updated PHP class file for ObjectType 123
[INFO] SchemaMigrator: Successfully migrated schema for ObjectType 123
```

## Affected Classes

- `Pressmind\System\SchemaMigrator` - Main migration logic
- `Pressmind\ORM\Object\MediaType\AbstractMediaType` - Extended with dynamic properties support
- `Pressmind\Import\MediaObjectData` - Calls SchemaMigrator before import

## Breaking Changes

The default mode changed from implicit `abort` (exception on schema mismatch) to `log_only` (log warning, ignore new fields, continue import). This is less disruptive but means new fields are silently ignored until manually handled. Set `mode: 'abort'` explicitly if you want the previous strict behavior.

## Recommendations

- **Development**: Use `mode: 'auto'` for convenience - new fields are automatically added
- **Staging**: Use `mode: 'auto'` to test with full data including new fields
- **Production**: Default `mode: 'log_only'` is safe - monitor the log file for new fields and run ObjectTypeScaffolder periodically, or switch to `mode: 'auto'` for fully automatic handling

## Limitations

- Only **new fields** are added automatically
- Field **type changes** are not migrated (requires manual intervention)
- Field **deletions** are not performed automatically
- Relation-type fields (picture, objectlink, etc.) are handled but may require additional scaffolding for full functionality
