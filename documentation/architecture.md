# Pressmind SDK – Architecture & Design Patterns

## Purpose

The Pressmind SDK is a **central core for touristic product management**. It serves three primary functions:

1. **Offer Cache** – Imports, normalizes, and stores touristic product data (dates, prices, accommodations, transports) from the Pressmind Webcore into a local MySQL database, providing fast and reliable access to offer data.

2. **Search Engine** – Provides a high-performance search layer via MongoDB and OpenSearch that enables complex touristic queries (price ranges, date ranges, occupancy, transport types, categories) with faceted filtering and pagination.

3. **Offer Middleware** – Acts as a data pipeline between the Pressmind CMS (Webcore) and consumer applications (Travelshops, IBE, APIs), transforming raw product data into structured, searchable, and cacheable content.

```
┌──────────────────┐     ┌─────────────────────────────────────────────┐     ┌──────────────────┐
│  Pressmind PIM   │     │              Pressmind SDK                  │     │  Consumer Apps   │
│                  │────▶│                                             │────▶│                  │
│                  │ REST│  ┌─────────┐  ┌────────┐  ┌─────────────┐   │ REST│  - Travelshop    │
│  - Products      │ API │  │ Import  │  │ MySQL  │  │ MongoDB     │   │ API │  - IBE3          │
│  - Touristic     │────▶│  │ Pipeline│─▶│ Offer  │─▶│ Search      │   │────▶│  - Custom APIs   │
│  - Categories    │     │  │         │  │ Cache  │  │ Engine      │   │     │  - Mobile Apps   │
│  - Media         │     │  └─────────┘  └────────┘  └─────────────┘   │     │                  │
└──────────────────┘     │                                             │     └──────────────────┘
                         │  ┌─────────┐  ┌────────┐  ┌─────────────┐   │
                         │  │ Redis   │  │ Open   │  │ Image/File  │   │
                         │  │ Cache   │  │ Search │  │ Storage     │   │
                         │  └─────────┘  └────────┘  └─────────────┘   │
                         └─────────────────────────────────────────────┘
```

---

## Design Patterns

The SDK applies well-established design patterns to ensure modularity, testability, and extensibility.

### 1. Registry (Singleton)

**Purpose:** Provides a single, globally accessible container for shared runtime objects (config, database connection, etc.).

**Implementation:** `Pressmind\Registry`

```php
class Registry {
    private $_registry = [];
    static $_instance = null;

    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function add($key, $value) {
        $this->_registry[$key] = $value;
    }

    public function get($key) {
        return $this->_registry[$key];
    }
}
```

**Stored Objects:**

| Key | Type | Description |
|---|---|---|
| `config` | `array` | Parsed `config.json` for the active environment |
| `db` | `DB\Adapter\Pdo` | Database connection instance |
| `defaultSectionName` | `string` | Active section name |

**Usage:**

```php
$config = Registry::getInstance()->get('config');
$db     = Registry::getInstance()->get('db');
```

**Why this pattern?** The SDK runs in various contexts (CLI import, REST server, Travelshop rendering). The Registry provides a consistent way to access infrastructure objects without dependency injection, keeping the API simple for integrators.

---

### 2. Active Record (ORM)

**Purpose:** Each database table is represented by a PHP class that handles its own persistence (read, create, update, delete).

**Implementation:** `Pressmind\ORM\Object\AbstractObject`

**Key Features:**

| Method | Description |
|---|---|
| `read($id)` | Load a single record by primary key (with optional cache) |
| `create()` | Insert a new record |
| `update()` | Update an existing record |
| `delete($cascade)` | Delete with optional relation cascade |
| `save()` | Smart create-or-update |
| `loadAll($where, $order, $limit)` | Query multiple records |
| `batchCreate()` | Bulk insert with relation support |
| `readRelations()` | Lazy-load related objects |
| `fromStdClass($obj)` | Hydrate from API response |
| `toStdClass($withRelations)` | Serialize for output |

**Relation Types:**

