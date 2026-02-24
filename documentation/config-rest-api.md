# Configuration: REST API & IBE

[← Back to Overview](configuration.md)

---

## REST Client (`rest.client`)

The REST client is used to fetch data from the Pressmind Webcore (API). It is the primary interface for data import.

```json
"rest": {
  "client": {
    "api_key": "",
    "api_user": "",
    "api_password": ""
  }
}
```

---

### `rest.client.api_key`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes (for import) |
| **Used in** | `REST\Client.php` |

#### Description

The API key for communication with the Pressmind Webcore. Used in the URL construction of REST requests.

#### Usage in Code

```php
// src/Pressmind/REST/Client.php:61
$this->_api_key = $config['rest']['client']['api_key'];

// URL structure: {api_endpoint}{api_key}/{controller}/{action}
```

The API key is sent as part of the URL to the Webcore and identifies the requesting installation.

#### Example

```json
"api_key": "abc123def456ghi789"
```

> **Note:** You receive the API key from Pressmind for your project.

---

### `rest.client.api_user`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes (for import) |
| **Used in** | `REST\Client.php` |

#### Description

Username for HTTP Basic Authentication when making requests to the Pressmind Webcore.

#### Usage in Code

```php
// src/Pressmind/REST/Client.php:94
curl_setopt($ch, CURLOPT_USERPWD, $this->_api_user . ":" . $this->_api_password);
```

#### Example

```json
"api_user": "import_user"
```

---

### `rest.client.api_password`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes (for import) |
| **Used in** | `REST\Client.php` |

#### Description

Password for HTTP Basic Authentication when making requests to the Pressmind Webcore.

#### Example

```json
"api_password": "s3cur3_api_p4ss"
```

---

## REST Server (`rest.server`)

