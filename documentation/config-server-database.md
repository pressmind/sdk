# Configuration: Server & Database

[← Back to Overview](configuration.md)

---

## Server Configuration (`server`)

The server section defines fundamental environment settings such as the document root, base HTTP URL, and the path to the PHP CLI binary.

```json
"server": {
  "document_root": "BASE_PATH/httpdocs",
  "webserver_http": "http://127.0.0.1",
  "php_cli_binary": "php",
  "timezone": "Europe/Berlin"
}
```

---

### `server.document_root`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"BASE_PATH/httpdocs"` |
| **Required** | Yes |
| **Used in** | `HelperFunctions::replaceConstantsFromConfig()` |

#### Description

Defines the webserver document root. This value is made available as the placeholder `WEBSERVER_DOCUMENT_ROOT` in all other config values and is automatically replaced at runtime.

#### Usage in Code

The function `HelperFunctions::replaceConstantsFromConfig()` replaces the placeholder `WEBSERVER_DOCUMENT_ROOT` in all path config values with the value configured here (after resolving `BASE_PATH` first).

Config values affected by `WEBSERVER_DOCUMENT_ROOT`:
- `docs_dir`
- `image_handling.storage.bucket`
- `file_handling.storage.bucket`

#### Examples

```json
// Standard setup with webroot relative to project directory
"document_root": "BASE_PATH/httpdocs"

// Absolute path
"document_root": "/var/www/html"

// Docker setup
"document_root": "/app/public"
```

---

### `server.webserver_http`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"http://127.0.0.1"` |
| **Required** | Yes |
| **Used in** | `HelperFunctions::replaceConstantsFromConfig()`, `REST\Controller\Ibe` |

#### Description

The base HTTP URL of the webserver. Made available as the placeholder `WEBSERVER_HTTP` in config values and serves as the foundation for generating asset URLs (images, files).

#### Usage in Code

- Used in `HelperFunctions::replaceConstantsFromConfig()` as a replacement for the `WEBSERVER_HTTP` placeholder.
- Used in `REST\Controller\Ibe` for generating image URIs.
- Automatically inserted into `image_handling.http_src` and `file_handling.http_src`.

#### Examples

```json
// Local development
"webserver_http": "http://127.0.0.1"

// Local development with port
"webserver_http": "http://localhost:8080"

// Production with HTTPS
"webserver_http": "https://www.my-travel-site.com"

// Subdomain
"webserver_http": "https://api.my-travel-site.com"
```

> **Note:** Do not use a trailing slash (`/`).

---

### `server.php_cli_binary`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"php"` |
| **Required** | No |
| **Used in** | `Import.php`, `System\EnvironmentValidation` |

#### Description

Path to the PHP CLI binary. Used for executing post-import scripts and other CLI operations.

#### Usage in Code

**In `Import.php`** (line 1069):
```php
$php_binary = isset($config['server']['php_cli_binary']) 
    && !empty($config['server']['php_cli_binary']) 
    ? $config['server']['php_cli_binary'] 
    : 'php';
```

**Validation in `EnvironmentValidation`:**
- Checks whether the path exists and is executable
- Resolves relative paths via the `PATH` environment variable
- Method: `EnvironmentValidation::validatePhpCliBinary()`

#### Fallback

If not set or empty, `'php'` is used as the default (expects PHP in the system PATH).

#### Examples

```json
// System PHP (in PATH)
"php_cli_binary": "php"

// Specific PHP version
"php_cli_binary": "/usr/bin/php8.2"

// Homebrew on macOS
"php_cli_binary": "/opt/homebrew/bin/php"

// Docker environment
"php_cli_binary": "/usr/local/bin/php"
```

---

### `server.timezone`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"Europe/Berlin"` |
| **Required** | No |
| **Used in** | Bootstrap/Initialization |

#### Description

Defines the timezone for the application. Typically used for `date_default_timezone_set()` or `DateTimeZone` initialization.

#### Valid Values