| Type | Description |
|---|---|
| `hasOne` | One-to-one (e.g., MediaObject → Route) |
| `hasMany` | One-to-many (e.g., MediaObject → Prices) |
| `belongsTo` | Inverse of hasOne |
| `ManyToMany` | Many-to-many via join table |

**Example:**

```php
$mediaObject = new MediaObject();
$mediaObject->read(12345);

// Lazy-loaded relations via __get()
$prices = $mediaObject->cheapest_prices;
$categories = $mediaObject->categories;

// Persistence
$mediaObject->name = 'Updated Name';
$mediaObject->update();
```

**Why this pattern?** The SDK manages ~50 database tables with complex relationships. Active Record keeps the data access code close to the domain model, making imports and transformations straightforward.

---

### 3. Factory

**Purpose:** Decouples object creation from usage, enabling runtime selection of implementations.

**Implementations:**

| Factory | Creates | Selection By |
|---|---|---|
| `Cache\Adapter\Factory` | Cache adapters (Redis) | `config.cache.adapter.name` |
| `Storage\Provider\Factory` | Storage providers (Filesystem, S3) | `config.*.storage.provider` |
| `ORM\Filter\Factory` | Input/Output filters | Data type + direction |
| `ORM\Validator\Factory` | Field validators | Validation config |
| `ORM\Object\MediaType\Factory` | MediaType instances | Name or ID |
| `Import\Mapper\Factory` | Import field mappers | Field type |
| `Image\Processor\Adapter\Factory` | Image processors | `config.image_handling.processor.adapter` |

**Pattern:**

```php
// src/Pressmind/Cache/Adapter/Factory.php
class Factory {
    public static function create($pAdapterName) {
        $class_name = '\Pressmind\Cache\Adapter\\' . $pAdapterName;
        return new $class_name();
    }
}

// Usage:
$cache = Factory::create('Redis');
```

**Why this pattern?** Different environments require different backends (filesystem vs. S3, Redis vs. no cache). Factories allow the `config.json` to determine the implementation without changing code.

---

### 4. Adapter

**Purpose:** Provides a uniform interface for interchangeable backend implementations.

**Implementation Areas:**

#### Database Adapter
```
AdapterInterface
    └── Pdo (MySQL/MariaDB)
```

Key methods: `fetchAll()`, `fetchRow()`, `insert()`, `update()`, `delete()`, `batchInsert()`, transactions.

#### Cache Adapter
```
AdapterInterface
    └── Redis
```

Key methods: `add()`, `get()`, `exists()`, `remove()`, `cleanUp()`.

#### Storage Provider
```
ProviderInterface
    ├── Filesystem (local disk)
    └── S3 (AWS S3 / S3-compatible)
```

Key methods: `save()`, `delete()`, `readFile()`, `fileExists()`, `listBucket()`.

#### Config Adapter
```
AdapterInterface
    ├── Json (config.json)
    └── Php (config.php)
```

**Why this pattern?** Storage, caching, and database backends vary between environments. The Adapter pattern lets integrators swap implementations (e.g., local filesystem → S3) by changing only configuration, not code.

---

### 5. MVC (Model-View-Controller)

**Purpose:** Separates the REST API layer into request handling, routing, and response generation.

**Components:**

| Component | Class | Responsibility |
|---|---|---|
| **Request** | `MVC\Request` | Parses URI, headers, body, query parameters, Basic Auth |
| **Response** | `MVC\Response` | Sets status codes, headers, JSON encoding, gzip compression |
| **Router** | `MVC\Router` | Matches routes to controller actions via regex patterns |
| **Route** | `MVC\Router\Route` | Individual route definition with method + pattern |
| **View** | `MVC\View` | PHP template rendering with data injection |
| **Controller** | `REST\Controller\*` | Business logic per endpoint |

**Request Flow:**

```
HTTP Request
  → Server::__construct()     // Register routes
  → Server::handle()
    → _checkAuthentication()  // Basic Auth check
    → Router::handle()        // Match route
    → _callControllerAction() // Execute controller
      → Cache check/write     // If caching enabled
    → Response::send()        // JSON output with headers
```

