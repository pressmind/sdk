# Changelog

All notable changes to the pressmind SDK from February 2025 to February 2026.

Changes are categorized as:
- **FEATURE** – New functionality
- **CHANGE** – Modified existing behavior
- **BUG** – Bug fixes
- **BREAKING** – Breaking changes that require action

---

## Table of Contents

- [February 2026](#february-2026)
- [January 2026](#january-2026)
- [December 2025](#december-2025)
- [November 2025](#november-2025)
- [October 2025](#october-2025)
- [September 2025](#september-2025)
- [August 2025](#august-2025)
- [July 2025](#july-2025)
- [June 2025](#june-2025)
- [May 2025](#may-2025)
- [April 2025](#april-2025)
- [March 2025](#march-2025)
- [February 2025](#february-2025)
- [Summary of Breaking Changes](#summary-of-breaking-changes)

---

## February 2026

### FEATURE: Search Hooks – External Search Provider Plugin System (`51e9bac`)

A complete plugin architecture for injecting external search providers into the MongoDB search pipeline. Enables integrating third-party availability systems, price engines, or custom search backends.

- New interface `Search\Hook\SearchHookInterface` defines the contract for hook implementations
- `SearchHookManager` orchestrates hook execution with priority ordering and result merging
- `SearchHookResult` provides a typed result envelope
- `ExampleSearchProvider` serves as a reference implementation
- Hooks support Redis caching and runtime caching for performance
- Configured via the new `search_hooks` array in `config.json`

### FEATURE: Database Integrity Check CLI (`239e167`, `76c611d`)

New `bin/database-integrity-check` command that validates the local MySQL schema against ORM model definitions.

- Detects missing columns, type mismatches, and index issues
- Validates custom `objectdata_*` tables against API object type definitions
- Supports `--non-interactive` mode for CI/CD pipelines
- Iterates up to 5 fix attempts per table to resolve cascading differences

### FEATURE: Image Processor CLI Rewrite (`24de675`)

Complete rewrite of image processing as a standalone CLI command (`ImageProcessorCommand`, 815 lines).

- Process locking prevents concurrent execution
- Handles image downloading, derivative creation, WebP generation, and MongoDB index updates
- Supports `unlock` argument and `mediaobject <ids>` for selective processing
- Integrated image integrity verification

### FEATURE: Environment Validation System (`24de675`)

New `System\EnvironmentValidation` class (491 lines) that checks runtime prerequisites:

- PHP version and required extensions
- Configuration integrity
- Storage access (filesystem/S3)
- Database connectivity
- MongoDB availability

### FEATURE: MongoDB Condition Getters & Category NOT (`fc012bf`)

- Public getter methods on all MongoDB condition classes (`BoardType`, `Code`, `DateRange`, `DurationRange`, `Occupancy`) for reading back configured values
- `Category` condition now supports exclusion via `$categoryIdsNot` parameter, generating `$not.$elemMatch` queries

### FEATURE: REST Import Controller – `touristicByCode()` (`82bbed0`)

New REST endpoint for external touristic data injection:

- Accepts media object `code` and `booking_packages` JSON payload
- Replaces all existing booking packages with cascading delete
- Recalculates cheapest prices and updates all indexes (Cache, MongoDB, OpenSearch)

### CHANGE: Import Performance Overhaul (`51e9bac`)

Significant performance improvements to the import pipeline:

- Cached `$_config` and `$_db` on Import class (avoids repeated Registry lookups)
- Static flags prevent redundant Brand/Season/Port/Powerfilter/EarlyBird API calls per session
- Route creation uses new batch INSERT instead of individual ORM creates
- Orphan detection uses `array_flip()` for O(1) lookups instead of `in_array()`
- Orphan attachment cleanup removes unreferenced attachments after full import

### CHANGE: MongoDB Indexer Expansion (`51e9bac`, `239e167`)

Major expansion of the MongoDB Indexer (~600 new lines):

- Powerfilter documents upserted to MongoDB after import
- Enhanced indexing capabilities for custom sort fields
- `Code` condition supports multi-mode query operators

### BUG: Query.php Parameter Parsing Fix (`126278d`)

Fixed parameter parsing issue in `Search\Query::fromRequest()`.

### BUG: MongoDB Indexer Cleanup (`68a0dee`)

Removed extraneous code from MongoDB Indexer.

### BREAKING: New `batchInsert()` Method on `AdapterInterface` (`51e9bac`)

> **Action Required:** Any custom DB adapter implementations must add the `batchInsert()` method. The method signature is defined in `DB\Adapter\AdapterInterface`.

---

## January 2026

### FEATURE: Touristic Orphans Detection System (`70946e8`, `7e2d420`, `22d5253`, `6f8a11f`, `97d822f`)

New `bin/touristic-orphans` command and `System\TouristicOrphans` class for finding products invisible in search.

- Statistics per object type: visible count, with-prices count, orphan count, percentage
- Individual orphan listing with booking package/date/option details
- Detail mode for single media object investigation
- `--stats-only` and `--object-types` filtering options
- Multiple refinement iterations improving detection accuracy

### FEATURE: Image Filter Pipeline (`754d1c0`, `bec5632`)

Complete post-processing filter system for image derivatives:

- `FilterInterface` / `AbstractFilter` base architecture
- `WatermarkFilter` – overlay watermarks with configurable position, size, margin, opacity
- `InstaFilter` – Instagram-style presets (vintage, vivid, warm, cool, fade) with intensity control
- `GrayscaleFilter` – grayscale conversion
- `FilterChain` manages ordered filter execution from config
- Filters are configured per derivative via a new `filters` array in the derivative config

### FEATURE: SchemaMigrator – Automatic Schema Migration (`754d1c0`, `6821bf8`)

Automatic detection and handling of new PIM fields not yet in local database:

- Three modes: `log_only` (default, logs warning), `auto` (adds columns automatically), `abort` (throws exception)
- Dynamic properties on `AbstractMediaType` allow data persistence even before schema update
- PHPUnit test coverage (`SchemaMigratorTest`)
- Configured via `schema_migration.mode` and `schema_migration.log_changes`

### FEATURE: Attachment ORM Model (`754d1c0`)

New `Attachment` and `AttachmentToMediaObject` ORM models for file attachments linked to media objects.

### FEATURE: MongoDB Port Search Condition (`754d1c0`)

New `Search\Condition\MongoDB\Port` for filtering by port/harbor in cruise/ship contexts.

### FEATURE: Custom Sort Order for MongoDB (`3506e58`)

- New `custom_order` config section under `search_mongodb.search` for per-object-type custom sort fields
- Fields can reference direct properties or linked objects
- Query parameter `pm-o=co.<shortname>-asc/desc` enables frontend sorting by custom fields
- All sort operations now include `_id` as tiebreaker for deterministic pagination

### FEATURE: CLI Framework (`6821bf8`, `754d1c0`)

- New `CLI\AbstractCommand` base class (98 lines) providing consistent argument parsing and output
- `CLI\Output` helper for styled terminal output
- Foundation for all new CLI commands

### CHANGE: Import Robustness (`bfdf81e`)

- File Downloader hardened: proper User-Agent, SSL verification, HTTP status checking, empty content detection, increased timeouts (120s/30s), curl error codes in messages
- `AbstractImport` base class tracks elapsed time and heap usage for all importers
- All sub-importers now log performance metrics

### CHANGE: Calendar Rewrite with Housing Package Support (`9bf7ef2`)

Major refactor of the MongoDB Calendar system:

- Calendar filter dimensions now track `id_housing_packages` as a cross-filter
- Three-tier fallback for resolving housing package IDs (direct, hash-based, single-package)
- Calendar merge logic restructured

### BUG: Calendar Filter Fixes (`9d31f07`)

Fixed `CalendarFilter` and `MediaObject` calendar generation issues.

### BUG: OpenSearch Index Updates (`ded0f79`, `07f4ab0`)

Added missing index refresh calls in OpenSearch after document updates.

### BUG: CheapestPriceSpeed Query Fix (`98404d0`)

Fixed CheapestPriceSpeed query in `MediaObject` for occupancy resolution.

### CHANGE: composer.json – PHPUnit, Bin Scripts, Post-Update Hook (`6821bf8`)

- Added `phpunit/phpunit` as dev dependency
- Added `bin` array pointing to CLI commands
- Added `post-update-cmd` script for automatic integrity checking
- Added `autoload-dev` for test namespace

### CHANGE: Log Cleanup Automation (`6821bf8`)

`Log\Writer::cleanup()` now automatically removes log entries older than 5 days. Called during Import constructor.

---

## December 2025

### BUG: Language Setup Fix & Rollback (`c703aab`, `777d908`)

Language setup was fixed and then rolled back due to regression. The fix incorrectly changed language initialization behavior.

### BUG: Multiple Import & Search Fixes (`709c702`, `dd52eb3`, `a49d7d2`, `9cdd67d`, `1854b56`, `f201361`, `14219bf`, `62877b4`, `f4f1aec`, `32af004`, `5c612ca`, `c6fb86d`)

Series of stability fixes across:
- Import pipeline error handling
- MongoDB search query generation
- CheapestPrice calculation edge cases
- Image processing error recovery

### CHANGE: OpenSearch PHP Integration Update (`68f34ad`)

Updated OpenSearch PHP client integration.

---

## November 2025

### FEATURE: MongoDB Search Improvements (`5c73c10`, `ee14591`, `4b39916`, `7ac7b30`, `8783bae`)

Multiple improvements to the MongoDB search layer, including enhanced query building, better filter handling, and performance optimizations.

### BUG: Import & Search Stability Fixes (`6964463`, `603c2e3`, `3edf089`, `7d70bd2`, `33f2f3b`, `c89ed1a`, `ef933a9`)

Multiple fixes for:
- Touristic data import edge cases
- MongoDB query generation
- CheapestPrice calculation

### BUG: OpenSearch Error Handling (`252fb57`, `a25d171`)

Improved error catching in OpenSearch integration to prevent import failures when OpenSearch is unavailable.

---

## October 2025

### BUG: OpenSearch Fix (`b0a82ce`)

Fixed OpenSearch integration issue.

### BUG: Multiple CheapestPrice & Import Fixes (`3ab7cf8`, `4174646`, `6ae630b`, `ed66472`, `75746e1`, `6577c09`)

Series of fixes for:
- CheapestPrice aggregation with edge case data
- Import pipeline reliability

### CHANGE: Search & Import Improvements (`9a3f424`, `533a2c0`, `2cfe580`, `ba2f241`)

Various improvements to search query handling and import robustness.

---

## September 2025

### CHANGE: CheapestPrice & Search Refinements (`458a557`, `feaf218`, `763d4f5`, `315edbc`, `ee8685a`, `50f03d7`)

Multiple refinements to:
- CheapestPrice calculation logic
- MongoDB search query building
- Import flow optimizations

### BUG: Multiple Fixes (`6a642c5`, `9613a59`, `28f4732`, `efa770c`, `4d008f2`, `d9bbfe0`)

Stability fixes across:
- Search query parameter validation
- CheapestPrice calculation
- Import error handling

---

## August 2025

### FEATURE: Redis Password Authentication (`3bb0611`)

Redis cache adapter now supports optional password authentication.

- New `password` key in `cache.adapter.config`
- Calls `$redis->auth()` when password is configured

### CHANGE: Import Improvements (`0758440`, `e13f998`)

Various import pipeline improvements.

### BUG: Import Fix (`d42e6f5`)

Fixed import issue.

---

## July 2025

### FEATURE: OpenSearch Full Integration (`f597832`)

Complete new search backend for fulltext search:

- `Search\OpenSearch` (318 lines) – main search class with index management
- `Search\OpenSearch\AbstractIndex` (317 lines) – index schema management
- `Search\OpenSearch\Indexer` (230 lines) – document indexing from MongoDB data
- `Search\Condition\MongoDB\AtlasLuceneFulltext` – Atlas Search support
- `Search\Condition\MongoDB\Fulltext` – enhanced fulltext condition
- `ORM\Object\FulltextSearch` – fulltext search result ORM
- `Search\SearchType` enum (`DEFAULT`, `AUTOCOMPLETE`)
- Import pipeline now updates OpenSearch alongside MongoDB
- New composer dependencies: `opensearch-project/opensearch-php ^2.4.3`, `symfony/http-client ^7.3`, `nyholm/psr7 ^1.8`

### CHANGE: IBE Booking Improvements (`e8a1f85`)

Updated IBE booking handler and REST IBE controller.

### BUG: EarlyBird Import Fixes (`ecbf61b`)

Fixed EarlyBird import issue.

### BUG: Import & Search Fixes (`288693a`, `0fc4d14`)

Fixed touristic data import issue and discount import handling.

### BREAKING: Removed Config Keys (`f597832`)

> **Action Required:** The following config keys have been removed:
> - `debug` (entire section)
> - `scheduled_tasks` (entire section)
> - `data.preview_url`
> - `logging.error_email_address`
> - `rest.client.api_endpoint_overwrite_default`
>
> Remove these keys from your `config.json` / `pm-config.php`. Code referencing them will produce undefined index notices.

### BREAKING: New Composer Dependencies (`f597832`)

> **Action Required:** Run `composer update` after upgrading. New required packages:
> - `opensearch-project/opensearch-php ^2.4.3`
> - `symfony/http-client ^7.3`
> - `nyholm/psr7 ^1.8`

---

## June 2025

### FEATURE: Manual Discount System (`fc3a03b`, `b630029`)

New system for per-media-object manual price discounts:

- `ORM\Object\MediaObject\ManualDiscount` – discount model with travel date ranges, booking date ranges, value types (fixed_price/percent), and agency support
- `Import\MediaObjectDiscount` – importer that syncs manual discounts from PIM
- `ManualDiscount::convertManualDiscountsToEarlyBird()` converts manual discounts to EarlyBird groups for CheapestPrice integration

### FEATURE: S3 Storage Provider Improvements (`6519041`)

- Custom endpoint support for S3-compatible services (MinIO, DigitalOcean Spaces) via `endpoint` and `use_path_style_endpoint`
- `deleteAll()` now fully implemented (was previously a stub)
- New `EXAMPLE_storage` config block demonstrating S3 setup

### CHANGE: EarlyBird Validity Logic (`b630029`)

- EarlyBird `isEarlyBirdValid()` now handles null date ranges gracefully (null = no restriction)
- Orphan removal uses `NOT EXISTS` subquery against Date table for safety

### CHANGE: Transport Entity Enhancement (`a2f0170`, `4823269`)

- New `getValidTypes()` and `mapTypeToString()` methods on `Transport` ORM
- Added `crs_meta_data` property to Transport

### CHANGE: Option Entity Enhancement (`56f2c8f`)

Added new properties/methods to `Touristic\Option`.

### CHANGE: Date Entity Enhancement (`1182d07`)

Added `getEarlybirds()` method to `Touristic\Date` for fetching applicable early bird discounts.

### CHANGE: MongoDB Filter Enhancement (`ff202d3`)

Added additional MongoDB search filter capabilities.

### BUG: EarlyBird Import Fixes (`95fe307`, `76cf6ec`, `b28ae86`, `c37d6d1`, `7e98ae7`)

Multiple fixes for the EarlyBird importer:
- Handling of null booking dates
- Orphan removal logic
- Import timing corrections

### BUG: CheapestPrice Fix (`30911d2`)

Fixed CheapestPriceSpeed table handling.

### BUG: Import Ordering Fix (`6bbc4f8`)

Fixed import execution ordering for reliability.

### BUG: REST Client Fix (`ba0a6b1`)

Fixed REST client error handling.

### BUG: MediaObject CheapestPrice Fix (`498e9c2`)

Fixed CheapestPrice calculation in MediaObject for edge cases.

### BREAKING: Removed `scheduled_tasks` Config (`6519041`)

> **Action Required:** The `scheduled_tasks` config array has been removed. If you rely on SDK-defined scheduled tasks, implement your own cron scheduling.

---

## May 2025

### CHANGE: Calendar IBE Entrypoint (`cb45886`)

Updated `MediaObject::getCalendar()` to support the new IBE booking entrypoint format.

### BUG: Calendar CheapestPrice Fix (`12de19e`)

Fixed `CheapestPrice` and `Calendar` interaction for correct price display.

### CHANGE: REST API Version Bump (`1ec334c`)

REST Client API endpoint updated.

---

## April 2025

### FEATURE: Calendar Multi-Package Merge (`8899fa1`)

`MediaObject::getCalendar()` now merges calendars from multiple booking packages into a unified view.

### BUG: Calendar Fixes (`65ca0cd`, `8e58314`)

Fixed calendar filter and MongoDB calendar query issues.

---

## March 2025

### FEATURE: CalendarFilter & CheapestPrice from Request (`f5303be`)

- `CalendarFilter::initFromGet()` populates filter from `$_GET` with input sanitization
- `CheapestPrice::initFromGet()` populates search parameters from `$_GET` with type-specific parsing

### FEATURE: EarlyBird Import as Dedicated Module (`3f9da77`)

Extracted EarlyBird import from `TouristicData` into its own `Import\EarlyBird` class:
- Fetches from dedicated `EarlyBird/search` API endpoint
- Creates `EarlyBirdDiscountGroup` and `Item` records
- Handles orphan removal

### CHANGE: MongoDB Indexer Enhancements (`ed6721d`, `40818ac`)

Enhanced MongoDB Indexer with additional field indexing.

### BUG: MongoDB Filter Min/Max Departure Fix (`ea769be`)

Fixed wrong `minDeparture` / `maxDeparture` values in MongoDB search filter aggregation.

### BUG: Occupancy GetCheapestPrices Fix (`1a03b7b`)

Fixed occupancy parameter handling in `getCheapestPrices()`.

### BUG: Image DataType Picture Fix (`fa2b04a`)

Fixed image processing for `DataType\Picture` with improved error handling.

### BUG: Multiple Calendar & CheapestPrice Fixes (`f5508d2`, `3249cbf`, `f13c251`, `ede214b`, `8f62b64`, `4b06270`, `8e58314`, `a085c71`, `1b8e51b`, `fb56c58`)

Extensive series of fixes for the Calendar and CheapestPrice subsystems, including:
- Calendar merge logic
- CheapestPrice state determination
- Calendar filter parameter handling

### BUG: Touristic Data Import Fixes (`a5a4127`, `396d3eb`)

Fixed edge cases in touristic data import.

### BUG: Query Parameter Fix (`0aca395`)

Fixed search Query parameter handling issue (#345498).

### BREAKING: API Version Upgrade (`3f9da77`)

> **Action Required:** REST Client API endpoint changed from `v2-18` to `v2-21`. Ensure your pressmind Webcore installation supports API version v2-21.

---

## February 2025

### FEATURE: Board Type Offer Generation (`760e564`)

New config option `generate_offer_for_each_option_board_type`:

- When enabled, CheapestPrice calculation partitions by board type (half-board, full-board, etc.)
- Each board type generates its own price entry in the search index
- Disabled by default

### CHANGE: Search & Import Improvements (`71c937e`, `a488192`, `b7d51c3`, `a4966d0`, `4880bba`, `dd92868`, `e5ee998`, `693db37`, `ef34a11`, `48335f4`, `6e6ba32`)

Multiple improvements to:
- MongoDB search query building and result handling
- Import pipeline robustness
- CheapestPrice calculation accuracy

### BUG: SDK Fix (`b220701`)

General SDK stability fix.

---

## Summary of Breaking Changes

| Date | Change | Action Required |
|---|---|---|
| **Jul 2025** | Removed config keys: `debug`, `scheduled_tasks`, `preview_url`, `error_email_address`, `api_endpoint_overwrite_default` | Remove from config files |
| **Jul 2025** | New composer dependencies (opensearch-php, symfony/http-client, nyholm/psr7) | Run `composer update` |
| **Jun 2025** | Removed `scheduled_tasks` config section | Implement own cron scheduling |
| **Mar 2025** | REST API version upgraded from v2-18 to v2-21 | Ensure compatible Webcore version |
| **Feb 2026** | New `batchInsert()` method on `AdapterInterface` | Update custom DB adapters |

---

## New CLI Commands

| Command | Since | Purpose |
|---|---|---|
| `bin/database-integrity-check` | Feb 2026 | Validate MySQL schema against ORM definitions |
| `bin/touristic-orphans` | Jan 2026 | Find products without CheapestPrice entries |
| `bin/integrity-check` | Jan 2026 | General system integrity check |

---

## New Config Options

| Key | Since | Default | Description |
|---|---|---|---|
| `search_hooks` | Feb 2026 | `[]` | External search provider plugin configuration |
| `schema_migration.mode` | Jan 2026 | `log_only` | Schema migration mode (`log_only`, `auto`, `abort`) |
| `schema_migration.log_changes` | Jan 2026 | `true` | Log schema change details |
| `search_mongodb.search.custom_order` | Jan 2026 | `{}` | Custom sort fields per object type |
| `image_handling.processor.derivatives.*.filters` | Jan 2026 | `[]` | Image filter chain per derivative |
| `search_opensearch.*` | Jul 2025 | — | OpenSearch connection and index configuration |
| `cache.adapter.config.password` | Aug 2025 | `null` | Redis authentication password |
| `touristic.generate_offer_for_each_option_board_type` | Feb 2025 | `false` | Partition CheapestPrice by board type |