The REST server provides its own API through which external systems (e.g., the Travelshop) can query data from the local database. For a full description of the login mechanism (which endpoints need which credentials, headers, and examples), see [REST API Endpoints – Authentication](rest-api-endpoints.md#authentication-login-mechanismus).

```json
"rest": {
  "server": {
    "api_endpoint": "/rest",
    "api_key": "",
    "api_user": "",
    "api_password": "",
    "controller": {
      "catalog": {
        "categories": [...]
      }
    }
  }
}
```

---

### `rest.server.api_endpoint`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"/rest"` |
| **Required** | No |
| **Used in** | Routing configuration |

#### Description

The base path for the REST server endpoint. Defines under which URL path the API is accessible.

#### Examples

```json
// Standard
"api_endpoint": "/rest"

// Versioned
"api_endpoint": "/api/v1"

// Under subdirectory
"api_endpoint": "/pressmind/api"
```

---

### `rest.server.api_key`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes for `/command/*` and `/redis/*` (together with Basic Auth); optional for other endpoints (enables API key auth) |
| **Used in** | `REST\Server.php` (`_checkAuthentication()`, `_extractApiKey()`) |

#### Description

API key for REST server authentication. When set, the server accepts **either** API key **or** Basic Auth for **general** endpoints (catalog, import, mediaObject, etc.).

**Command and Redis endpoints** (`/command/list`, `/command/stream`, `/redis/getKeys`, …) require **both** API key **and** Basic Auth: the request must send a valid API key (preferably in a header) and valid Basic Auth. If either is missing or invalid, the controller throws an exception (HTTP 500). All other REST endpoints are unchanged.

**Accepted formats (priority order):**

- **X-Api-Key header (recommended):** `X-Api-Key: <api_key>` — use for Command/Redis (where `Authorization` is used for Basic Auth) and for general endpoints; avoids API key in URLs and logs.
- **Authorization Bearer:** `Authorization: Bearer <api_key>` — for general endpoints when no Basic Auth is used.
- **Query parameter (fallback, insecure):** `?api_key=<api_key>` — not recommended (logs, referrer); only for legacy or when custom headers are not possible (e.g. plain `EventSource`).

Comparison is timing-safe (`hash_equals()`). If `api_key` is empty, only Basic Auth (or no auth when user/password are empty) applies for general endpoints; command and redis endpoints then throw when called.

#### Example (e.g. in Travelshop `.env`)

```bash
PM_REST_SERVER_API_KEY=your-secret-api-key
```

Config (e.g. `pm-config.php`):

```json
"api_key": ""
```

Typically filled via `getenv("PM_REST_SERVER_API_KEY")` so the secret stays in `.env`.

---

### `rest.server.api_user`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Recommended |
| **Used in** | `REST\Server.php` |

#### Description

Username for HTTP Basic Authentication of the REST server. Used together with `api_password`.

#### Authentication Logic

The server checks in order:

1. **API key** (if `rest.server.api_key` is set): from **X-Api-Key** header (recommended), then `Authorization: Bearer <key>`, then `?api_key=<key>` (fallback). If valid (timing-safe compare), request is authenticated.
2. **Basic Auth** (if `api_user` and `api_password` are set): from `Authorization: Basic ...`. If credentials match, request is authenticated.
3. If neither API key nor Basic Auth is configured → authentication is **disabled** (API is open).

**Important behavior:**
- If **both** values (`api_user` and `api_password`) are set and non-empty → Basic Auth is **enabled** (when API key is not used or not set).
- If either or both values are empty and no API key is set → Authentication is **disabled** (API is open).

#### Example

```json
"api_user": "travelshop_api"
```

> **Security recommendation:** Always set `api_user` and `api_password` in production environments to protect the API.

---

### `rest.server.api_password`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Recommended |
| **Used in** | `REST\Server.php` |

#### Description

Password for HTTP Basic Authentication of the REST server. See `api_user` for the authentication logic.

#### Example

```json
"api_password": "str0ng_s3rv3r_p4ss!"
```

---

### `rest.server.controller.catalog.categories`

| Property | Value |
|---|---|
| **Type** | `array` of objects |
| **Default** | `[]` |
| **Required** | No |
| **Used in** | `REST\Controller\Catalog.php` |

#### Description

Maps internal category variable names to custom display names. Used in the Catalog REST controller to personalize the API output.

#### Structure

Each element in the array has the following properties:

| Property | Type | Description |
|---|---|---|
| `var_name` | `string` | Internal variable name of the category (from Pressmind) |
| `title` | `string` | Display name for the API output |

#### Usage in Code

```php
// src/Pressmind/REST/Controller/Catalog.php:58-68
if (!empty($controller_config['categories'])) {
    foreach ($controller_config['categories'] as $configured_category) {
        if ($configured_category['var_name'] == $category_name) {
            $name = $configured_category['title'];  // Overrides the internal name
            $found = true;
        }
    }
}
```

If no mapping is configured for a category, the internal variable name is used.

#### Example

```json
"controller": {
  "catalog": {
    "categories": [
      {
        "var_name": "zielgebiet_default",
        "title": "Destination"
      },
      {
        "var_name": "reiseart_default",
        "title": "Travel Type"
      },
      {
        "var_name": "sterne_default",
        "title": "Hotel Category"
      },
      {
        "var_name": "thema_default",
        "title": "Travel Theme"
      }
    ]
  }
}
```

---

## Backend (for REST Command streaming)

The REST endpoints `command/stream` and `command/list` run CLI commands via the SDK Backend. They require the Backend CLI runner path.

### `backend.cli_runner`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | – |
| **Required** | Yes (for `command/stream`) |
| **Used in** | `REST\Controller\Command::stream()`, Backend `CommandController` |

#### Description

Path to the CLI runner script (e.g. `APPLICATION_PATH/cli/run.php`) that executes registered commands. Placeholders `APPLICATION_PATH` and `BASE_PATH` are replaced at runtime. If not set or invalid, `command/stream` returns an error and does not execute.

See [Backend](backend.md) for full Backend configuration.

#### Example

```json
"backend": {
  "enabled": true,
  "cli_runner": "APPLICATION_PATH/cli/run.php"
}
```

---

## IBE Configuration (`ib3`)

The IBE section (Internet Booking Engine) configures the connection to the Pressmind booking system.

```json
"ib3": {
  "endpoint": ""
}
```

---

### `ib3.endpoint`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes (for booking links) |
| **Used in** | `REST\Controller\Entrypoint.php`, `ORM\Object\MediaObject.php` |

#### Description

The base URL of the IBE3 booking system. Used for generating booking links.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject.php:2647
$ib3_endpoint = !empty($config['ib3']['endpoint']) 
    ? trim($config['ib3']['endpoint'], '/') 
    : '';

if (empty($ib3_endpoint)) {
    throw new Exception('No IB3 endpoint configured, see sdk config: ib3.endpoint');
}
```

**Important behavior:**
- Trailing slashes (`/`) are automatically removed
- If the value is empty and a booking link needs to be generated, an **Exception** is thrown

#### Examples

```json
// Standard IBE3 endpoint
"endpoint": "https://ibe.my-travel-site.com"

// With path
"endpoint": "https://www.my-travel-site.com/booking"

// Subdomain
"endpoint": "https://booking.my-travel-site.com"
```

> **Note:** The `imo` parameter (id_media_object) is automatically appended to the URL. No trailing slash needed.

---

## Complete Example

```json
{
  "development": {
    "rest": {
      "client": {
        "api_key": "abc123def456ghi789",
        "api_user": "import_user",
        "api_password": "s3cur3_api_p4ss"
      },
      "server": {
        "api_endpoint": "/rest",
        "api_key": "",
        "api_user": "travelshop_api",
        "api_password": "str0ng_s3rv3r_p4ss!",
        "controller": {
          "catalog": {
            "categories": [
              {
                "var_name": "zielgebiet_default",
                "title": "Destination"
              },
              {
                "var_name": "reiseart_default",
                "title": "Travel Type"
              }
            ]
          }
        }
      }
    },
    "ib3": {
      "endpoint": "https://ibe.my-travel-site.com"
    }
  }
}
```

---

[← Back to Overview](configuration.md) | [Next: Logging →](config-logging.md)
