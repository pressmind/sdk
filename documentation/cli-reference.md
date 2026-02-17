# CLI Reference

[← Documentation Overview](documentation.md) | [WordPress Tools (Helpers)](cli-wordpress-tools.md)

---

## Table of Contents

- [Overview](#overview)
- [How to Run CLI Commands](#how-to-run-cli-commands)
- [Import (Primary Command)](#import-primary-command)
  - [Import: Global Options](#import-global-options)
  - [Import: Subcommands & Parameter Reference](#import-subcommands--parameter-reference)
  - [Import: Examples](#import-examples)
- [Index Mongo (MongoDB Best-Price Index)](#index-mongo-mongodb-best-price-index)
  - [Index Mongo: Subcommands & Parameter Reference](#index-mongo-subcommands--parameter-reference)
  - [Index Mongo: Examples](#index-mongo-examples)
- [Other CLI Commands](#other-cli-commands)
  - [Cache Primer](#cache-primer)
  - [File Downloader](#file-downloader)
  - [Fulltext Indexer](#fulltext-indexer)
  - [Index OpenSearch](#index-opensearch)
  - [Image Processor](#image-processor)
  - [Log Cleanup](#log-cleanup)
  - [Rebuild Cache](#rebuild-cache)
  - [Rebuild Routes](#rebuild-routes)
  - [Reset](#reset)
  - [Touristic Orphans](#touristic-orphans)
  - [Database Integrity Check](#database-integrity-check)
  - [Integrity Check](#integrity-check)
- [WordPress-Dependent Commands](#wordpress-dependent-commands)

---

## Overview

The Pressmind SDK provides a set of **command-line interface (CLI)** tools for import, indexing, cache management, and maintenance. The CLI is **independent of any CMS**: most commands run with only the SDK and a valid `pm-config` (PHP or JSON). A subset of commands and helpers require a WordPress environment (e.g. Travelshop theme); these are documented on a separate page: [CLI WordPress Tools](cli-wordpress-tools.md).

**Most important commands:**

1. **Import** – Synchronizes product data from the pressmind PIM into the local database. This is the **primary** data pipeline and should be run regularly (e.g. via cron). See [Import (Primary Command)](#import-primary-command).
2. **Index Mongo** – Manages the **MongoDB best-price search index**. Required for product search and filter results. See [Index Mongo](#index-mongo-mongodb-best-price-index).
3. All other commands support specific tasks (cache, routes, fulltext, OpenSearch, file download, image processing, cleanup, reset, diagnostics).

---

## How to Run CLI Commands

- **From SDK (recommended):**  
  Use the `bin/` scripts in the SDK package. They require a valid config (e.g. `pm-config.php` in the project root or path set via `PM_CONFIG`). This is the **preferred** way to run CLI commands.

  ```bash
  php bin/import fullimport
  php bin/index-mongo all
  php bin/rebuild-cache
  ```

- **From Travelshop theme (legacy):**  
  The theme provides `cli/*.php` wrappers that load the theme bootstrap (and WordPress if needed) and delegate to the same SDK command classes. Use these only when you need WordPress-specific setup (e.g. post-import Redis callback). **Consider migrating to the SDK `bin/` scripts** where possible.

  ```bash
  cd /path/to/wp-content/themes/travelshop
  php cli/import.php fullimport
  php cli/index_mongo.php all
  ```

Arguments and options are the same; the theme wrapper only adds environment setup.

---

## Import (Primary Command)

The **Import** command is the main data pipeline: it fetches media objects and touristic data from the pressmind REST API, writes them to the local MySQL/MariaDB database, updates search indexes (MongoDB, OpenSearch, fulltext), and triggers post-import steps (e.g. image processing, cache priming). It is the **most important** CLI tool for keeping product data and search in sync with the PIM.

**SDK class:** `Pressmind\CLI\ImportCommand`  
**SDK bin (recommended):** `bin/import`  
**Theme entry point (legacy):** `cli/import.php` (wrapper that bootstraps WordPress and optionally sets a post-import callback for Redis cache).

The command uses a **lock** (`ProcessList::lock('import')`) so that only one import process runs at a time. A stale lock (e.g. after a crash) can be removed with the `unlock` subcommand.

---

### Import: Global Options

| Option | Short | Description |
|--------|--------|-------------|
| `--config=<file>` | `-c=<file>` | Path to config file relative to the script’s parent directory (e.g. `pm-config.php`). Sets `PM_CONFIG` before any import step. If the file does not exist, the command exits with an error. |
| *(positional)* `debug` | — | If the word `debug` appears among the arguments, the command defines `PM_SDK_DEBUG` (if not already defined) to enable SDK debug output. |

**Config path:** The path is resolved relative to the directory of the script that is run (e.g. `cli/import.php` → parent = theme root). Example: `-c=pm-config.php` looks for `pm-config.php` in the theme root.

---

### Import: Subcommands & Parameter Reference

The first positional argument is the **subcommand**. Many subcommands accept an optional second argument: a single ID or a comma-separated list of IDs (media object IDs or object type IDs, depending on the subcommand).

| Subcommand | Second argument | Description |
|------------|-----------------|-------------|
| `fullimport` | — | Imports **all** media objects from the PIM (ID discovery via API, then full import for each). Runs post-import (images, hooks) and optionally the post-import callback (e.g. Redis). |
| `resume` | — | Processes the **import queue** (e.g. after an interrupted full import). Runs post-import and optional callback for the processed IDs. |
| `mediaobject` | `<id>[,<id>...]` | Imports one or more media objects by ID. Runs post-import for these IDs, optional callback, and prints validation output per object. |
| `mediaobject_cache_update` | `<id>[,<id>...]` | Does **not** import; only triggers the post-import callback (e.g. Redis cache invalidation/priming) for the given IDs. |
| `itinerary` | `<id>[,<id>...]` | Imports itineraries for the given media object ID(s). |
| `objecttypes` | `<id>[,<id>...]` | Imports the **object type** model(s) for the given ID(s). Does not import the media objects of that type. |
| `touristic` | `<id>[,<id>...]` | Imports **only touristic data** for the given media object ID(s) (no full media object re-import). Runs post-import and optional callback, then validation output. |
| `fullimport_touristic` | — | Imports **only touristic data** for all media objects (IDs from local folder/queue). Runs post-import and optional callback. |
| `depublish` | `<id>[,<id>...]` | Sets visibility to `10` (nobody) for the given media object(s) and updates the MongoDB index. |
| `destroy` | `<id>[,<id>...]` | **Deletes** the given media object(s) from the database (with cascade). |
| `remove_orphans` | — | Removes **orphan** records (media objects that no longer exist in the PIM) from the database. |
| `update_tags` | `<id_object_type>` | Updates tags for the given object type ID (single ID). |
| `offer` | `<id>[,<id>...]` | Recalculates **cheapest price** and updates the MongoDB index for the given media object(s). |
| `calendar` | `<id>[,<id>...]` | Recalculates cheapest price and **calendar** data in MongoDB for the given media object(s). |
| `powerfilter` | — | Imports **powerfilter** and result set definitions, then runs MongoDB indexer `upsertPowerfilter()`. |
| `postimport` | `[<id>[,<id>...]]` | Runs only the **post-import** step (image processing, custom hooks) for all objects or for the given IDs. Optional second argument. |
| `categories` | `[<id>[,<id>...]]` | Imports **category trees**. Optional second argument: category tree ID(s). |
| `create_translations` | — | Creates **gettext** `.mo` translation files from category/code data. |
| `reset_insurances` | — | **Truncates** insurance-related tables (reset insurances). |
| `unlock` | — | Removes the import **lock** without running any import. Use with care if a previous run crashed. |
| `help`, `--help`, `-h` | — | Prints usage and subcommand list, then exits. |
| *(default)* | — | If the first argument is missing or unknown, help is printed. |

**Post-import callback:** When the command is run from the Travelshop wrapper with `PM_REDIS_ACTIVATE` set, the wrapper can register a callback (e.g. `RedisPageCache::del_by_id_media_object` and `prime_by_id_media_object`). The SDK command invokes this callback after the relevant subcommands (fullimport, resume, mediaobject, mediaobject_cache_update, touristic, fullimport_touristic) so that Redis cache stays in sync without the SDK depending on Redis or WordPress.

---

### Import: Examples

Examples are shown **first for the SDK CLI** (recommended), **then for the Travelshop wrapper** (legacy). Prefer `bin/import` unless you rely on WordPress-specific behaviour (e.g. Redis cache callback provided by the theme).

**SDK CLI (recommended)**

```bash
# Full import (all products from PIM)
php bin/import fullimport

# Full import with custom config and debug
php bin/import -c=pm-config-staging.php fullimport debug

# Import specific media objects
php bin/import mediaobject 12345,12346,12347

# Import only touristic data for specific IDs
php bin/import touristic 12345,12346

# Touristic-only full sync (no re-import of media object base data)
php bin/import fullimport_touristic

# Import object types (model only)
php bin/import objecttypes 1212,1214

# Import itineraries for given media objects
php bin/import itinerary 12345,12346

# Depublish or destroy
php bin/import depublish 12345,12346
php bin/import destroy 12345,12346

# Recalculate offers and calendar
php bin/import offer 12345,12346
php bin/import calendar 12345,12346

# Remove orphans, update tags, powerfilter, post-import, categories
php bin/import remove_orphans
php bin/import update_tags 1212
php bin/import powerfilter
php bin/import postimport
php bin/import postimport 12345,12346
php bin/import categories 123,124

# Utilities
php bin/import create_translations
php bin/import reset_insurances
php bin/import unlock
php bin/import help
```

**Travelshop wrapper (legacy / deprecated)**

The theme script `cli/import.php` runs the same command after bootstrapping WordPress and optionally registering a Redis post-import callback. Use only when you need that integration; otherwise use `bin/import`.

```bash
cd /path/to/wp-content/themes/travelshop

# Same subcommands as above, via legacy wrapper
php cli/import.php fullimport
php cli/import.php -c=pm-config-staging.php fullimport debug
php cli/import.php mediaobject 12345,12346,12347
php cli/import.php touristic 12345,12346
php cli/import.php fullimport_touristic
php cli/import.php objecttypes 1212,1214
php cli/import.php itinerary 12345,12346
php cli/import.php depublish 12345,12346
php cli/import.php destroy 12345,12346
php cli/import.php offer 12345,12346
php cli/import.php calendar 12345,12346
php cli/import.php remove_orphans
php cli/import.php update_tags 1212
php cli/import.php powerfilter
php cli/import.php postimport
php cli/import.php categories 123,124
php cli/import.php create_translations
php cli/import.php reset_insurances
php cli/import.php unlock
php cli/import.php help
```

---

## Index Mongo (MongoDB Best-Price Index)

The **Index Mongo** command manages the **MongoDB best-price search index**. This index is used by the SDK search API (e.g. `pm-*` query parameters) to resolve product lists, filters, and sorting. After a full import or after changing touristic/price data, the MongoDB index should be updated; for full reindex you can run `index_mongo.php all` (or the equivalent SDK bin script).

**SDK class:** `Pressmind\CLI\IndexMongoCommand`  
**Theme entry point:** `cli/index_mongo.php`  
**SDK bin:** `bin/index-mongo`

---

### Index Mongo: Subcommands & Parameter Reference

The first positional argument is the **subcommand**. For `mediaobject` and `destroy`, the second argument is required: a single ID or comma-separated list of media object IDs.

| Subcommand | Second argument | Description |
|------------|-----------------|-------------|
| `all` | — | (Re)creates the **entire** MongoDB index for all media objects (according to config). |
| `mediaobject` | `<id>[,<id>...]` | **Upserts** the index documents for the given media object ID(s). |
| `destroy` | `<id>[,<id>...]` | **Removes** the index documents for the given media object ID(s) from the MongoDB collections. |
| `indexes` | — | Creates/updates the **MongoDB collection indexes** (e.g. for performance) as defined by the SDK. |
| `flush` | — | **Flushes** all index collections (deletes all documents). |
| `create_collections` | — | Creates the configured **collections** if they do not exist. |
| `remove_temp_collections` | — | Deletes collections whose names have the `temp_*` prefix. |
| `help`, `--help`, `-h` | — | Prints usage and exits. |

---

### Index Mongo: Examples

**SDK CLI (recommended)**

```bash
# Rebuild full MongoDB index (e.g. after full import)
php bin/index-mongo all

# Update index for specific media objects
php bin/index-mongo mediaobject 12345,12346,12347

# Remove documents for specific media objects
php bin/index-mongo destroy 12345,12346

# Ensure collection indexes and collections exist
php bin/index-mongo indexes
php bin/index-mongo create_collections

# Flush all index data; remove temp collections
php bin/index-mongo flush
php bin/index-mongo remove_temp_collections

# Help
php bin/index-mongo help
```

**Travelshop wrapper (legacy / deprecated)**

```bash
cd /path/to/wp-content/themes/travelshop
php cli/index_mongo.php all
php cli/index_mongo.php mediaobject 12345,12346,12347
php cli/index_mongo.php destroy 12345,12346
php cli/index_mongo.php indexes
php cli/index_mongo.php create_collections
php cli/index_mongo.php flush
php cli/index_mongo.php remove_temp_collections
php cli/index_mongo.php help
```

---

## Other CLI Commands

The following commands are part of the SDK CLI and can be run via theme wrapper or SDK `bin` where applicable. Options and arguments are listed in parameter style.

---

### Cache Primer

**Purpose:** Outputs all pretty URLs for primary media types (one per line). Can be used to prime an HTTP cache (e.g. Redis) by requesting these URLs.

| Option / Argument | Description |
|-------------------|-------------|
| `--base-url=<url>` | Optional. Prepended to each pretty URL (e.g. `https://example.com`). Default: empty. |

**Examples:**

```bash
php cli/cache_primer.php
php cli/cache_primer.php --base-url=https://example.com
php bin/cache-primer --base-url=https://example.com
```

**Note:** When run from the Travelshop theme, the wrapper can pass `--base-url` from `WordPress\Tools::getSiteUrl()` so the SDK command stays CMS-agnostic.

---

### File Downloader

**Purpose:** Downloads files from the Media Object File data type where `download_successful = 0` (e.g. after import).

No options or arguments.

**Examples:**

```bash
php cli/file_downloader.php
php bin/file-downloader
```

---

### Fulltext Indexer

**Purpose:** Creates or updates the **MySQL fulltext search index** for media objects (all or by ID).

| Argument | Description |
|----------|-------------|
| *(none)* | Index **all** media objects. |
| `<id>[,<id>...]` | Index only the given media object ID(s). |

**Examples:**

```bash
php cli/fulltext_indexer.php
php cli/fulltext_indexer.php 12345,12346
php cli/fulltext_indexer.php help
```

---

### Index OpenSearch

**Purpose:** Manages the **OpenSearch** fulltext index and can run a search test.

| Subcommand | Arguments | Description |
|------------|------------|-------------|
| `all` | — | Creates/updates the OpenSearch index for all media objects. |
| `mediaobject` | `<id>[,<id>...]` | Upserts the index for the given media object ID(s). |
| `search` | `<term>` `[<language>]` | Searches the index for `<term>` (optional language code) and prints matching media object IDs. |
| `create_index_templates` | — | Creates index templates and reports current indexes. |
| `help` | — | Prints usage. |

**Examples:**

```bash
php cli/index_opensearch.php all
php cli/index_opensearch.php mediaobject 12345,12346
php cli/index_opensearch.php search "reise" de
php cli/index_opensearch.php create_index_templates
```

---

### Image Processor

**Purpose:** Downloads and processes images, creates derivatives, verifies storage (with optional skip), and can reset missing images for reprocessing.

| Argument / Option | Description |
|-------------------|-------------|
| *(none)* | Process all pending images, then run verification (storage scan and report). |
| `unlock` | Removes the image processor process lock. |
| `reset-missing` | Sets `download_successful=0` for images whose files are missing, so they are reprocessed on the next run. |
| `mediaobject` | Second argument: `<id>[,<id>...]`. Process only images for the given media object(s). |
| `skip-verification` | Skip the verification step after processing (no storage scan, no verification report). Useful for very large buckets when you only need to process pending images. |
| `--skip-verification` | Same as `skip-verification`. |

**Examples:**

```bash
php cli/image_processor.php
php cli/image_processor.php unlock
php cli/image_processor.php reset-missing
php cli/image_processor.php mediaobject 12345,12346
php cli/image_processor.php skip-verification
php cli/image_processor.php --skip-verification
php cli/image_processor.php mediaobject 12345,12346 skip-verification
```

For details on verification (streaming scan, progress output, report) see [Image Processor – Image Verification](image-processor.md#image-verification).

---

### Log Cleanup

**Purpose:** Cleans up old log entries (e.g. intended for a nightly cron job).

No options or arguments.

**Examples:**

```bash
php cli/log_cleanup.php
php bin/log-cleanup
```

---

### Rebuild Cache

**Purpose:** Rebuilds the object cache by removing and re-adding all media objects to the cache backend (e.g. Redis).

No options or arguments.

**Examples:**

```bash
php cli/rebuild_cache.php
php bin/rebuild-cache
```

---

### Rebuild Routes

**Purpose:** Rebuilds **pretty URLs** (routes) for all media objects in the database.

No options or arguments.

**Examples:**

```bash
php cli/rebuild_routes.php
php bin/rebuild-routes
```

---

### Reset

**Purpose:** **Drops all tables** in the configured MySQL database and **flushes** the configured MongoDB database. Use with extreme care (e.g. for a clean reinstall).

| Option | Description |
|--------|-------------|
| `--non-interactive`, `-n` | Do not prompt; requires `--confirm` to proceed. |
| `--confirm` | When used with `--non-interactive`, performs the reset without a prompt. |

**Examples:**

```bash
php cli/reset.php
# Interactive: type 'yes' when prompted

php bin/reset --non-interactive --confirm
```

---

### Touristic Orphans

**Purpose:** Finds **orphan** products: visible media objects that have no cheapest-price entries and therefore do not appear in search results.

| Option | Description |
|--------|-------------|
| `--object-types=<ids>` | Comma-separated object type IDs (default: from config). |
| `--visibility=<int>` | Visibility value (default: 30). |
| `--details=<id>` | Show details for a single media object ID. |
| `--stats-only` | Output only statistics, no per-object list. |
| `-n`, `--non-interactive` | No interactive prompts. |

**Examples:**

```bash
php bin/touristic-orphans
php bin/touristic-orphans --object-types=1212,1214 --visibility=30
php bin/touristic-orphans --details=12345
php bin/touristic-orphans --stats-only
```

---

### Database Integrity Check

**Purpose:** Checks database integrity (e.g. schema, required tables). See SDK `bin/database-integrity-check` for usage.

---

### Integrity Check

**Purpose:** Broader integrity check (custom path, env, app path, DB user, Mongo URI/DB). Used for diagnostics and deployment checks.

| Option | Description |
|--------|-------------|
| `--custom-path=<path>` | Path to Custom directory (default: `getcwd()/Custom`). |
| `--env=<env>` | Environment name. |
| `--app-path=<path>` | Application path (default: current working directory). |
| `--db-user=<user>` | Database user. |
| `--mongo-uri=<uri>` | MongoDB connection URI. |
| `--mongo-db=<name>` | MongoDB database name. |

---

## WordPress-Dependent Commands

The following commands **require a WordPress environment** (e.g. Travelshop theme with `WordPress\Tools::boot()`). They are documented in detail on the dedicated page:

**[CLI WordPress Tools](cli-wordpress-tools.md)** – Includes:

- **WordPress Tools (`Pressmind\CLI\WordPress\Tools`)** – Helper class: boot WordPress, get site URL, delete transients, send test email. **CMS-independent design:** the SDK does not depend on WordPress; these helpers are only used when explicitly called from a WordPress context (e.g. theme wrappers).
- **WordPress CLI commands** – Check Email, Migrate Site, Regenerate Images, Setup Beaver Builder, and scripts that use Tools (e.g. delete transients, cache primer with site URL).

For parameter reference and usage of these commands, see [CLI WordPress Tools](cli-wordpress-tools.md).
