# Configuration: Logging

[← Back to Overview](configuration.md)

---

## Overview

The SDK's logging system supports two storage backends (filesystem and database) and provides fine-grained control over log levels, categories, retention periods, and database query logging.

```json
"logging": {
  "mode": ["ERROR"],
  "categories": ["ALL"],
  "storage": "database",
  "log_file_path": "APPLICATION_PATH/logs",
  "lifetime": 86400,
  "keep_log_types": ["ERROR"],
  "enable_advanced_object_log": false,
  "enable_database_query_logging": false,
  "database_query_log_file": "APPLICATION_PATH/logs/db_query_log.txt"
}
```

---

### `logging.mode`

| Property | Value |
|---|---|
| **Type** | `string` or `array` of strings |
| **Default** | `["ERROR"]` |
| **Required** | Yes |
| **Used in** | `Log\Writer.php` |

#### Description

Controls which log types are written. Only log entries whose type is included in this list will be stored.

#### Valid Values

| Value | Description |
|---|---|
| `"ALL"` | All log types are written |
| `"DEBUG"` | Debug messages (very detailed) |
| `"INFO"` | Informational messages |
| `"WARNING"` | Warnings (potential issues) |
| `"ERROR"` | Errors (handled problems) |
| `"FATAL"` | Fatal errors (processing aborted) |

#### Usage in Code

```php
// src/Pressmind/Log/Writer.php:35-36
$log_modes = is_array($config['logging']['mode']) 
    ? $config['logging']['mode'] 
    : [$config['logging']['mode']];

// Check for database storage (line 61):
if (in_array('ALL', $log_modes) || in_array($type, $log_modes)) {
    // Log entry is written
}
```

#### Examples

```json
// Only log errors (default, minimal)
"mode": ["ERROR"]

// Log errors and warnings
"mode": ["ERROR", "WARNING"]

// Log everything (debugging)
"mode": ["ALL"]

// Detailed logging for development
"mode": ["DEBUG", "INFO", "WARNING", "ERROR", "FATAL"]
```

> **Performance note:** `"ALL"` generates very many log entries, especially during import. Use only for debugging.

---

### `logging.categories`

| Property | Value |
|---|---|
| **Type** | `array` of strings |
| **Default** | `["ALL"]` |
| **Required** | No |
| **Used in** | `Log\Writer.php` (only with `storage: "database"`) |

#### Description

Filters log entries by category. Categories correspond to functional areas of the SDK (e.g., `import`, `touristic_data_import`).

#### Valid Values

| Value | Description |
|---|---|
| `"ALL"` | All categories are logged |
| `"import"` | General import |
| `"custom_import_hook"` | Custom import hooks |
| `"touristic_data_import"` | Touristic data import |
| `"agency_import"` | Agency import |
| `"my_content_class_map"` | MyContent mapping |
| Others | Any custom category names |

#### Fallback

If not set or not an array, it automatically defaults to `[$filename]` (the current category only) – meaning all log entries for that specific category are written.

#### Examples

```json
// Log everything (default)
"categories": ["ALL"]

// Only import-related logs
"categories": ["import", "touristic_data_import", "agency_import"]

// Only debug custom hooks
"categories": ["custom_import_hook", "my_content_class_map"]
```

---

### `logging.storage`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"database"` |
| **Required** | Yes |
| **Used in** | `Log\Writer.php`, `Log\Service.php` |

#### Description

Defines where log entries are stored.

#### Valid Values

| Value | Description | Table/Path |
|---|---|---|
| `"database"` | Logs in the database table `pmt2core_logs` | Columns: `id`, `date`, `type`, `text`, `category`, `trace` |
| `"filesystem"` | Logs as text files in the configured directory | Path from `log_file_path` |

#### Comparison of Storage Backends

| Feature | `database` | `filesystem` |
|---|---|---|
| Category filter | Yes | No (mode only) |
| Cleanup via `lifetime` | Yes | Yes (via Service) |
| `keep_log_types` | Yes | No |
| Performance | Slower (DB write) | Faster (file append) |
| Queryability | SQL queries | Read file |

#### Examples

```json
// Default: Log to database
"storage": "database"

// Alternative: Log to files
"storage": "filesystem"
```