**Why this pattern?** The SDK provides a REST API for the Travelshop and external consumers. MVC gives a clean separation between HTTP handling and business logic.

---

### 6. Pipeline (Import)

**Purpose:** Orchestrates the multi-step import of media objects from the Pressmind Webcore into the local database and search indices.

**Implementation:** `Pressmind\Import`

**Pipeline Stages:**

```
1. Fetch IDs from Webcore API
     ↓
2. Queue IDs for import
     ↓
3. For each media object:
   ┌─────────────────────────────────┐
   │ BEGIN TRANSACTION               │
   │  a) Fetch full object via API   │
   │  b) Delete existing data        │
   │  c) Import touristic data       │
   │     - Dates, Prices, Housing    │
   │     - Transport, Insurance      │
   │     - Startingpoints            │
   │  d) Calculate cheapest prices   │
   │  e) Import content data         │
   │     - Sections, Languages       │
   │     - Categories, Locations     │
   │     - Pictures, Files, Links    │
   │  f) Import linked objects       │
   │     (recursive)                 │
   │  g) Generate routes/URLs        │
   │  h) Execute custom hooks        │
   │ COMMIT TRANSACTION              │
   └─────────────────────────────────┘
     ↓
4. Update search indices
   - MongoDB: Upsert documents
   - OpenSearch: Index fields
     ↓
5. Invalidate caches
     ↓
6. Remove orphaned objects
```

**Extensibility Points:**

| Hook | Timing | Config Key |
|---|---|---|
| Custom import hooks | During import, per object type | `data.media_type_custom_import_hooks` |
| Post-import hooks | After import completes | `data.media_type_custom_post_import_hooks` |
| MyContent class map | During touristic import | `data.touristic.my_content_class_map` |

**Why this pattern?** The import process is complex with many interdependent steps. The pipeline pattern ensures correct ordering, transaction safety, and clear extensibility through hooks.

---

### 7. Observer / Hook System

**Purpose:** Enables external code to modify search behavior without changing SDK internals.

**Implementation:** `Search\Hook\SearchHookManager`

**Components:**

| Class | Role |
|---|---|
| `SearchHookInterface` | Contract for hook implementations |
| `SearchHookManager` | Registers and executes hooks |
| `SearchHookResult` | Data container for hook results |

**Lifecycle:**

```
MongoDB::getResult()
  │
  ├─ SearchHookManager::executePreSearch($conditions, $context)
  │    → Hook may: add codes, remove conditions, force sort order
  │
  ├─ Build & execute MongoDB query
  │
  └─ SearchHookManager::executePostSearch($result, $context)
       → Hook may: enrich documents with external data
```

**Configuration:**

```json
"search_hooks": [
  {
    "class": "\\Custom\\Search\\Hook\\ExternalApiProvider",
    "config": {
      "enabled": true,
      "priority": 10
    }
  }
]
```

**Why this pattern?** Travel websites often need to integrate external data sources (availability APIs, partner inventories). The hook system allows this without forking the SDK.

**Search Hooks during validation:** After import (and when opening validation in the Backend), `MediaObject::validate()` runs. To check whether the product appears in the MongoDB search index (“MongoIndex Results”), it calls `Query::getResult()` with a filter limited to that media object. That goes through the full search pipeline, so `executePreSearch` and `executePostSearch` are always called. If you want to avoid triggering external hooks during validation, set `QueryFilter->skip_search_hooks = true` before calling `Query::getResult()` in validation code; the hooks will then be skipped for that request.

---

### 8. Value Object

**Purpose:** Immutable data containers for structured results without behavior.

**Implementations:**

| Class | Content |
|---|---|
| `ValueObject\Search\Filter\Result\MinMax` | Min/max values for filters |
| `ValueObject\Search\Filter\Result\DateRange` | Date ranges for filter results |
| `ValueObject\MediaObject\Result\GetPrettyUrls` | URL routing results |
| `ValueObject\MediaObject\Result\GetByPrettyUrl` | URL lookup results |