All valid [PHP timezones](https://www.php.net/manual/en/timezones.php), e.g.:
- `"Europe/Berlin"`
- `"Europe/Vienna"`
- `"Europe/Zurich"`
- `"UTC"`
- `"America/New_York"`

#### Examples

```json
// Germany
"timezone": "Europe/Berlin"

// Austria
"timezone": "Europe/Vienna"

// UTC for server environments
"timezone": "UTC"
```

---

## Database Configuration (`database`)

The database section configures the connection to the MySQL/MariaDB database that the SDK uses for storing all data.

```json
"database": {
  "username": "",
  "password": "",
  "host": "127.0.0.1",
  "port": "3306",
  "dbname": "",
  "engine": "MySQL"
}
```

---

### `database.username`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes |
| **Used in** | `DB\Adapter\Pdo` |

#### Description

Username for the database connection. Passed directly to the PDO constructor.

#### Usage in Code

```php
// src/Pressmind/DB/Adapter/Pdo.php:44
$this->databaseConnection = new \PDO(
    'mysql:host=' . $config->host . ';port=' . $config->port . ';dbname=' . $config->dbname . ';charset=utf8',
    $config->username,  // ← used here
    $config->password
);
```

#### Example

```json
"username": "pressmind_user"
```

---

### `database.password`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes |
| **Used in** | `DB\Adapter\Pdo` |

#### Description

Password for the database connection. Passed along with `username` to the PDO constructor.

> **Security Note:** The password is stored in plain text in the config file. Ensure that the `config.json` is not publicly accessible and is not checked into version control.

#### Example

```json
"password": "s3cur3_p4ssw0rd!"
```

---

### `database.host`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"127.0.0.1"` |
| **Required** | Yes |
| **Used in** | `DB\Adapter\Pdo` |

#### Description

Hostname or IP address of the database server. Used in the PDO DSN: `mysql:host={host}`.

#### Examples

```json
// Local database
"host": "127.0.0.1"

// Docker Compose service
"host": "mysql"

// External server
"host": "db.example.com"

// Unix socket (via socket path instead of host)
"host": "localhost"
```

---

### `database.port`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"3306"` |
| **Required** | Yes |
| **Used in** | `DB\Adapter\Pdo` |

#### Description

Port of the database server. The standard MySQL port is `3306`. Used in the PDO DSN: `mysql:host={host};port={port}`.

#### Examples

```json
// Standard MySQL
"port": "3306"

// Alternative port
"port": "3307"

// MariaDB on non-standard port
"port": "3308"
```

---

### `database.dbname`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes |
| **Used in** | `DB\Adapter\Pdo`, `HelperFunctions::replaceConstantsFromConfig()` |

#### Description

Name of the database. Used in two places:

1. **PDO DSN:** `mysql:host={host};port={port};dbname={dbname}`
2. **Placeholder `DATABASE_NAME`:** Used in `HelperFunctions::replaceConstantsFromConfig()` as a replacement value, e.g., in the cache `key_prefix`.

#### Example

```json
"dbname": "pressmind_sdk"
```

---

### `database.engine`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"MySQL"` |
| **Required** | Yes |
| **Used in** | `Search.php` |

#### Description

Defines the database engine. Used in the search component to generate engine-specific SQL syntax.

#### Valid Values

| Value | Description |
|---|---|
| `"MySQL"` | MySQL / MariaDB (default, recommended) |

#### Usage in Code

```php
// src/Pressmind/Search.php:374
$db_engine = $config['database']['engine'];
```

#### Example

```json
"engine": "MySQL"
```

> **Note:** Currently only MySQL/MariaDB is fully supported.

---

## Complete Example

```json
{
  "development": {
    "server": {
      "document_root": "/var/www/travelsite/httpdocs",
      "webserver_http": "https://www.my-travel-site.com",
      "php_cli_binary": "/usr/bin/php8.2",
      "timezone": "Europe/Berlin"
    },
    "database": {
      "username": "pressmind_user",
      "password": "s3cur3_p4ssw0rd!",
      "host": "127.0.0.1",
      "port": "3306",
      "dbname": "pressmind_production",
      "engine": "MySQL"
    }
  }
}
```

---

[← Back to Overview](configuration.md) | [Next: REST API →](config-rest-api.md)