---

### `logging.log_file_path`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"APPLICATION_PATH/logs"` |
| **Required** | Only with `storage: "filesystem"` |
| **Used in** | `Log\Writer.php` |

#### Description

Directory for log files when using filesystem storage. Supports the `APPLICATION_PATH` placeholder.

#### Usage in Code

```php
// src/Pressmind/Log/Writer.php:78-80
static function getLogFilePath() {
    $config = Registry::getInstance()->get('config');
    return isset($config['logging']['log_file_path']) 
        ? str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['logging']['log_file_path']) 
        : APPLICATION_PATH . DIRECTORY_SEPARATOR . 'logs';
}
```

The directory is automatically created if it does not exist (with permissions `0644`).

#### Log File Format

Log files are named by category: `{category}.log`

Each entry has the format:
```
[2025-01-15 14:30:00] Log message here
```

#### Fallback

If not set, `APPLICATION_PATH/logs` is used.

#### Examples

```json
// Default
"log_file_path": "APPLICATION_PATH/logs"

// Absolute path
"log_file_path": "/var/log/pressmind"

// Specific subdirectory
"log_file_path": "APPLICATION_PATH/storage/logs"
```

---

### `logging.lifetime`

| Property | Value |
|---|---|
| **Type** | `integer` (seconds) |
| **Default** | `86400` (24 hours) |
| **Required** | No |
| **Used in** | `Log\Service.php` |

#### Description

Determines how long log entries are retained (in seconds). Older entries are deleted during cleanup – unless their type is listed in `keep_log_types`.

#### Usage in Code

```php
// src/Pressmind/Log/Service.php:44-47
private function _cleanUpDatabase() {
    $date = new DateTime();
    $date->modify('-' . $this->_config['lifetime'] . ' seconds');
    // Delete all logs older than $date (except keep_log_types)
}
```

#### Common Values

| Value | Duration |
|---|---|
| `3600` | 1 hour |
| `86400` | 1 day (default) |
| `604800` | 1 week |
| `2592000` | 30 days |

#### Examples

```json
// 24 hours (default)
"lifetime": 86400

// 7 days
"lifetime": 604800

// 30 days
"lifetime": 2592000
```

---

### `logging.keep_log_types`

| Property | Value |
|---|---|
| **Type** | `array` of strings |
| **Default** | `["ERROR"]` |
| **Required** | No |
| **Used in** | `Log\Service.php` (only with `storage: "database"`) |

#### Description

Log types in this list are **not** deleted during cleanup, even if they are older than `lifetime`. This ensures that important error messages are not lost.

#### Usage in Code

```php
// src/Pressmind/Log/Service.php:51-53
if (is_array($this->_config['keep_log_types'])) {
    $filters['type'] = ['not in', implode(',', $this->_config['keep_log_types'])];
}
// Result: DELETE FROM pmt2core_logs WHERE date < ? AND type NOT IN ('ERROR')
```

#### Examples

```json
// Keep only errors (default)
"keep_log_types": ["ERROR"]

// Keep errors and fatal errors
"keep_log_types": ["ERROR", "FATAL"]

// Keep errors and warnings
"keep_log_types": ["ERROR", "WARNING", "FATAL"]
```

---

### `logging.enable_advanced_object_log`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `Search\MongoDB.php`, `Search\OpenSearch.php` |

#### Description

Enables detailed in-memory logging for MongoDB and OpenSearch search operations. Logs are collected in memory and can be retrieved via `getLog()`.

#### Usage in Code

```php
// src/Pressmind/Search/MongoDB.php:229-247
private function _addLog($text) {
    if (isset($config['logging']['enable_advanced_object_log']) 
        && $config['logging']['enable_advanced_object_log'] == true) {
        $now = new \DateTime();
        $this->_log[] = '[' . $now->format(DATE_RFC3339_EXTENDED) . '] ' . $text;
    }
}

public function getLog() {
    if ($config['logging']['enable_advanced_object_log'] == true) {
        return $this->_log;  // Array of all log entries
    }
    return ['Logging is disabled in config (logging.enable_advanced_object_log)'];
}
```

#### When to Use

- Diagnosing search issues
- Performance analysis of MongoDB/OpenSearch queries
- Debugging index generation