**Why this pattern?** Value Objects provide type-safe return values for methods that would otherwise return unstructured arrays, improving code readability and IDE support.

---

## Functional Overview

### Data Layer

| Function | Description |
|---|---|
| **Import** | Full and incremental import from Pressmind Webcore via REST API |
| **ORM** | Active Record objects for all ~50 database tables |
| **Transactions** | ACID-compliant imports with rollback on failure |
| **Schema Migration** | Automatic database schema updates when API fields change |
| **Orphan Detection** | Safe removal of objects no longer in the source system |

### Search Layer

| Function | Description |
|---|---|
| **MongoDB Search** | Aggregation pipeline-based search with faceted filters |
| **OpenSearch** | Full-text search with boost weighting and autocomplete |
| **CheapestPrice** | Optimized price index for fast "from price" queries |
| **Search Hooks** | Extensible pre/post-search hooks for external data sources |
| **Pagination** | Server-side pagination with total counts |

### Caching Layer

| Function | Description |
|---|---|
| **Redis Cache** | TTL-based caching for REST responses, search results, objects |
| **Cache Types** | Fine-grained control over what is cached (REST, SEARCH, OBJECT, etc.) |
| **Cache Control** | URL parameters to bypass or force-refresh cache |
| **Auto-Cleanup** | Idle time and frequency-based cache management |

### Media Layer

| Function | Description |
|---|---|
| **Image Processing** | Automatic derivative generation (thumbnail, teaser, detail) |
| **Image Filters** | Watermarks, grayscale, Instagram-style filters |
| **WebP Support** | Optional WebP conversion for each derivative |
| **Storage Abstraction** | Local filesystem or S3-compatible cloud storage |

### API Layer

| Function | Description |
|---|---|
| **REST Server** | Built-in REST API with authentication and routing |
| **REST Client** | HTTP client for Webcore API communication |
| **IBE Integration** | Booking link generation for IBE3 |
| **Custom Controllers** | Extensible with `\Custom\REST\Controller\*` classes |

---

## Directory Structure

```
src/Pressmind/
├── Cache/           # Cache adapters (Redis)
├── CLI/             # Command-line tools
├── Config/          # Configuration adapters (JSON, PHP)
├── DB/              # Database abstraction (PDO)
├── File/            # File processing
├── IBE/             # IBE integration
├── Image/           # Image processing, filters, derivatives
├── Import/          # Import pipeline, mappers, data importers
├── Log/             # Logging (Writer, Service)
├── MVC/             # Request, Response, Router, View
├── ORM/             # Object-Relational Mapping
│   ├── Filter/      #   Input/Output filters
│   ├── Object/      #   Active Record models (~50 classes)
│   └── Validator/   #   Field validators
├── REST/            # REST API (Client, Server, Controllers)
├── Search/          # Search engine
│   ├── Condition/   #   Query conditions (MongoDB, SQL)
│   ├── Filter/      #   Search result filters
│   ├── Hook/        #   Extensible search hooks
│   ├── MongoDB/     #   MongoDB indexer, abstract index
│   ├── OpenSearch/  #   OpenSearch indexer
│   └── Query/       #   Query builder, Filter value object
├── Storage/         # Storage abstraction (Filesystem, S3)
├── System/          # System utilities, migrations, validation
├── Tools/           # Helper tools (PriceHandler)
└── ValueObject/     # Immutable data containers
```

---

[← Back to Overview](configuration.md) | [Next: REST API Endpoints →](rest-api-endpoints.md) | [MongoDB Search API →](search-mongodb-api.md) | [MongoDB Index Configuration →](search-mongodb-index-configuration.md) | [OpenSearch →](search-opensearch.md) | [Import Process →](import-process.md) | [Image Processor →](image-processor.md) | [Template Interface →](template-interface.md) | [Real-World Examples →](real-world-examples.md) | [CheapestPrice →](cheapest-price-aggregation.md) | [Troubleshooting →](troubleshooting-missing-products.md)
