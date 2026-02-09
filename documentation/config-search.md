# Configuration: Search (MongoDB, OpenSearch, Search Hooks)

[← Back to Overview](configuration.md) | [→ Configuration Examples & Best Practices](config-examples.md)

---

## Overview

The SDK supports multiple search backends that can be operated in parallel or individually:

- **MongoDB** – Full-featured search with touristic data, categories, and descriptions
- **OpenSearch** – Full-text search with boost weighting
- **Search Hooks** – Extensible search providers for external APIs

> **See also:** [Configuration Examples & Best Practices](config-examples.md#mongodb-search-configuration) for real-world MongoDB search configurations from ~40 production installations.

---

## MongoDB Search (`data.search_mongodb`)

```json
"search_mongodb": {
  "enabled": false,
  "database": {
    "uri": "mongodb+srv://",
    "db": ""
  },
  "search": { ... },
  "calendar": { ... }
}
```

---

### `data.search_mongodb.enabled`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | Yes |
| **Used in** | `Import.php`, `ORM\Object\MediaObject.php`, `Search\MongoDB\AbstractIndex.php` |

#### Description

Master switch for the MongoDB search integration. When enabled, search documents are indexed in MongoDB and the MongoDB search becomes available.

#### Effects When Enabled

- During import, MongoDB documents are automatically created/updated
- The `Search\MongoDB` class becomes available for search queries
- Requires a running MongoDB instance

#### Example

```json
"enabled": true
```

---

### `data.search_mongodb.database.uri`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"mongodb+srv://"` |
| **Required** | Yes (when enabled) |
| **Used in** | `Search\MongoDB.php`, `ORM\Object\MediaObject.php` |

#### Description

MongoDB connection URI. Supports all MongoDB URI formats.

#### Examples

```json
// MongoDB Atlas (Cloud)
"uri": "mongodb+srv://user:password@cluster.mongodb.net"

// Local MongoDB
"uri": "mongodb://127.0.0.1:27017"

// Docker Compose
"uri": "mongodb://mongodb:27017"

// With authentication
"uri": "mongodb://user:password@host:27017/dbname?authSource=admin"

// Replica Set
"uri": "mongodb://host1:27017,host2:27017,host3:27017/dbname?replicaSet=rs0"
```

---

### `data.search_mongodb.database.db`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `""` |
| **Required** | Yes (when enabled) |
| **Used in** | `Search\MongoDB.php`, `ORM\Object\MediaObject.php` |

#### Description

Name of the MongoDB database.

#### Example

```json
"db": "pressmind_search"
```

---

### `data.search_mongodb.search.build_for`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Yes (when enabled) |
| **Used in** | `Search\MongoDB\AbstractIndex.php` |

#### Description

Defines for which object types, languages, and origins MongoDB collections are created. Each entry produces its own MongoDB collection.

#### Structure

```json
"build_for": {
  "{object_type_id}": [
    {
      "language": "de",
      "origin": 0,
      "disable_language_prefix_in_url": false
    }
  ]
}
```

| Property | Type | Description |
|---|---|---|
| `language` | `string` | Language code for this collection |
| `origin` | `integer` | Origin ID (source market) |
| `disable_language_prefix_in_url` | `boolean` | If `true`, the language prefix is omitted in URLs |

#### Usage in Code

```php
// src/Pressmind/Search/MongoDB/AbstractIndex.php:134
foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
    foreach ($build_infos as $build_info) {
        $collection_name = $this->getCollectionName(
            $build_info['origin'], 
            $build_info['language'], 
            $agency
        );
    }
}
```

#### Example

```json
"build_for": {
  "123": [
    {
      "language": "de",
      "origin": 0,
      "disable_language_prefix_in_url": false
    },
    {
      "language": "en",
      "origin": 0,
      "disable_language_prefix_in_url": false
    }
  ],
  "456": [
    {
      "language": "de",
      "origin": 0,
      "disable_language_prefix_in_url": true
    }
  ]
}
```

---

### `data.search_mongodb.search.code_delimiter`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `","` |
| **Required** | No |

#### Description

Delimiter for code values in the search index. Used when codes are stored as comma-separated lists.

---

### `data.search_mongodb.search.groups`

| Property | Value |
|---|---|
| **Type** | `array` of objects |
| **Default** | `[]` |
| **Required** | No |

#### Description

Grouping configurations for MongoDB documents. Each group element maps a group ID to a field and an optional filter.

#### Structure

```json
"groups": [
  {
    "{group_id}": {
      "field": "agencies",
      "filter": null
    }
  }
]
```

| Property | Type | Description |
|---|---|---|
| `field` | `string` | Data field for grouping |
| `filter` | `string` or `null` | PHP callable for data transformation (e.g., `"\\Custom\\Filter::treeToGroup"`) |

#### Example

```json
"groups": [
  {"100": {"field": "agencies", "filter": null}},
  {"101": {"field": "id_pool", "filter": null}},
  {"102": {"field": "brand", "filter": null}},
  {"103": {"field": "website_ausgabe_default", "filter": "\\Custom\\Filter::treeToGroup"}}
]
```

---

### `data.search_mongodb.search.categories`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |

#### Description

Defines which category fields per object type are included in the MongoDB index.

#### Structure

```json
"categories": {
  "{object_type_id}": {
    "{category_var_name}": null,
    "{category_var_name}": {
      "from": "{source_field}"
    }
  }
}
```

| Property | Type | Description |
|---|---|---|
| Key | `string` | Variable name of the category |
| `null` | – | Direct mapping |
| `from` | `string` | Source from a linked object |

#### Example

```json
"categories": {
  "123": {
    "zielgebiet_default": null,
    "reiseart_default": null,
    "sterne_default": {
      "from": "unterkuenfte_default"
    }
  }
}
```

In this example, `zielgebiet_default` and `reiseart_default` are taken directly from the object, while `sterne_default` is read from the linked object `unterkuenfte_default`.

---

### `data.search_mongodb.search.descriptions`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |

#### Description

Defines description fields to be included in MongoDB search documents. Each field can specify a filter and a source.

#### Structure

```json
"descriptions": {
  "{object_type_id}": {
    "{description_name}": {
      "field": "{source_field}",
      "from": null,
      "filter": "\\Custom\\Filter::strip",
      "params": {}
    }
  }
}
```

| Property | Type | Description |
|---|---|---|
| `field` | `string` | Source field in the object |
| `from` | `string` or `null` | Linked object as source |
| `filter` | `string` or `null` | PHP callable for data transformation |
| `params` | `object` | Additional parameters for the filter |

#### Example

```json
"descriptions": {
  "123": {
    "headline": {
      "field": "name",
      "from": null,
      "filter": "\\Custom\\Filter::strip"
    },
    "subline": {
      "field": "subline_default",
      "from": "unterkuenfte_default",
      "filter": "\\Custom\\Filter::strip"
    },
    "destination": {
      "field": "zielgebiet_default",
      "from": "unterkuenfte_default",
      "filter": "\\Custom\\Filter::lastTreeItemAsString"
    },
    "images": {
      "field": "bilder_default",
      "filter": "\\Custom\\Filter::firstPicture",
      "params": {
        "derivative": "teaser"
      }
    }
  }
}
```

---

### `data.search_mongodb.search.custom_order`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |
| **Used in** | `Search\Query.php` |

#### Description

Defines custom sort fields for MongoDB search results. Enables sorting by fields not present in the search index by default.

#### Usage in Code

```php
// src/Pressmind/Search/Query.php:748
if (!empty($config['data']['search_mongodb']['search']['custom_order'][$ot])) {
    foreach ($config['data']['search_mongodb']['search']['custom_order'][$ot] as $shortname => $fieldConfig) {
        // Register sort field
    }
}
```

#### Example

```json
"custom_order": {
  "123": {
    "destination": {
      "field": "zielgebiet_default",
      "from": null,
      "filter": null,
      "params": {}
    },
    "region": {
      "field": "region_default",
      "from": null,
      "filter": null,
      "params": {}
    },
    "country": {
      "field": "name",
      "from": "laender_default",
      "filter": null,
      "params": {}
    }
  }
}
```

---

### `data.search_mongodb.search.five_dates_per_month_list`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |

#### Description

When enabled, a list of up to five dates per month is stored in the MongoDB document. Useful for calendar views.

---

### `data.search_mongodb.search.possible_duration_list`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |

#### Description

When enabled, a list of all possible travel durations is stored in the MongoDB document.

---

### `data.search_mongodb.search.allow_invalid_offers`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[]` |
| **Used in** | `Search\MongoDB.php` |

#### Description

List of object type IDs for which invalid offers (e.g., without price or with expired dates) are still allowed to appear in search results.

#### Example

```json
"allow_invalid_offers": [123, 456]
```

---

### `data.search_mongodb.search.order_by_primary_object_type_priority`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Used in** | `Search\MongoDB.php` |

#### Description

When enabled, search results are sorted by the priority of the primary object type.

---

### `data.search_mongodb.search.touristic`

Touristic search parameters for the MongoDB index.

```json
"touristic": {
  "occupancies": [1, 2, 3, 4, 5, 6],
  "occupancy_additional": [1, 2],
  "duration_ranges": [[1, 3], [4, 7], [8, 99]]
}
```

#### `touristic.occupancies`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[1, 2, 3, 4, 5, 6]` |

List of occupancy options (number of persons) for which prices are indexed.

#### `touristic.occupancy_additional`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[1, 2]` |

Additional occupancy options (e.g., children, extra beds).

#### `touristic.duration_ranges`

| Property | Value |
|---|---|
| **Type** | `array` of `[min, max]` arrays |
| **Default** | `[[1, 3], [4, 7], [8, 99]]` |

Travel duration ranges for grouping offers.

**Example:**
- `[1, 3]` = 1-3 nights (short break)
- `[4, 7]` = 4-7 nights (week trip)
- `[8, 99]` = 8+ nights (long-term trip)

---

### `data.search_mongodb.calendar`

```json
"calendar": {
  "include_startingpoint_option": false
}
```

#### `calendar.include_startingpoint_option`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |

When enabled, departure point options are included in calendar indexing.

---

## OpenSearch Search (`data.search_opensearch`)

```json
"search_opensearch": {
  "enabled": false,
  "enabled_in_mongo_search": true,
  "uri": "http://opensearch:9200",
  "user": null,
  "password": null,
  "number_of_shards": 1,
  "number_of_replicas": 0,
  "index": { ... }
}
```

---

### `data.search_opensearch.enabled`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | Yes |
| **Used in** | `Import.php`, `Search\OpenSearch.php`, `Search\MongoDB.php` |

#### Description

Master switch for the OpenSearch integration. When enabled, data is indexed in OpenSearch during import.

---

### `data.search_opensearch.enabled_in_mongo_search`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |
| **Used in** | `Search\MongoDB.php`, `Search\MongoDB\AbstractIndex.php` |

#### Description

Controls whether OpenSearch is used **within** the MongoDB search. Enables combining both search systems – MongoDB for structured data, OpenSearch for full-text.

#### Usage in Code

```php
// src/Pressmind/Search/MongoDB.php:132
$this->_use_opensearch = !empty($config['data']['search_opensearch']['enabled']) 
    && !empty($config['data']['search_opensearch']['enabled_in_mongo_search']);
```

#### Example

```json
// Use OpenSearch only within MongoDB search
"enabled": true,
"enabled_in_mongo_search": true

// Use OpenSearch independently from MongoDB
"enabled": true,
"enabled_in_mongo_search": false
```

---

### `data.search_opensearch.uri`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"http://opensearch:9200"` |
| **Required** | Yes (when enabled) |

#### Description

OpenSearch server URI.

#### Examples

```json
// Docker Compose
"uri": "http://opensearch:9200"

// Local
"uri": "http://127.0.0.1:9200"

// With HTTPS
"uri": "https://opensearch.example.com:9200"

// AWS OpenSearch Service
"uri": "https://search-domain.region.es.amazonaws.com"
```

---

### `data.search_opensearch.user` / `password`

| Property | Value |
|---|---|
| **Type** | `string` or `null` |
| **Default** | `null` |

#### Description

Username and password for OpenSearch authentication via HTTP Basic Auth.

> **Note:** If both are `null`, no authentication is used.

#### Example

```json
"user": "admin",
"password": "admin"
```

---

### `data.search_opensearch.number_of_shards`

| Property | Value |
|---|---|
| **Type** | `integer` |
| **Default** | `1` |

#### Description

Number of primary shards for OpenSearch indices. Determines horizontal data distribution.

| Scenario | Recommended Value |
|---|---|
| Single node | `1` |
| 3-node cluster | `3` |
| Large datasets | `5` |

---

### `data.search_opensearch.number_of_replicas`

| Property | Value |
|---|---|
| **Type** | `integer` |
| **Default** | `0` |

#### Description

Number of replicas per shard. For high availability, at least `1` should be configured.

| Scenario | Recommended Value |
|---|---|
| Development | `0` |
| Production (1 node) | `0` |
| Production (cluster) | `1` - `2` |

---

### `data.search_opensearch.index`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Yes (when enabled) |
| **Used in** | `Search\OpenSearch.php`, `Search\OpenSearch\AbstractIndex.php` |

#### Description

Defines the field mappings for the OpenSearch index. Each field has a type, an optional boost factor, and an object type mapping.

#### Structure

```json
"index": {
  "{field_alias}": {
    "type": "text|keyword",
    "boost": 1,
    "object_type_mapping": {
      "{object_type_id}": [
        {
          "language": null,
          "field": {
            "name": "{source_field_name}",
            "params": []
          }
        }
      ]
    }
  }
}
```

#### Properties

| Property | Type | Description |
|---|---|---|
| `type` | `string` | OpenSearch field type: `"text"` (analyzed), `"keyword"` (exact) |
| `boost` | `integer` | Relevance weighting (default: 1). Higher values = more relevance |
| `object_type_mapping` | `object` | Mapping of object types to source fields |
| `language` | `string` or `null` | Language for language-specific fields |
| `field.name` | `string` | Source field name in the media object |
| `field.params` | `array` | Additional parameters |

#### Complete Example

```json
"index": {
  "code": {
    "type": "keyword",
    "object_type_mapping": {
      "607": [{"language": null, "field": {"name": "code", "params": []}}],
      "609": [{"language": null, "field": {"name": "code", "params": []}}]
    }
  },
  "headline_default": {
    "type": "text",
    "object_type_mapping": {
      "607": [{"language": null, "field": {"name": "headline_default", "params": []}}],
      "609": [{"language": null, "field": {"name": "headline_default", "params": []}}]
    }
  },
  "subline_default": {
    "type": "text",
    "boost": 2,
    "object_type_mapping": {
      "607": [{"language": null, "field": {"name": "subline_default", "params": []}}],
      "609": [{"language": null, "field": {"name": "subline_default", "params": []}}]
    }
  }
}
```

In this example, `subline_default` is weighted with `boost: 2` – matches in this field are prioritized in the search result ranking.

---

## Search Hooks (`data.search_hooks`)

```json
"search_hooks": []
```

---

### `data.search_hooks`

| Property | Value |
|---|---|
| **Type** | `array` of objects |
| **Default** | `[]` |
| **Required** | No |
| **Used in** | `Search\Hook\SearchHookManager.php` |

#### Description

Extensible search providers integrated into the search system via hooks. Enables connecting external APIs or custom search logic.

#### Usage in Code

```php
// src/Pressmind/Search/Hook/SearchHookManager.php:74
foreach ($config['data']['search_hooks'] as $hookConfig) {
    $className = $hookConfig['class'];
    $providerConfig = $hookConfig['config'] ?? [];
    $hook = new $className($providerConfig);
    self::register($hook);
}
```

#### Structure

Each hook has the following properties:

| Property | Type | Description |
|---|---|---|
| `class` | `string` | Fully qualified class name (must implement `SearchHookInterface`) |
| `config` | `object` | Hook-specific configuration |
| `config.enabled` | `boolean` | Whether the hook is active |
| `config.api_url` | `string` | External API URL (optional) |
| `config.object_types` | `array` | Object type IDs the hook applies to |
| `config.default_params` | `object` | Default parameters for API requests |
| `config.priority` | `integer` | Execution priority (lower = earlier) |
| `config.redis` | `object` | Redis cache configuration |
| `config.runtime_cache` | `object` | Runtime cache configuration |

#### Example (from `EXAMPLE_search_hooks`)

```json
"search_hooks": [
  {
    "class": "\\Custom\\Search\\Hook\\ExternalApiProvider",
    "config": {
      "enabled": true,
      "api_url": "https://api.example.com/offers",
      "object_types": [123, 456],
      "default_params": {
        "per_page": 500
      },
      "priority": 10,
      "redis": {
        "enabled": true,
        "host": "127.0.0.1",
        "port": 6379,
        "password": null,
        "database": 2,
        "prefix": "myapp:search:",
        "ttl": 10800
      },
      "runtime_cache": {
        "enabled": true
      }
    }
  }
]
```

> **Note:** The `EXAMPLE_search_hooks` entry in the config is purely illustrative and is ignored by the code. Only `search_hooks` is processed.

#### Implementing a Custom Hook

A hook class must implement the `SearchHookInterface`:

```php
namespace Custom\Search\Hook;

use Pressmind\Search\Hook\SearchHookInterface;
use Pressmind\Search\Hook\SearchHookResult;

class ExternalApiProvider implements SearchHookInterface
{
    private $_config;
    
    public function __construct(array $config)
    {
        $this->_config = $config;
    }
    
    public function search(array $conditions): SearchHookResult
    {
        // Implement search logic
        $result = new SearchHookResult();
        $result->setIds([123, 456, 789]);
        return $result;
    }
}
```

---

### `data.media_types_fulltext_index_fields`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Used in** | `ORM\Object\MediaObject.php`, `ORM\Object\FulltextSearch.php`, `Search\MongoDB\AbstractIndex.php` |

#### Description

Defines per object type which fields are included in the full-text search index. Used for both the MySQL full-text table and MongoDB.

#### Example

```json
"media_types_fulltext_index_fields": {
  "123": ["code", "name", "headline_default", "beschreibung_default", "tags_default"],
  "456": ["code", "name", "hotelname_default", "ort_default"]
}
```

> **Note:** This field is automatically populated by the `ObjectTypeScaffolder` but can be manually adjusted.

---

## Complete MongoDB Configuration Example

```json
"search_mongodb": {
  "enabled": true,
  "database": {
    "uri": "mongodb://127.0.0.1:27017",
    "db": "pressmind_search"
  },
  "search": {
    "build_for": {
      "123": [
        {"language": "de", "origin": 0, "disable_language_prefix_in_url": true}
      ]
    },
    "code_delimiter": ",",
    "groups": [
      {"100": {"field": "agencies", "filter": null}},
      {"101": {"field": "brand", "filter": null}}
    ],
    "categories": {
      "123": {
        "zielgebiet_default": null,
        "reiseart_default": null
      }
    },
    "descriptions": {
      "123": {
        "headline": {"field": "name", "from": null, "filter": null}
      }
    },
    "custom_order": {},
    "five_dates_per_month_list": false,
    "possible_duration_list": true,
    "allow_invalid_offers": [],
    "order_by_primary_object_type_priority": false,
    "touristic": {
      "occupancies": [1, 2, 3, 4],
      "occupancy_additional": [1],
      "duration_ranges": [[1, 3], [4, 7], [8, 14], [15, 99]]
    }
  },
  "calendar": {
    "include_startingpoint_option": false
  }
}
```

---

[← Back to Overview](configuration.md) | [Next: Cache →](config-cache.md)