#### Examples

```json
// Disabled (default, production)
"enable_advanced_object_log": false

// Enabled (debugging)
"enable_advanced_object_log": true
```

> **Performance note:** Keep disabled in production, as logs are held in memory.

---

### `logging.enable_database_query_logging`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `DB\Adapter\Pdo.php` |

#### Description

Enables logging of **all** database queries including execution time to a text file. Useful for performance analysis and debugging slow queries.

#### Usage in Code

```php
// src/Pressmind/DB/Adapter/Pdo.php:81-99
$database_query_log_enabled = Registry::getInstance()->get('config')['logging']['enable_database_query_logging'] ?? false;

if ($database_query_log_enabled) {
    $debug_start_time = microtime(true);
}

// ... query is executed ...

if ($database_query_log_enabled) {
    $now = new \DateTime();
    $logfile = Registry::getInstance()->get('config')['logging']['database_query_log_file'] 
        ?? APPLICATION_PATH . '/logs/db_query_log.txt';
    $debug_end_time = microtime(true);
    file_put_contents(
        HelperFunctions::replaceConstantsFromConfig($logfile),
        $now->format(DATE_ISO8601) . ' - ' . ($debug_end_time - $debug_start_time) . ': ' . $query . "\n",
        FILE_APPEND
    );
}
```

Logged across all PDO adapter methods:
- `fetchAll()` – SELECT queries
- `insert()` – INSERT operations
- `update()` – UPDATE operations
- `delete()` – DELETE operations
- `truncate()` – TRUNCATE operations
- `batchInsert()` – Batch INSERT operations

#### Log Format

```
2025-01-15T14:30:00+0100 - 0.003456: SELECT * FROM pmt2core_media_objects WHERE id = 123
2025-01-15T14:30:00+0100 - 0.001234: UPDATE pmt2core_media_objects SET name = 'Test' WHERE id = 123
```

Format: `[ISO8601 timestamp] - [execution time in seconds]: [SQL query]`

#### Example

```json
// Disabled (default, production)
"enable_database_query_logging": false

// Enabled (performance debugging)
"enable_database_query_logging": true
```

> **Warning:** Generates very large log files during active imports. Only enable temporarily for debugging!

---

### `logging.database_query_log_file`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"APPLICATION_PATH/logs/db_query_log.txt"` |
| **Required** | Only with `enable_database_query_logging: true` |
| **Used in** | `DB\Adapter\Pdo.php` |

#### Description

Path to the log file for database query logging. Supports placeholder constants.

#### Fallback

If not set, `APPLICATION_PATH/logs/db_query_log.txt` is used.

#### Examples

```json
// Default
"database_query_log_file": "APPLICATION_PATH/logs/db_query_log.txt"

// Absolute path
"database_query_log_file": "/var/log/pressmind/queries.log"

// Separate directory
"database_query_log_file": "APPLICATION_PATH/logs/sql/queries.txt"
```

---

## Interplay of Logging Options

### Example: Minimal Logging (Production)

```json
"logging": {
  "mode": ["ERROR"],
  "categories": ["ALL"],
  "storage": "database",
  "lifetime": 604800,
  "keep_log_types": ["ERROR", "FATAL"],
  "enable_advanced_object_log": false,
  "enable_database_query_logging": false
}
```

### Example: Full Debugging

```json
"logging": {
  "mode": ["ALL"],
  "categories": ["ALL"],
  "storage": "database",
  "log_file_path": "APPLICATION_PATH/logs",
  "lifetime": 86400,
  "keep_log_types": ["ERROR", "FATAL"],
  "enable_advanced_object_log": true,
  "enable_database_query_logging": true,
  "database_query_log_file": "APPLICATION_PATH/logs/db_query_log.txt"
}
```

### Example: Filesystem Logging

```json
"logging": {
  "mode": ["ERROR", "WARNING"],
  "storage": "filesystem",
  "log_file_path": "/var/log/pressmind",
  "lifetime": 2592000,
  "enable_advanced_object_log": false,
  "enable_database_query_logging": false
}
```

---

[← Back to Overview](configuration.md) | [Next: Touristic Data & Import →](config-touristic-data.md)
