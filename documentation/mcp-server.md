# MCP server — travel search and product data

This document describes the **Model Context Protocol (MCP)** integration in the Pressmind SDK: architecture, configuration, CLI, HTTP transport, tools, and deployment. MCP lets AI clients (ChatGPT Apps, Claude Desktop, Cursor, custom agents) query travel data through the **same search stack as Travelshop** (`Query::getResult()` — MongoDB, OpenSearch/Atlas when configured, MySQL for product details).

---

## Table of contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Dependencies](#dependencies)
4. [Bootstrap and registry](#bootstrap-and-registry)
5. [Programmatic use (`ServerFactory`)](#programmatic-use-serverfactory)
6. [CLI (`bin/mcp-server`)](#cli-binmcp-server)
7. [HTTP transport (SSE)](#http-transport-sse)
8. [URL configuration (`site_url`, `ibe_url`)](#url-configuration-site_url-ibe_url)
9. [Search configuration (`options['search']`)](#search-configuration-optionssearch)
10. [Tools reference](#tools-reference)
11. [Search parameter mapping](#search-parameter-mapping)
12. [Errors](#errors)
13. [Deployment](#deployment)
14. [Breaking changes](#breaking-changes)

---

## Overview

| Item | Description |
|------|-------------|
| **Namespace** | `Pressmind\MCP` |
| **Transport** | Provided by `php-mcp/server`: **stdio** (local) or **HTTP + SSE** (remote). |
| **Auth** | Not implemented in the SDK. Local stdio needs no auth; remote HTTPS should use your reverse proxy / IdP (OAuth 2.1 for ChatGPT Apps). |
| **Tools** | `search`, `semantic_search`, `fetch`, `get_categories`, `get_filter_options`, `get_calendar`, `get_cheapest_prices`, `get_touristic_options`, `get_starting_points` |

The SDK focuses on **business logic and tool handlers**. Thin host projects (e.g. `travelshop-chatgpt-app`) can add env-based bootstrap and long-running HTTP listeners.

---

## Architecture

```
Pressmind\MCP\
├── Bootstrap.php              # Load pm-config; Registry: config, db (no WordPress)
├── ServerFactory.php        # Build php-mcp Server + tools + BasicContainer
├── Service\
│   ├── SearchService.php    # Query::getResult(), category facets
│   └── ProductService.php   # MediaObject details, calendar, price matrix, touristic options, starting points
└── Tool\
    ├── SearchTool.php             # MCP tool: search
    ├── SemanticSearchTool.php     # MCP tool: semantic_search (OpenSearch k-NN / hybrid)
    ├── FetchTool.php              # MCP tool: fetch
    ├── CategoriesTool.php         # MCP tool: get_categories
    ├── FilterOptionsTool.php      # MCP tool: get_filter_options
    ├── CalendarTool.php           # MCP tool: get_calendar
    ├── CheapestPricesTool.php     # MCP tool: get_cheapest_prices
    ├── TouristicOptionsTool.php   # MCP tool: get_touristic_options
    └── StartingPointsTool.php     # MCP tool: get_starting_points
```

- **SearchService** maps high-level arguments to internal `pm-*` request keys, exposes category facets and touristic filter facets, and calls `Pressmind\Search\Query::getResult()` — the same search pipeline as Travelshop (MongoDB + OpenSearch/Atlas when configured). Optional **vector / hybrid** search uses OpenSearch k-NN (`semantic_search` tool or `search` with `semantic=true`) when `data.search_opensearch.vector.enabled` is set; see **Vector search (OpenSearch k-NN)** below.
- **ProductService** loads `MediaObject` for `fetch`, calendar payloads via `CalendarFilter` + `MediaObject::getCalendar()`, price matrices via `MediaObject::getCheapestPrices()` + `CheapestPrice` filters, extras/tickets/sightseeing via `Touristic\Booking\Package` relations, and outbound **starting points** via `Date` → `Transport` (way=1) → `Startingpoint` → `Startingpoint\Option` (incl. pickup + `zip_ranges`).
- **ServerFactory** registers tools with `php-mcp/server` and injects services through a `BasicContainer`.

---

## Dependencies

- **`php-mcp/server`** (^2.0): listed under **`require-dev`** and **`suggest`** in the SDK `composer.json`. Production projects that only run imports do not need it.
- To run MCP: `composer require php-mcp/server` in the consuming project, or `composer install` in the SDK clone (includes dev dependencies).
- **Runtime**: Valid `pm-config.php` with database credentials and `data.search_mongodb` (same as Travelshop). The search index (MongoDB, and OpenSearch when configured) must be built and maintained like for the shop.

---

## Bootstrap and registry

`Pressmind\MCP\Bootstrap::init($applicationPath, $pmConfigBasename, $env)`:

1. Resolves the config file: `{applicationPath}/{PM_CONFIG or pm-config.php}`.
2. Reads config via `Pressmind\Config`, sets `WEBSERVER_HTTP` and `ENV` if not already defined.
3. Opens a MySQL PDO connection and applies the same session tweaks as elsewhere (`sql_mode`, `group_concat_max_len`).
4. Registers on `Pressmind\Registry::getInstance()`: `config`, `config_adapter`, `db`.

It does **not** load WordPress. All search-relevant settings are passed through `ServerFactory::create($options)` (see below).

Call `Pressmind\Registry::clear()` before `Bootstrap::init()` if you reuse PHP in a long-lived worker and need a clean state.

---

## Programmatic use (`ServerFactory`)

```php
use Pressmind\MCP\Bootstrap;
use Pressmind\MCP\ServerFactory;
use PhpMcp\Server\Transports\HttpServerTransport;
use PhpMcp\Server\Transports\StdioServerTransport;

Pressmind\Registry::clear();
Bootstrap::init('/path/to/travelshop-theme', 'pm-config.php');

$server = ServerFactory::create([
    'name' => 'Pressmind Travel MCP',
    'version' => '1.0.0',
    'instructions' => 'Optional text sent to the client at initialize.',
    'site_url' => 'https://www.example.com',          // absolute product detail URLs
    'ibe_url'  => 'https://buchung.example.com',       // absolute booking links (IBE3)
    'search' => [
        'language_code' => 'de',
        'touristic_origin' => 0,
        'destination_category_field' => 'zielgebiet_default',
        'travel_type_category_field' => 'reiseart_default',
        'category_fields' => ['zielgebiet_default', 'reiseart_default', 'sterne_default'],
    ],
]);

// Stdio
$server->listen(new StdioServerTransport());

// HTTP (prefix → /{prefix}/sse and /{prefix}/message)
$server->listen(new HttpServerTransport('127.0.0.1', 8080, 'mcp'));
```

---

## CLI (`bin/mcp-server`)

| Option | Description |
|--------|-------------|
| `--application-path=DIR` | Directory containing `pm-config.php` (**required** unless `MCP_APPLICATION_PATH` is set). |
| `--pm-config=FILE` | Basename of config file (default: env `PM_CONFIG` or `pm-config.php`). |
| `--transport=stdio\|http` | Default: `stdio`. |
| `--host=HOST` | HTTP bind address (default `127.0.0.1`). |
| `--port=PORT` | HTTP port (default `8080`). |
| `--mcp-prefix=PREFIX` | URL prefix for SSE/message endpoints (default `mcp`). |
| `--site-url=URL` | Public website base URL. Fallback: `mcp.site_url` or `server.webserver_http` from pm-config. |
| `--ibe-url=URL` | IBE3 booking engine base URL. Fallback: `mcp.ibe_url`, `ib3.api_endpoint`, `ib3.endpoint` from pm-config, or env `TS_IBE3_BASE_URL`. |
| `--instructions=TEXT` | Optional server instructions capability. |
| `--help` | Usage summary. |

**Environment:** `PM_CONFIG`, `APP_ENV`, `MCP_APPLICATION_PATH`, `TS_IBE3_BASE_URL`.

When the package is installed as a dependency, the same script is available as `vendor/bin/mcp-server` if Composer exposes it from `pressmind/sdk`.

---

## HTTP transport (SSE)

The SDK uses `PhpMcp\Server\Transports\HttpServerTransport` (php-mcp 2.x).

Example with default prefix `mcp`:

| Method | URL | Role |
|--------|-----|------|
| GET | `http://127.0.0.1:8080/mcp/sse` | SSE stream for the MCP session |
| POST | `http://127.0.0.1:8080/mcp/message?clientId=...` | JSON-RPC messages |

Always place a **TLS** reverse proxy in production. For SSE, disable buffering and use a long read timeout (see [Deployment](#deployment)).

---

## URL configuration (`site_url`, `ibe_url`)

Product detail URLs and booking links are relative by default. To produce absolute URLs in MCP responses, set `site_url` and `ibe_url` in `ServerFactory::create($options)`.

| Option | Purpose | CLI flag | pm-config fallback | ENV fallback |
|--------|---------|----------|--------------------|--------------|
| `site_url` | Base URL for product detail pages (`url` in `fetch` response) | `--site-url` | `mcp.site_url` → `server.webserver_http` | — |
| `ibe_url` | Base URL for the IBE3 booking engine (`booking_url` in `fetch` response) | `--ibe-url` | `mcp.ibe_url` → `ib3.api_endpoint` → `ib3.endpoint` | `TS_IBE3_BASE_URL` |

In Travelshop projects the IBE URL is typically set via the `.env` file as `TS_IBE3_BASE_URL`. The CLI script reads it from pm-config first, then falls back to the environment variable.

**Note:** `MediaObject::getBookingLink()` in the SDK reads `$config['ib3']['endpoint']` from the Registry. Many Travelshop pm-configs do not set this key — the IBE URL comes from the Travelshop constant `TS_IBE3_BASE_URL` instead. The MCP layer resolves this by accepting `ibe_url` as an explicit factory option, independent of the ORM's config lookup.

---

## Search configuration (`options['search']`)

Search settings are passed as `options['search']` to `ServerFactory::create()`. They are injected into `SearchService` at construction time — **not** read from `pm-config.php` or PHP constants at runtime.

```php
$server = ServerFactory::create([
    'search' => [
        'language_code' => 'de',                  // Query::$language_code
        'touristic_origin' => 0,                  // Query::$touristic_origin
        'agency_id_price_index' => null,          // Query::$agency_id_price_index
        'group_keys' => null,                     // Query::$group_keys
        'calendar_show_departures' => false,       // Query::$calendar_show_departures
        'destination_category_field' => 'zielgebiet_default', // pm-c[field] for "destination" argument
        'travel_type_category_field' => 'reiseart_default',   // pm-c[field] for "travel_type" argument
        'category_fields' => ['zielgebiet_default', 'reiseart_default'], // indexed category field names (get_categories validation); omit to discover keys from facets
        'atlas' => [                              // MongoDB Atlas full-text search
            'active' => true,
            'definition' => 'default',            // Atlas search index name
        ],
    ],
]);
```

| Key | Type | Default | Maps to |
|-----|------|---------|---------|
| `language_code` | string | `null` | `Query::$language_code` |
| `touristic_origin` | int | `0` | `Query::$touristic_origin` |
| `agency_id_price_index` | mixed | `null` | `Query::$agency_id_price_index` |
| `group_keys` | mixed | `null` | `Query::$group_keys` |
| `calendar_show_departures` | bool | `false` | `Query::$calendar_show_departures` |
| `destination_category_field` | string | `'zielgebiet_default'` | Category field for `destination` → `pm-c[field]` |
| `travel_type_category_field` | string | `'reiseart_default'` | Category field for `travel_type` → `pm-c[field]` |
| `category_fields` | list of string | `[]` (discover at runtime) | Allowed `field_name` values for `get_categories`; when empty, `get_categories` without `field_name` lists keys from the filter run |
| `atlas.active` | mixed | `null` | `Query::$atlas_active` |
| `atlas.definition` | mixed | `null` | `Query::$atlas_definition` |

All values default to `null` / `0` / `false` when omitted. There are no implicit fallbacks to `pm-config.php` keys, `TS_*` constants, or any other external source.

---

## Tools reference

Tools return a **JSON string** (MCP content). Parse the string in the client to consume structured data.

### `search`

Full-text and faceted catalog search via `Query::getResult()` (MongoDB + OpenSearch/Atlas when configured).

**Arguments:**

| Argument | Type | Default | Description |
|----------|------|---------|-------------|
| `query` | string | null | Full-text term → `pm-t` |
| `destination` | string | null | Category id(s) for configured destination field → `pm-c` |
| `travel_type` | string | null | Category id(s) for configured travel type field → `pm-c` |
| `categories` | string | null | JSON object of extra `pm-c` fields, e.g. `{"sterne_default":"123"}`; merged before `destination` / `travel_type` (those override their keys) |
| `date_from` | string | null | Start date `YYYY-MM-DD` (use with `date_to`) → `pm-dr` |
| `date_to` | string | null | End date `YYYY-MM-DD` |
| `price_min`, `price_max` | int | null | Price range → `pm-pr` |
| `duration_min`, `duration_max` | int | null | Duration range → `pm-du` |
| `transport_type` | string | null | → `pm-tr` |
| `board_type` | string | null | → `pm-bt` |
| `object_type` | string | null | Comma-separated ids → `pm-ot` |
| `order` | string | null | Sort, e.g. `price-asc`, `date_departure-asc` → `pm-o` |
| `semantic` | bool | `false` | When `true`, runs vector/hybrid OpenSearch ranking (requires `data.search_opensearch.vector.enabled`; same as calling `semantic_search`) |
| `page` | int | 1 | Page index (1-based) |
| `page_size` | int | 10 | Page size (max 100) |
| `occupancy` | int | 2 | Persons per room |

**Response (object):**

- `results` — array of `{ id, title, text, url, image_url, price, duration, departure_date }`
- `total_result`, `current_page`, `pages`

When both `date_from` and `date_to` are set, the service sets default order `date_departure-asc` and `output` to `date_list` (same idea as date-range search in the shop).

---

### `semantic_search`

Same arguments and response shape as **`search`**, but ranking uses **OpenSearch k-NN** (and optional **hybrid** fusion with lexical scores). Requires:

- `data.search_opensearch.vector.enabled` (and indexed documents with `content_vector` after re-index),
- Provider credentials (e.g. `OPENAI_API_KEY` when `provider` is `openai`).

The service embeds `query`, resolves candidate `id_media_object` values from OpenSearch, then runs `Query::getResult()` with `pm-id` and `pm-o=list` so MongoDB preserves relevance order. Filters (`destination`, `categories`, date range, etc.) apply as for `search`.

---

### Vector search (OpenSearch k-NN)

Configuration lives under **`data.search_opensearch.vector`** (see `config.default.json`). Key fields:

| Key | Purpose |
|-----|---------|
| `enabled` | Index `knn_vector` and generate embeddings during OpenSearch indexing |
| `enabled_in_search` | Use hybrid/vector path inside the normal Travelshop / `Query` pipeline (when `pm-t` is present) |
| `search_mode` | `hybrid` (lexical + vector score fusion) or `vector` (k-NN only) |
| `provider` / `model` / `dimensions` | Embedding provider (`openai`, `ollama`) |
| `cache` | MongoDB embedding cache (`embedding_cache`, `query_embedding_cache` with TTL) |

Shop search with `enabled_in_search` does not require MCP; MCP adds **`semantic_search`** and **`search(..., semantic=true)`** for explicit semantic ranking.

---

### `fetch`

Load one product by Pressmind **`id_media_object`**.

**Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `id` | string | Numeric `id_media_object` |

**Response (object):**

- `id`, `title`, `text` (truncated plain text from description blocks), `url` (pretty URL)
- `metadata`: `code`, `id_object_type`, `booking_type`, `booking_link`, `booking_url` (IB3 link when derivable from cheapest price), `description_blocks` (normalized arrays), `recommendation_rate`

---

### `get_categories`

Category tree facets for any indexed category `field_name` (same keys as `pm-c` in the shop). When `field_name` is omitted, returns the list of known fields.

**Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `field_name` | string | Optional: e.g. `zielgebiet_default`, `reiseart_default`, `sterne_default`. Omit to get `available_fields` only. |
| `parent_id` | string | Optional: keep only rows where `id_parent` matches |

**Response (object):**

- Without `field_name`: `available_fields` — array of field name strings (from `options['search']['category_fields']`, or discovered from `Query::getResult()` facets when that list is empty).
- With `field_name`: `categories` — array of `{ id, name, level, id_parent, count_in_system, count_in_search }`.

If `category_fields` is configured and `field_name` is not in that list, the tool returns an error JSON object.

Data comes from `Query::getResult()` with `returnFiltersOnly=true` and `getFilters=true`.

**CLI:** `bin/mcp-server` fills `category_fields` from `data.search_mongodb.search.categories` in `pm-config.php` (union of keys across object types).

---

### `get_filter_options`

Touristic facets from the same filter run as the shop: board types, transport types, starting points, duration span, configured duration buckets, and price span.

**Arguments:** none.

**Response (object):**

- `board_types` — array of `{ id, name, count_in_system, count_in_search }`
- `transport_types` — same shape
- `startingpoint_options` — array of `{ id, city, count_in_system, count_in_search }`
- `duration` — `{ min, max, allowed_ranges }` (`allowed_ranges` from `search.touristic.duration_ranges` in config)
- `price` — `{ min, max }`

---

### `get_calendar`

Calendar / date-price payload for one **`id_media_object`**, via `MediaObject::getCalendar()`.

**Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `id` | string | `id_media_object` (required) |
| `month` | string | Optional `YYYY-MM` to narrow the normalized payload |
| `id_booking_package` | string | Calendar filter |
| `id_housing_package` | string | |
| `housing_package_code_ibe` | string | |
| `transport_type` | string | |
| `duration` | string | |
| `startingpoint_id_city` | string | |
| `airport` | string | |
| `agency` | string | |
| `housing_package_id_name` | string | |

Optional filter keys match `Pressmind\Search\CalendarFilter::initFromArray()` (same as GET parameters for `app-feed/calendar.php`). **`ProductService`** merges **`id`** (cheapest-price row id) into the filter input when you do not pass it, so a call with only `id_media_object` can succeed if that offer id is valid for `getCalendar()`.

Refine the calendar with the same parameters the shop would append to the calendar URL (`id_booking_package`, `transport_type`, `duration`, etc.) when multiple offers exist or the default cheapest context is wrong.

Products that are **booking_on_request**, or have only a **virtual** cheapest price, return an error instead of a calendar.

**Response (object):**

- `payload` — JSON-decoded structure from `getCalendar()` (optionally month-filtered)

---

### `get_cheapest_prices`

Price matrix for one product: rows from MySQL `pmt2core_cheapest_price_speed` via `MediaObject::getCheapestPrices()` with a `Pressmind\Search\CheapestPrice` filter (same semantics as the shop booking-offers AJAX). Each row includes a **`booking_url`** for that specific offer (IBE3 query string, prefixed with `ibe_url` when configured).

**Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `id` | string | `id_media_object` (required) |
| `duration_from`, `duration_to` | int | Optional duration range |
| `date_from`, `date_to` | string | Optional `YYYY-MM-DD` departure range |
| `price_min`, `price_max` | float | Optional total price range |
| `occupancy` | int | Optional persons / room occupancy filter |
| `transport_type` | string | Optional transport code (e.g. bus vs flight) |
| `id_booking_package` | string | Optional package id |
| `id_housing_package` | string | Optional housing package id |
| `startingpoint_id_city` | string | Optional starting-point city id |
| `airport` | string | Optional departure airport code |
| `order` | string | `price-asc` (default), `date-asc` / `date_departure-asc`, `price-desc` |
| `limit` | int | Max rows (default 50, max 200) |

**Response (object):**

- `id_media_object`, `total`
- `filter_options` — facets from `MediaObject::getCheapestPricesOptions()` (`durations`, `transport_types`, `option_occupancy`, airports, date span, `count`). May contain `_error` if the aggregation query fails (e.g. empty index).
- `prices` — array of rows with `id`, prices, dates, option/housing/transport/startingpoint fields, `state`, `included_options_description`, `booking_url`

Virtual cheapest-price rows are omitted. Configure **`ibe_url`** / **`site_url`** via `ServerFactory` or CLI so `booking_url` is absolute.

---

### `get_touristic_options`

Lists **extras**, **tickets**, and **sightseeing** options from `Touristic\Booking\Package` (`extras`, `tickets`, `sightseeings` relations). Each row includes price fields, `required`, `required_group`, `selection_type` (e.g. `MIN_ONE_OF_GROUP`, `EXACTLY_ONE_OF_GROUP`, `OPTIONAL`), and `auto_book`. **`required_groups`** aggregates option ids by `required_group` for mandatory-choice rules.

**Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `id` | string | `id_media_object` (required) |
| `id_booking_package` | string | Optional: only options for this package |
| `type` | string | Optional: `extra`, `ticket`, or `sightseeing`; omit for all three |

**Response (object):**

- `id_media_object`
- `options` — array of normalized option records
- `required_groups` — map of group key → `{ selection_type, option_ids[] }`

---

### `get_starting_points`

Boarding / **starting points** for **outbound** transports (`way === 1`), aligned with `MediaObject::getAllAvailableTransports()` / IB3 bootstrap: each row ties a future `Touristic\Date` to a `Touristic\Transport`, the linked `Touristic\Startingpoint`, and all **`starting_point_options`** (`Touristic\Startingpoint\Option`). **Pickup / Haustür** options are marked with **`is_pickup_service`**; valid postal areas use **`zip_ranges`** (`from` / `to`) and optionally **`zip_validity_area`**.

**Arguments:**

| Argument | Type | Description |
|----------|------|-------------|
| `id` | string | `id_media_object` (required) |
| `id_booking_package` | string | Optional: only dates belonging to this package |

**Response (object):**

- `id_media_object`, `id_booking_package_filter`
- `entries` — array of `{ id_date, id_booking_package, transport, starting_point, starting_point_options[] }`  
  - `transport`: id, type, way, code, `dont_use_for_offers`, times, `id_starting_point`  
  - `starting_point`: id, code, name, text (or `null` if missing)  
  - `starting_point_options`: normalized options incl. `is_pickup_service`, `zip_ranges`, pickup address fields

Only **future** departures are included (`getAllAvailableDates()`), same scope as the cached transport list on `MediaObject`.

---

## Search parameter mapping

| MCP / high-level | Internal request key | Notes |
|------------------|----------------------|--------|
| `query` | `pm-t` | Full-text |
| `categories` (JSON) | `pm-c[...]` | Arbitrary category fields; merged first |
| `destination` | `pm-c[<destination_field>]` | Field from `destination_category_field` (overrides JSON for that key) |
| `travel_type` | `pm-c[<travel_type_field>]` | Field from `travel_type_category_field` (overrides JSON for that key) |
| `date_from` + `date_to` | `pm-dr` | Normalized to `YYYYMMDD-YYYYMMDD` |
| `price_min` + `price_max` | `pm-pr` | |
| `duration_min` + `duration_max` | `pm-du` | |
| `transport_type` | `pm-tr` | |
| `board_type` | `pm-bt` | |
| `object_type` | `pm-ot` | |
| `order` | `pm-o` | |
| `page`, `page_size` | `pm-l` | `page,page_size` |

---

## Errors

On failure, tools return JSON with:

```json
{ "error": true, "message": "…" }
```

Examples: invalid id, Mongo/MySQL connectivity, missing calendar filter, booking-on-request product.

---

## Deployment

### How the server works

`bin/mcp-server --transport=http` starts a **long-running PHP process** (ReactPHP event loop) that binds a TCP socket (e.g. `127.0.0.1:8080`). It does **not** use PHP-FPM or Apache — it is a standalone daemon.

An AI client connects in two steps:

1. **`GET /mcp/sse`** — opens a long-lived Server-Sent Events stream (session).
2. **`POST /mcp/message?clientId=…`** — sends JSON-RPC tool calls through that session.

The process stays in memory, holds the database connection, and answers tool calls directly.

### What is Supervisor?

[Supervisor](http://supervisord.org/) is a process manager for Linux. It starts programs automatically, restarts them if they crash, and collects their log output — similar to systemd service units but simpler to configure.

The MCP server is a **long-running PHP process** (not a web request). Without Supervisor (or an equivalent) the process would stop as soon as the terminal session ends, or silently die on an unrecoverable error. Supervisor ensures:

- The process **starts on boot** (`autostart=true`).
- It **restarts automatically** if it exits or crashes (`autorestart=true`).
- stdout/stderr go to **log files** for debugging.

**Installation** (Debian/Ubuntu):

```bash
apt install supervisor
systemctl enable supervisor
```

**Config files** live in `/etc/supervisor/conf.d/*.conf`. After adding or changing a file:

```bash
supervisorctl reread
supervisorctl update
```

**Useful commands:**

| Command | Description |
|---------|-------------|
| `supervisorctl status` | Show all managed programs and their state |
| `supervisorctl start mcp-example` | Start a program |
| `supervisorctl stop mcp-example` | Stop a program |
| `supervisorctl restart mcp-example` | Restart a program |
| `supervisorctl tail -f mcp-example stderr` | Follow error log output |

### Subdomain vs. path on existing domain

A separate subdomain (`mcp.example.com`) is **not required**. You can serve MCP from a path on the existing website domain instead (`example.com/mcp/`). This is often simpler because no additional DNS record or SSL certificate is needed.

**How it works with WordPress / PHP-FPM:** nginx matches the longest `location` prefix first. A `location /mcp/` block takes priority over the catch-all `location /` that passes requests to PHP-FPM (WordPress). The MCP requests never reach WordPress at all.

**Option A — path on the existing domain (recommended):**

Add a `location /mcp/` block to the **existing** nginx vhost for the website. No new `server` block, no new certificate.

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    # ... existing SSL, root, WordPress location blocks ...

    # MCP — proxied to the long-running PHP process, bypasses PHP-FPM entirely
    location /mcp/ {
        proxy_pass http://127.0.0.1:8080/mcp/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 86400s;
    }

    # WordPress (existing) — only handles requests NOT matched above
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
}
```

Public endpoint: `https://example.com/mcp/sse`

**Option B — dedicated subdomain:**

Separate `server` block with its own certificate. Useful when you want to isolate MCP traffic or apply different rate limits / firewall rules.

```nginx
server {
    listen 443 ssl http2;
    server_name mcp.example.com;

    ssl_certificate     /etc/letsencrypt/live/mcp.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mcp.example.com/privkey.pem;

    location /mcp/ {
        proxy_pass http://127.0.0.1:8080/mcp/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 86400s;
    }
}
```

Public endpoint: `https://mcp.example.com/mcp/sse`

### Single-site deployment

1. Run the HTTP transport behind **Supervisor**, binding to `127.0.0.1`.
2. Add the nginx `location /mcp/` block (Option A or B above).
3. For SSE: `proxy_buffering off;`, `proxy_cache off;`, long `proxy_read_timeout`.

Example **Supervisor** program:

```ini
[program:mcp-example]
command=php /var/www/vhosts/example.com/development/vendor/pressmind/sdk/bin/mcp-server --application-path=/var/www/vhosts/example.com/development/wp-content/themes/travelshop --transport=http --host=127.0.0.1 --port=8080
directory=/var/www/vhosts/example.com/development
autostart=true
autorestart=true
user=www-data
```

### Multi-site deployment (shared server)

Each Travelshop site has its own `pm-config.php` (database, MongoDB, search config). Every site therefore needs **its own MCP process** on a **unique port**. nginx maps each public domain to its local port.

**Port assignment example:**

| Site | Application path | Port | Public URL |
|------|-----------------|------|------------|
| site-a.com | `/var/www/vhosts/site-a.com/development/wp-content/themes/…` | 8080 | `https://site-a.com/mcp/sse` |
| site-b.com | `/var/www/vhosts/site-b.com/development/wp-content/themes/…` | 8081 | `https://site-b.com/mcp/sse` |
| site-c.com | `/var/www/vhosts/site-c.com/development/wp-content/themes/…` | 8082 | `https://site-c.com/mcp/sse` |

**Supervisor** — one `[program:…]` block per site:

```ini
[program:mcp-site-a]
command=php /var/www/vhosts/site-a.com/development/vendor/pressmind/sdk/bin/mcp-server --application-path=/var/www/vhosts/site-a.com/development/wp-content/themes/travelshop --transport=http --host=127.0.0.1 --port=8080
directory=/var/www/vhosts/site-a.com/development
autostart=true
autorestart=true
user=www-data

[program:mcp-site-b]
command=php /var/www/vhosts/site-b.com/development/vendor/pressmind/sdk/bin/mcp-server --application-path=/var/www/vhosts/site-b.com/development/wp-content/themes/travelshop --transport=http --host=127.0.0.1 --port=8081
directory=/var/www/vhosts/site-b.com/development
autostart=true
autorestart=true
user=www-data

[program:mcp-site-c]
command=php /var/www/vhosts/site-c.com/development/vendor/pressmind/sdk/bin/mcp-server --application-path=/var/www/vhosts/site-c.com/development/wp-content/themes/travelshop --transport=http --host=127.0.0.1 --port=8082
directory=/var/www/vhosts/site-c.com/development
autostart=true
autorestart=true
user=www-data
```

**nginx** — add a `location /mcp/` block to each site's **existing** vhost (no subdomain needed):

```nginx
# In the existing site-a.com server block:
location /mcp/ {
    proxy_pass http://127.0.0.1:8080/mcp/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
}

# In the existing site-b.com server block:
location /mcp/ {
    proxy_pass http://127.0.0.1:8081/mcp/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
}
```

Each process is completely isolated: own port, own database, own search index. Processes do not share state.

### Plesk environments

Plesk manages nginx configs and overwrites them on domain changes. Do **not** edit the generated `nginx.conf` directly. The setup works on Plesk servers with the following adjustments.

**PHP binary path:** Plesk installs PHP under `/opt/plesk/php/`. Use the full path in Supervisor configs:

```
/opt/plesk/php/8.2/bin/php
```

**nginx directives — two options:**

1. **Plesk UI (recommended):** Domains → example.com → Apache & nginx Settings → *Additional nginx directives*. Paste the `location /mcp/ { … }` block there. It survives Plesk rebuilds.
2. **Include file:** Create `/var/www/vhosts/system/<domain>/conf/vhost_nginx.conf` with the `location` block. This file is not overwritten by Plesk.

Content for either option (same as single-site, only the `location` block — no `server` wrapper):

```nginx
location /mcp/ {
    proxy_pass http://127.0.0.1:8080/mcp/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
}
```

**User:** Plesk runs each domain under its own system user (e.g. `example_com`), not `www-data`. Set the Supervisor `user` to the domain owner:

```ini
[program:mcp-example]
command=/opt/plesk/php/8.2/bin/php /var/www/vhosts/example.com/development/vendor/pressmind/sdk/bin/mcp-server --application-path=/var/www/vhosts/example.com/development/wp-content/themes/travelshop --transport=http --host=127.0.0.1 --port=8080
directory=/var/www/vhosts/example.com/development
autostart=true
autorestart=true
user=example_com
```

Find the actual username with `ls -la /var/www/vhosts/example.com/` or in Plesk under Domains → Hosting Settings.

**Supervisor:** Not a Plesk component — install independently (`apt install supervisor`). Plesk does not interfere with it.

**Firewall / ports:** Binding to `127.0.0.1` keeps the port internal. The Plesk firewall module does not need changes — traffic enters through nginx on port 443 (managed by Plesk, including Let's Encrypt certificates).

### OAuth 2.1 (ChatGPT)

The SDK does not validate JWTs. Host [protected resource metadata](https://developers.openai.com/apps-sdk/build/auth) at `/.well-known/oauth-protected-resource` on your public origin and validate `Authorization: Bearer` at the edge (nginx `auth_request`, API gateway, or a small wrapper).

---

## AI discovery (`.well-known/mcp.json`, `llms.txt`)

Public MCP servers should be **discoverable** by AI clients without manual configuration. The Travelshop theme ships two PHP endpoints that generate discovery files dynamically from `config-theme.php`:

| URL | File | Purpose |
|-----|------|---------|
| `/.well-known/mcp.json` | `well-known/mcp-json.php` | MCP Server Card ([SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127)). Tells AI clients where the MCP endpoint is, which tools are available, and what the server does. |
| `/llms.txt` | `well-known/llms-txt.php` | AI discovery file ([llms.txt spec v1.1](https://www.ai-visibility.org.uk/specifications/llms-txt/)). Human- and machine-readable Markdown describing the travel shop for LLM crawlers. |

Both files are served through WordPress rewrite rules defined in `functions/rewrite_rules.php`. After deployment, flush rewrite rules once:

```bash
wp rewrite flush --hard --allow-root
```

### config-theme.php: `TS_MCP_DISCOVERY`

Shop-specific discovery metadata is configured in **`config-theme.php`** (not `pm-config.php`) via the `TS_MCP_DISCOVERY` constant. This follows the same pattern as other theme-level constants (`TS_SEARCH`, `TS_FILTERS`, `SITE_URL`, etc.).

All keys are optional. The endpoints derive sensible defaults from `SITE_URL` / `$_SERVER['HTTP_HOST']` when keys are missing.

```php
define('TS_MCP_DISCOVERY', [
    // Server Card (.well-known/mcp.json)
    'name'              => 'pressmind-travel-example-com',
    'title'             => 'Example Reisen – Travel Search',
    'description'       => 'Search and book package holidays, city trips, and cruises.',
    'version'           => '1.0.0',
    'endpoint'          => 'https://www.example.com/mcp',
    'icon_url'          => 'https://www.example.com/favicon.png',
    'documentation_url' => 'https://www.example.com',

    // llms.txt
    'site_name'         => 'Example Reisen',
    'site_description'  => 'Online travel shop for package holidays, city trips, and cruises in Europe and worldwide. Search, compare, and book directly.',
    'contact_email'     => 'info@example.com',
    'contact_phone'     => '+49 123 456789',
    'key_information'   => [
        'Over 5,000 travel products from 20+ tour operators',
        'Destinations: Europe, Mediterranean, North Africa, worldwide',
        'Online booking with real-time availability',
    ],
]);
```

The MCP server runtime settings (`site_url`, `ibe_url`) remain in **`pm-config.php`** under `mcp.*` (see [URL configuration](#url-configuration-site_url-ibe_url)).

### Additional discovery mechanisms

Beyond the files shipped with the theme, consider these complementary steps:

| Mechanism | Purpose | How |
|-----------|---------|-----|
| **MCP Registry** | Central directory at [modelcontextprotocol.io/registry](https://modelcontextprotocol.io/registry) | Publish `server.json` via `mcp-publisher` CLI |
| **DNS TXT record** | Machine-level discovery ([IETF draft](https://www.ietf.org/archive/id/draft-serra-mcp-discovery-uri-02.txt)) | `_mcp.example.com. TXT "v=mcp1 url=https://example.com/mcp"` |
| **Schema.org / JSON-LD** | Google AI Overviews, Bing Copilot | Add `TouristTrip`, `LodgingReservation` structured data to product pages |
| **robots.txt** | Allow AI crawlers | Ensure `User-agent: *` does not block `/llms.txt` or `/.well-known/` |

---

## Breaking changes

**MCP tool names:** `get_destinations` and `get_travel_types` were removed. Use **`get_categories`** with `field_name` set to your destination or travel type field (see `destination_category_field` / `travel_type_category_field`), or call **`get_categories`** without `field_name` to list `available_fields`.

**`get_insurances`:** This tool was removed. Clients that registered it must drop the tool from their MCP configuration.

Non-MCP SDK consumers are unaffected. Projects that do not install `php-mcp/server` are unaffected.

---

## See also

- [CHANGELOG.md](CHANGELOG.md) — release notes for MCP-related changes
- `src/Pressmind/MCP/` — source code
- `src/Pressmind/Search/CalendarFilter.php` — calendar filter keys for `get_calendar`
