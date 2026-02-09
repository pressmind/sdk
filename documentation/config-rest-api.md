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

The REST server provides its own API through which external systems (e.g., the Travelshop) can query data from the local database.

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
| **Required** | No |
| **Used in** | – (reserved) |

#### Description

API key for server authentication. This value is defined in the config but is currently **not actively used** in the server authentication code. Authentication is handled exclusively via `api_user` and `api_password`.

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

```php
// src/Pressmind/REST/Server.php:86-88
if (!empty($config['rest']['server']['api_user']) 
    && !empty($config['rest']['server']['api_password'])) {
    // Basic Auth is ENABLED
    if ($auth = $this->_request->getParsedBasicAuth()) {
        if ($auth[0] == $config['rest']['server']['api_user'] 
            && $auth[1] == $config['rest']['server']['api_password']) {
            return true;  // Authentication successful
        }
    }
    return false;  // Authentication failed
}
// If user/password are empty: Authentication is DISABLED
return true;
```

**Important behavior:**
- If **both** values (`api_user` and `api_password`) are set and non-empty → Basic Auth is **enabled**
- If either or both values are empty → Authentication is **disabled** (API is open)

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
