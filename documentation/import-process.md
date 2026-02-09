# Import Process

[← Back to Architecture](architecture.md) | [→ Image Processor](image-processor.md) | [→ CheapestPrice Aggregation](cheapest-price-aggregation.md)

---

## Table of Contents

- [Overview](#overview)
- [The Import Pipeline](#the-import-pipeline)
- [Import Types](#import-types)
  - [Full Import](#full-import)
  - [Touristic-Only Import](#touristic-only-import)
  - [Queue Processing](#queue-processing)
- [Step-by-Step: Full Media Object Import](#step-by-step-full-media-object-import)
  - [Phase 1: ID Discovery](#phase-1-id-discovery)
  - [Phase 2: REST API Request](#phase-2-rest-api-request)
  - [Phase 3: Data Import (Transaction)](#phase-3-data-import-transaction)
  - [Phase 4: Post-Transaction Updates](#phase-4-post-transaction-updates)
  - [Phase 5: Post-Import (Image Processing)](#phase-5-post-import-image-processing)
- [Import Sub-Modules](#import-sub-modules)
- [Linked Media Objects](#linked-media-objects)
- [Orphan Removal](#orphan-removal)
- [Custom Import Hooks](#custom-import-hooks)
- [Import Queue](#import-queue)
- [Transaction Safety](#transaction-safety)
- [Global Imports (Once Per Session)](#global-imports-once-per-session)
- [Configuration Reference](#configuration-reference)

---

## Overview

The import process is the data pipeline that synchronizes product data from the **pressmind PIM (Webcore)** into the SDK's local database. It fetches media objects via the pressmind REST API, transforms and stores them in MySQL/MariaDB, builds search indexes (MongoDB, OpenSearch), and triggers image processing.

```
┌──────────────────┐         ┌──────────────────────────────────────────────┐
│  pressmind PIM   │         │  SDK Import Pipeline                        │
│  (Webcore)       │         │                                              │
│                  │  REST   │  1. Discover IDs ──────────────────────────┐ │
│  Products        │──API──▶ │  2. Fetch full object via REST             │ │
│  Touristic Data  │         │  3. Begin Transaction                      │ │
│  Categories      │         │     ├─ Delete old data                     │ │
│  Media/Images    │         │     ├─ Import touristic data               │ │
│                  │         │     ├─ Import media object                 │ │
│                  │         │     ├─ Import content data                 │ │
│                  │         │     ├─ Import categories                   │ │
│                  │         │     ├─ Import agencies, brands, routes     │ │
│                  │         │     ├─ Execute custom hooks                │ │
│                  │         │     └─ Calculate cheapest price            │ │
│                  │         │  4. Commit Transaction                     │ │
│                  │         │  5. Update caches (Redis)                  │ │
│                  │         │  6. Update search indexes                  │ │
│                  │         │     ├─ MySQL fulltext                      │ │
│                  │         │     ├─ MongoDB                             │ │
│                  │         │     └─ OpenSearch                          │ │
│                  │         │  7. Remove orphans                         │ │
│                  │         │  8. Post-import (Image Processor)          │ │
└──────────────────┘         └──────────────────────────────────────────────┘
```

**Entry Point:** `Pressmind\Import`

---

## The Import Pipeline

The import follows a strict sequential pipeline. Each step depends on the previous one:

```
Import::import()
  │
  ├─ 1. getIDsToImport()
  │     └─ REST API: Text/search → Queue IDs
  │
  ├─ 2. importMediaObjectsFromFolder()
  │     └─ For each queued ID:
  │         └─ importMediaObject($id)
  │             ├─ REST API: Text/getById (with touristic + dynamic data)
  │             ├─ BEGIN TRANSACTION
  │             │   ├─ Delete old booking packages
  │             │   ├─ Delete old media object
  │             │   ├─ TouristicData::import()
  │             │   ├─ StartingPointOptions::import()
  │             │   ├─ Import linked touristic media objects
  │             │   ├─ MediaObject::import()
  │             │   ├─ EarlyBird::import()
  │             │   ├─ CheapestPrice calculation
  │             │   ├─ MediaObjectData::import()
  │             │   ├─ CategoryTree::import()
  │             │   ├─ Import linked content media objects
  │             │   ├─ Route generation (pretty URLs)
  │             │   ├─ MyContent class map hooks
  │             │   ├─ Custom import hooks
  │             │   └─ Recalculate cheapest price
  │             ├─ COMMIT TRANSACTION
  │             ├─ Update cache (Redis)
  │             ├─ Create search index (MySQL fulltext)
  │             ├─ Create MongoDB index
  │             ├─ Create MongoDB calendar
  │             └─ Create OpenSearch index
  │
  └─ 3. removeOrphans()
        ├─ Re-fetch all IDs from API
        ├─ Compare with imported IDs
        ├─ Safety check: max_orphan_delete_ratio
        └─ Delete orphaned objects from MySQL + MongoDB
```

---

## Import Types

### Full Import

A complete synchronization of all configured media objects:

```php
$importer = new \Pressmind\Import('fullimport');
$importer->import($id_pool, $allowed_object_types, $allowed_visibilities);
$importer->postImport($importer->getImportedIds());
```

This fetches all IDs from the API, imports each object, and removes orphans.

### Touristic-Only Import

Updates only touristic data (prices, booking packages) without reimporting content, images, or routes. Much faster for price-only updates:

```php
$importer = new \Pressmind\Import('fullimport_touristic');
$importer->import();
// Or for specific objects:
$importer->importTouristicDataFromArray([12345, 67890]);
```

### Queue Processing

The queue system allows incremental imports. Objects are queued (e.g. via webhooks) and processed individually:

```php
$importer = new \Pressmind\Import('queueimport');
$processed_ids = $importer->processQueue();
$importer->postImport($processed_ids);
```

Each queue entry has an `action` field (`mediaobject` or `touristic`) that determines whether a full or touristic-only import is performed.

---

## Step-by-Step: Full Media Object Import

### Phase 1: ID Discovery

The importer queries the pressmind API for all media object IDs matching the configured object types and visibilities:

```
REST API: Text/search
  Parameters:
    - id_media_object_type: from config (primary_media_type_ids or media_types keys)
    - visibility: from config (media_types_allowed_visibilities)
    - id_pool: optional filter
    - startIndex / numItems: paginated (50 per request)
```

Each returned ID is added to the import queue (`pmt2core_import_queue`).

### Phase 2: REST API Request

For each queued ID, the full media object is fetched:

```
REST API: Text/getById
  Parameters:
    - ids: the media object ID
    - withTouristicData: 1
    - withDynamicData: 1
    - byTouristicOrigin: from config (touristic.origins, e.g. "0" or "0,1,2")
```

The response contains the complete product data: content, touristic packages, prices, categories, agencies, images, routes, and discounts.

### Phase 3: Data Import (Transaction)

All database writes happen within a single transaction. If any step fails, the entire import for that object is rolled back.

**3.1 – Delete Old Data**
```
Delete existing booking packages (cascade)
Delete existing media object (cascade)
```

**3.2 – Import Touristic Data** (`TouristicData::import()`)
- Booking packages, dates, housing packages, options
- Transport options, starting point options
- Insurance groups and insurances
- Collects linked media object IDs from touristic data

**3.3 – Import Starting Point Options** (`StartingPointOptions::import()`)
- Departure cities/stations referenced in booking packages

**3.4 – Import Linked Touristic Objects**
- Media objects referenced in touristic data (e.g. accommodations)
- Recursive: each linked object goes through the full import pipeline
- Duplicate prevention via `$_RUNTIME_IMPORTED_IDS`

**3.5 – Import Media Object** (`Import\MediaObject::import()`)
- Core media object record: name, code, visibility, validity dates, etc.

**3.6 – Import Cheapest Price** (`MediaObjectCheapestPrice::import()`)
- Virtual price calculation from booking packages
- Creates entries in `pmt2core_cheapest_price_speed`

**3.7 – Import Manual Cheapest Price** (`ManualCheapestPrice::import()`)
- Editor-defined manual prices from the PIM

**3.8 – Import MyContent** (`MyContent::import()`)
- Custom content assignments (my_contents_to_media_object)

**3.9 – Import Agencies** (`Agency::import()`)
- Agency-to-media-object relationships

**3.10 – Import Global Data (Once Per Session)**
- `Brand::import()` – Brand definitions
- `Season::import()` – Season/time period definitions
- `Port::import()` – Ports (for cruise itineraries)
- `Import\Powerfilter::import()` – Powerfilter result sets
- `EarlyBird::import()` – Early bird discount definitions

These are flagged and only executed once per import session.

**3.11 – Import Itinerary** (`Itinerary::import()`)
- Route itineraries with steps, ports, documents

**3.12 – Calculate Cheapest Price**
```php
$media_object->insertCheapestPrice();
```
Generates optimized price index entries in `pmt2core_cheapest_price_speed`.

**3.13 – Import Discounts** (`MediaObjectDiscount::import()`)
- Discount rules and manual discounts
- Converts manual discounts to early bird format

**3.14 – Import Content Data** (`MediaObjectData::import()`)
- All content fields per section/language
- Pictures, files, links, tables, category trees, locations, object links
- Each field type has its own data type handler
- Collects linked media object IDs and category tree IDs

**3.15 – Import Category Trees** (`CategoryTree::import()`)
- Hierarchical category structures with items, parents, paths

**3.16 – Import Linked Content Objects**
- Media objects referenced via object links in content data
- Recursive import with duplicate prevention

**3.17 – Generate Routes** (Pretty URLs)
```php
$media_object->buildPrettyUrls($language);
```
- Generates SEO-friendly URLs based on `media_types_pretty_url` config
- Stored in `pmt2core_routes` table
- Batch-inserted for performance

**3.18 – Execute Custom Import Hooks**
- `media_type_custom_import_hooks` from config
- Per object type, custom PHP classes implementing `ImportInterface`

**3.19 – Commit Transaction**

### Phase 4: Post-Transaction Updates

After successful commit:

**4.1 – Update Redis Cache**
```php
$media_object->updateCache($id_media_object);
```

**4.2 – Update MySQL Fulltext Index**
```php
$media_object->createSearchIndex();
```

**4.3 – Update MongoDB Index**
```php
$media_object->createMongoDBIndex();
$media_object->createMongoDBCalendar();
```

**4.4 – Update OpenSearch Index**
```php
$media_object->createOpenSearchIndex();
```

### Phase 5: Post-Import (Image Processing)

After all objects are imported, `postImport()` is called:

```php
$importer->postImport($imported_ids);
```

This executes:

1. **Custom post-import hooks** (`media_type_custom_post_import_hooks`)
2. **Image Processor** – spawned as background CLI process:
   ```bash
   nohup php /cli/image_processor.php mediaobject 123,456,789 &
   ```
3. **File Downloader** – spawned as background CLI process:
   ```bash
   nohup php /cli/file_downloader.php &
   ```

The image processor downloads original images from the pressmind CDN and generates all configured derivatives (thumbnails, teasers, etc.). See [Image Processor Documentation](image-processor.md) for details.

---

## Import Sub-Modules

Each import step is handled by a dedicated class implementing `ImportInterface`:

| Class | Responsibility |
|---|---|
| `Import\MediaObject` | Core media object record |
| `Import\MediaObjectData` | Content fields per section/language |
| `Import\TouristicData` | Booking packages, dates, housing, options, transports |
| `Import\MediaObjectCheapestPrice` | Virtual price calculation |
| `Import\ManualCheapestPrice` | Editor-defined prices |
| `Import\MediaObjectDiscount` | Discount rules |
| `Import\CategoryTree` | Category tree structures |
| `Import\Agency` | Agency assignments |
| `Import\Brand` | Brand definitions (global) |
| `Import\Season` | Season definitions (global) |
| `Import\Port` | Ports for itineraries (global) |
| `Import\EarlyBird` | Early bird discounts (global) |
| `Import\Itinerary` | Route itineraries |
| `Import\StartingPointOptions` | Departure locations |
| `Import\MyContent` | Custom content assignments |
| `Import\Powerfilter` | Powerfilter result sets (global) |
| `Import\DataView` | Data view imports |

All implement this interface:

```php
interface ImportInterface {
    public function import();
    public function getLog();
    public function getErrors();
}
```

---

## Linked Media Objects

The SDK supports recursive importing of linked media objects. There are two sources of links:

1. **Touristic links** – Accommodations, ships, or other products referenced in booking packages
2. **Content links** – Object link fields in the content data (e.g. "related hotels")

To prevent infinite loops and duplicate imports, a global runtime variable `$_RUNTIME_IMPORTED_IDS` tracks which objects have already been imported in the current session.

The `disable_recursive_import` config option can be set to prevent automatic import of linked objects:

```json
{
  "data": {
    "disable_recursive_import": false
  }
}
```

---

## Orphan Removal

After a full import, the SDK compares all imported IDs against the existing database to find orphans (objects in the local DB that no longer exist in the PIM).

**Safety mechanisms:**

1. **API failure protection:** If the API ID retrieval fails, orphan removal is completely skipped
2. **Ratio protection:** If the number of orphans exceeds `max_orphan_delete_ratio` (default 50%) of total objects, removal is aborted to prevent accidental mass deletion
3. **Force override:** `force_orphan_removal: true` bypasses the ratio check

```json
{
  "data": {
    "import": {
      "max_orphan_delete_ratio": 0.5,
      "force_orphan_removal": false
    }
  }
}
```

Orphan removal also deletes:
- MongoDB index documents
- Orphan attachments (files without media object reference)

---

## Custom Import Hooks

The SDK provides two hook points for custom import logic:

### Per-Object Hooks (during import)

```json
{
  "data": {
    "media_type_custom_import_hooks": {
      "123": ["\\Custom\\Import\\CalculateSpecialPrices"]
    }
  }
}
```

Executed for each media object of the specified type, inside the transaction. The hook class receives the `id_media_object` and must implement `ImportInterface`.

### Post-Import Hooks (after all imports)

```json
{
  "data": {
    "media_type_custom_post_import_hooks": {
      "123": ["\\Custom\\Import\\BuildSitemaps"]
    }
  }
}
```

Executed once after all objects are imported, outside the transaction.

### MyContent Class Map

Maps `id_my_content` values to custom import classes:

```json
{
  "data": {
    "touristic": {
      "my_content_class_map": {
        "42": "\\Custom\\Import\\CustomTouristicData"
      }
    }
  }
}
```

---

## Import Queue

The import queue (`pmt2core_import_queue`) is a database table that tracks which objects need to be imported:

| Column | Description |
|---|---|
| `id_media_object` | The media object ID |
| `import_type` | `fullimport`, `fullimport_touristic`, `queueimport` |
| `action` | `mediaobject` (full) or `touristic` (prices only) |

Queue operations:
- `Queue::addToQueue($id, $type, $action)` – Add entry
- `Queue::remove($id)` – Remove after processing
- `Queue::getAllPending()` – Get all pending IDs
- `Queue::getAllPendingWithAction()` – Get pending entries with their action type

---

## Transaction Safety

Each media object import is wrapped in a database transaction:

```php
$this->_db->beginTransaction();
try {
    // ... all import steps ...
    $this->_db->commit();
} catch (Exception $e) {
    $this->_db->rollback();
    // Log error, continue with next object
}
```

This ensures that if any step fails, the database remains in a consistent state. The old object data is already deleted at the beginning of the transaction, so a rollback restores the previous state.

---

## Global Imports (Once Per Session)

Certain data types are global (not per media object) and only need to be imported once per session:

| Import | Data |
|---|---|
| Brand | Brand definitions |
| Season | Season/time period definitions |
| Port | Ports for cruise itineraries |
| Powerfilter | Powerfilter result sets → also upserted to MongoDB |
| EarlyBird | Early bird discount definitions |

These are tracked via static flags (`$_globalImportsExecuted`) and can be reset with `Import::resetGlobalImportFlags()`.

---

## Configuration Reference

Key configuration properties that affect the import:

| Config Path | Description |
|---|---|
| `data.primary_media_type_ids` | Object types to import (array of IDs) |
| `data.media_types` | Object type name mapping |
| `data.media_types_allowed_visibilities` | Visibility filter per object type |
| `data.touristic.origins` | Touristic origins to import |
| `data.touristic.disable_touristic_data_import` | Object types without touristic data |
| `data.touristic.disable_virtual_price_calculation` | Disable price calculation per type |
| `data.touristic.disable_manual_cheapest_price_import` | Disable manual prices per type |
| `data.disable_recursive_import` | Disable linked object imports |
| `data.import.max_orphan_delete_ratio` | Safety threshold for orphan deletion |
| `data.import.force_orphan_removal` | Override orphan safety check |
| `data.media_type_custom_import_hooks` | Custom per-object hooks |
| `data.media_type_custom_post_import_hooks` | Custom post-import hooks |
| `data.touristic.my_content_class_map` | MyContent-based custom importers |
| `server.php_cli_binary` | PHP CLI binary path for background processes |
