# Configuration: Cache

[← Back to Overview](configuration.md) | [→ Configuration Examples & Best Practices](config-examples.md)

---

## Overview

The SDK's cache system uses Redis as its backend and enables caching of various data types – from REST responses and search results to individual objects.

> **Production Insight:** All ~40 checked production installations set `cache.enabled: false` in the config file and enable caching via deployment scripts or environment variables. ~60% use the full cache type set `[REST, SEARCH, SEARCH_FILTER, OBJECT, MONGODB]`. See [Configuration Examples](config-examples.md#cache-configuration) for details.

```json
"cache": {
  "enabled": false,
  "adapter": {
    "name": "Redis",
    "config": {
      "host": "127.0.0.1",
      "port": 6379,
      "connection_string": null
    }
  },
  "key_prefix": "DATABASE_NAME",
  "disable_parameter": {
    "key": "no_cache",
    "value": 1
  },
  "update_parameter": {
    "key": "update_cache",
    "value": 1
  },
  "types": ["REST", "SEARCH", "SEARCH_FILTER", "OBJECT", "MONGODB"],
  "update_frequency": 3600,
  "max_idle_time": 86400
}
```

---

### `cache.enabled`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | Yes |
| **Used in** | `REST\Server.php`, `Import.php`, `ORM\Object\AbstractObject.php`, `ORM\Object\MediaObject.php`, `Search\MongoDB.php` |

#### Description

Master switch for the entire cache system. Must be `true` for caching to be active.

#### Usage in Code

```php
// Typical pattern across all cache-using classes:
$cache_enabled = ($config['cache']['enabled'] == true 
    && in_array('TYPE', $config['cache']['types']));

if ($cache_enabled) {
    // Check / write cache
}
```

The cache is checked at the following locations:
- **REST Server:** Response caching for API requests
- **Import:** Cache invalidation after import
- **AbstractObject:** ORM object caching
- **MediaObject:** MediaObject-specific caching
- **MongoDB Search:** Search result caching

---

### `cache.adapter.name`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"Redis"` |
| **Required** | Yes (when enabled) |
| **Used in** | `Cache\Adapter\Factory.php` |

#### Description

Name of the cache adapter. Determines which adapter class is instantiated.

#### Usage in Code

```php
// src/Pressmind/Cache/Adapter/Factory.php:9-12
$class_name = '\Pressmind\Cache\Adapter\\' . $pAdapterName;
return new $class_name($pConfig);
```

#### Valid Values

| Value | Class | Description |
|---|---|---|
| `"Redis"` | `Pressmind\Cache\Adapter\Redis` | Redis server (default, recommended) |

> **Note:** Currently only the Redis adapter is provided.

---

### `cache.adapter.config.host`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"127.0.0.1"` |
| **Required** | Yes (when enabled) |
| **Used in** | `Cache\Adapter\Redis.php` |

#### Description

Hostname or IP address of the Redis server.

#### Examples

```json
// Local Redis
"host": "127.0.0.1"

// Docker Compose
"host": "redis"

// External server
"host": "redis.example.com"
```

---

### `cache.adapter.config.port`

| Property | Value |
|---|---|
| **Type** | `integer` |
| **Default** | `6379` |
| **Required** | Yes (when enabled) |
| **Used in** | `Cache\Adapter\Redis.php` |

#### Description

Port of the Redis server.

---

### `cache.adapter.config.connection_string`

| Property | Value |
|---|---|
| **Type** | `string` or `null` |
| **Default** | `null` |
| **Required** | No |
| **Used in** | – (reserved) |

#### Description

Reserved for future use. The connection string is currently **not** used – the connection is established via `host` and `port`.

---

### `cache.adapter.config.password` (optional)

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | – (not in the default config) |
| **Required** | No |
| **Used in** | `Cache\Adapter\Redis.php` |

#### Description

Redis password for authenticated connections. Only used when set and non-empty.

#### Usage in Code

```php
// src/Pressmind/Cache/Adapter/Redis.php:39-41
if (!empty($this->_config['adapter']['config']['password'])) {
    $this->_server->auth($this->_config['adapter']['config']['password']);
}
```

#### Example

```json
"config": {
  "host": "redis.example.com",
  "port": 6379,
  "password": "r3d1s_p4ss!"
}
```

---

### `cache.key_prefix`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"DATABASE_NAME"` |
| **Required** | No |
| **Used in** | `Cache\Adapter\Redis.php` |

#### Description

Prefix for all cache keys. Enables separation of cache data from multiple projects on the same Redis server. The placeholder `DATABASE_NAME` is replaced with the value from `database.dbname`.

#### Usage in Code

```php
// src/Pressmind/Cache/Adapter/Redis.php:43
$this->_prefix = HelperFunctions::replaceConstantsFromConfig($this->_config['key_prefix']);

// Resulting key: 'pm:{prefix}:{key}'
```

#### Key Format

All cache keys follow the format: `pm:{key_prefix}:{cache_key}`

Example: `pm:pressmind_production:REST_catalog_categories`

#### Examples

```json
// Default: Database name as prefix
"key_prefix": "DATABASE_NAME"

// Custom
"key_prefix": "my_project"

// Environment-based
"key_prefix": "pressmind_staging"
```

---

### `cache.disable_parameter`

```json
"disable_parameter": {
  "key": "no_cache",
  "value": 1
}
```

| Property | Value |
|---|---|
| **Type** | object with `key` and `value` |
| **Default** | `{"key": "no_cache", "value": 1}` |
| **Used in** | `REST\Server.php` |

#### Description

HTTP query parameter that can disable caching for a single request.

#### Usage in Code

```php
// src/Pressmind/REST/Server.php:77
$this->_cache_enabled = (
    $config['cache']['enabled'] == true 
    && in_array('REST', $config['cache']['types']) 
    && $this->_request->getParameter($config['cache']['disable_parameter']['key']) 
        != $config['cache']['disable_parameter']['value']
);
```

#### Example Request

```
GET /rest/catalog/categories?no_cache=1
```

This request bypasses the cache and delivers fresh data.

---

### `cache.update_parameter`

```json
"update_parameter": {
  "key": "update_cache",
  "value": 1
}
```

| Property | Value |
|---|---|
| **Type** | object with `key` and `value` |
| **Default** | `{"key": "update_cache", "value": 1}` |
| **Used in** | `REST\Server.php` |

#### Description

HTTP query parameter that forces a cache update for a single request. The response is computed and the cache is overwritten with the new result.

#### Difference from `disable_parameter`

| Parameter | Behavior |
|---|---|
| `no_cache=1` | Cache is completely ignored (neither read nor written) |
| `update_cache=1` | Cache is updated (result computed and written to cache) |

#### Example Request

```
GET /rest/catalog/categories?update_cache=1
```

---

### `cache.types`

| Property | Value |
|---|---|
| **Type** | `array` of strings |
| **Default** | `["REST", "SEARCH", "SEARCH_FILTER", "OBJECT", "MONGODB"]` |
| **Required** | Yes (when enabled) |
| **Used in** | Various classes |

#### Description

Defines which cache types are active. Each type is checked by a specific component of the SDK.

#### Valid Values

| Type | Description | Used in |
|---|---|---|
| `"REST"` | REST API response caching | `REST\Server.php` |
| `"SEARCH"` | Search result caching | `Search\*.php` |
| `"SEARCH_FILTER"` | Search filter caching | `Search\Filter\*.php` |
| `"OBJECT"` | ORM object caching | `ORM\Object\AbstractObject.php` |
| `"MONGODB"` | MongoDB search caching | `Search\MongoDB.php` |
| `"OPENSEARCH"` | OpenSearch caching | `Search\OpenSearch.php` |
| `"QUERY"` | Database query caching | `DB\Adapter\Pdo.php` |
| `"URL"` | URL caching | Routing components |
| `"RENDERER"` | Template rendering caching | View components |

#### Check in Code

```php
// Typical pattern:
if ($config['cache']['enabled'] == true 
    && in_array('MONGODB', $config['cache']['types'])) {
    // MongoDB caching is active
}
```

#### Examples

```json
// Full caching (production)
"types": ["REST", "SEARCH", "SEARCH_FILTER", "OBJECT", "MONGODB", "OPENSEARCH"]

// REST caching only
"types": ["REST"]

// Without object caching (for frequently changing data)
"types": ["REST", "SEARCH", "SEARCH_FILTER", "MONGODB"]
```

---

### `cache.update_frequency`

| Property | Value |
|---|---|
| **Type** | `integer` (seconds) |
| **Default** | `3600` (1 hour) |
| **Used in** | `Cache\Adapter\Redis.php` |

#### Description

Time interval (in seconds) after which a cache entry is considered "too old" and is updated at the next opportunity – but only if it is still actively used (idle time < max_idle_time).

#### Usage in Code

```php
// src/Pressmind/Cache/Adapter/Redis.php:134
if ($age >= $this->_config['update_frequency'] 
    && $idle_time < $this->_config['max_idle_time']) {
    // Cache entry should be updated
}
```

#### Interplay with `max_idle_time`

| Condition | Behavior |
|---|---|
| `age < update_frequency` | Cache is used directly |
| `age >= update_frequency` AND `idle_time < max_idle_time` | Cache is updated |
| `idle_time >= max_idle_time` | Cache entry is deleted |

#### Examples

```json
// Hourly updates (default)
"update_frequency": 3600

// Every 15 minutes
"update_frequency": 900

// Every 6 hours
"update_frequency": 21600
```

---

### `cache.max_idle_time`

| Property | Value |
|---|---|
| **Type** | `integer` (seconds) |
| **Default** | `86400` (24 hours) |
| **Used in** | `Cache\Adapter\Redis.php` |

#### Description

Maximum idle time (TTL) of a cache entry in seconds. Set as the Redis TTL. Cache entries that have not been accessed for longer than this period are automatically deleted by Redis.

#### Examples

```json
// 24 hours (default)
"max_idle_time": 86400

// 1 hour (frequently changing data)
"max_idle_time": 3600

// 7 days (static data)
"max_idle_time": 604800
```

---

## Complete Example Configurations

### Minimal Configuration (Development)

```json
"cache": {
  "enabled": false
}
```

### Standard Configuration (Production)

```json
"cache": {
  "enabled": true,
  "adapter": {
    "name": "Redis",
    "config": {
      "host": "127.0.0.1",
      "port": 6379
    }
  },
  "key_prefix": "DATABASE_NAME",
  "disable_parameter": {"key": "no_cache", "value": 1},
  "update_parameter": {"key": "update_cache", "value": 1},
  "types": ["REST", "SEARCH", "SEARCH_FILTER", "OBJECT", "MONGODB"],
  "update_frequency": 3600,
  "max_idle_time": 86400
}
```

### High-Performance Configuration

```json
"cache": {
  "enabled": true,
  "adapter": {
    "name": "Redis",
    "config": {
      "host": "redis.internal",
      "port": 6379,
      "password": "r3d1s_s3cur3"
    }
  },
  "key_prefix": "prod_travelsite",
  "disable_parameter": {"key": "no_cache", "value": 1},
  "update_parameter": {"key": "update_cache", "value": 1},
  "types": ["REST", "SEARCH", "SEARCH_FILTER", "OBJECT", "MONGODB", "OPENSEARCH"],
  "update_frequency": 900,
  "max_idle_time": 43200
}
```

---

[← Back to Overview](configuration.md) | [Next: Image & File Handling →](config-image-file-handling.md)
