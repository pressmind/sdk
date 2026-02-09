# MongoDB Search API – Query Parameters (`pm-*`)

[← Back to Architecture](architecture.md) | [→ Index Configuration](search-mongodb-index-configuration.md) | [→ OpenSearch Integration](search-opensearch.md)

---

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Collection Naming](#collection-naming)
- [Parameter Reference](#parameter-reference)
  - [Core Parameters](#core-parameters)
    - [`pm-ot` – Object Type](#pm-ot--object-type)
    - [`pm-id` – Media Object ID](#pm-id--media-object-id)
    - [`pm-co` – Code](#pm-co--code)
  - [Touristic Filters](#touristic-filters)
    - [`pm-pr` – Price Range](#pm-pr--price-range)
    - [`pm-dr` – Departure Date Range](#pm-dr--departure-date-range)
    - [`pm-du` – Duration Range](#pm-du--duration-range)
    - [`pm-ho` – Occupancy (Adults)](#pm-ho--occupancy-adults)
    - [`pm-hoc` – Occupancy (Children)](#pm-hoc--occupancy-children)
    - [`pm-bt` – Board Type](#pm-bt--board-type)
    - [`pm-tr` – Transport Type](#pm-tr--transport-type)
    - [`pm-sc` – Starting Point City](#pm-sc--starting-point-city)
  - [Category & Content Filters](#category--content-filters)
    - [`pm-c[field]` – Category](#pm-cfield--category)
    - [`pm-t` – Fulltext Search](#pm-t--fulltext-search)
    - [`pm-gr` – Group](#pm-gr--group)
  - [Status Filters](#status-filters)
    - [`pm-so` – Sold Out](#pm-so--sold-out)
    - [`pm-ir` – Is Running](#pm-ir--is-running)
    - [`pm-gu` – Guaranteed Departures](#pm-gu--guaranteed-departures)
    - [`pm-sp` – Sales Priority](#pm-sp--sales-priority)
  - [Special Parameters](#special-parameters)
    - [`pm-url` – URL Match](#pm-url--url-match)
    - [`pm-pf` – Powerfilter](#pm-pf--powerfilter)
    - [`pm-loc[field]` – Location / Geo Search](#pm-locfield--location--geo-search)
  - [Pagination & Sorting](#pagination--sorting)
    - [`pm-l` – Pagination (Limit)](#pm-l--pagination-limit)
    - [`pm-o` – Sort Order](#pm-o--sort-order)
- [Response Structure](#response-structure)
  - [Response Fields](#response-fields)
- [Complete Query Examples](#complete-query-examples)
- [MongoDB Aggregation Pipeline](#mongodb-aggregation-pipeline)

---

## Overview

The MongoDB search engine is the SDK's primary search interface for touristic products. It uses a parameter-based query language with the `pm-` prefix that translates HTTP request parameters into a MongoDB aggregation pipeline.

All parameters are parsed by `Search\Query::fromRequest()` and converted into typed `Condition` objects that produce MongoDB query fragments.

The `pm-` prefix design allows parameters to be passed directly as `$_GET` query parameters in any URL. This makes it trivial to build search pages, filter links, and bookmarkable search results without any backend glue code:

```
https://www.my-travel-site.com/search?pm-ot=123&pm-c[zielgebiet_default]=5d41402abc4b&pm-pr=500-2000&pm-du=7-14&pm-ho=2&pm-o=price-asc&pm-l=1,12
```

In PHP, the parameters are picked up automatically from the request:

```php
// All pm-* parameters are read directly from $_GET
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;

$QueryFilter = new Filter();
$QueryFilter->request = $_GET;       // Pass the raw $_GET array with pm-* params
$QueryFilter->occupancy = 2;
$QueryFilter->page_size = 12;
$QueryFilter->getFilters = true;     // Also return filter aggregations (categories, ranges, etc.)

$result = Query::getResult($QueryFilter);
// $result contains: items, categories, transport_types, board_types,
// price_min/max, duration_min/max, departure_min/max, pagination, etc.
```

This means any `<a>` link, `<form>`, or JavaScript `fetch()` call can construct a fully functional search query simply by assembling `pm-*` URL parameters – no API wrapper, no POST body, no serialization needed.

> **Note:** `Query::getResult()` internally calls `Query::fromRequest()` to parse the `pm-*` parameters and then delegates to `Search\MongoDB` for query execution. It returns a fully transformed, template-ready result array. See [Real-World Examples → Pattern 2](real-world-examples.md#pattern-2-query--sdk-search-pipeline) for the complete result structure.

---

## How It Works

```
HTTP Request with pm-* parameters ($_GET)
        │
        ▼
  Query::getResult(Filter)          ← Recommended entry point
        │ Configures search via Filter DTO (request, occupancy, page_size, etc.)
        │
        ├─ Query::fromRequest()
        │    │ Parses pm-* parameters into Condition objects
        │    ▼
        │  MongoDB::buildQuery()
        │    │ Builds aggregation pipeline:
        │    │   $match → $addFields → $project → $sort → $facet
        │    ▼
        │  MongoDB::getResult()
        │    │ Executes pipeline against collection
        │    │   Collection name: {prefix}{language}_origin_{origin}
        │    ▼
        │  Raw MongoDB documents
        │
        ├─ Document transformation (dates, prices, metadata → template-ready arrays)
        ├─ Filter aggregation (categories, ranges, counts)
        ▼
  Complete result array (items, filters, pagination, debug info)
```

---

## Collection Naming

MongoDB collections follow a strict naming convention:

```
best_price_search_based_{language}_origin_{origin}
```

**Examples:**

| Collection Name | Language | Origin |
|---|---|---|
| `best_price_search_based_de_origin_0` | German | Default market |
| `best_price_search_based_en_origin_0` | English | Default market |
| `best_price_search_based_de_origin_1` | German | Market 1 |
| `best_price_search_based_de_origin_0_agency_5` | German | Default, Agency 5 |

A separate `description_{language}_origin_{origin}` collection stores description data (headline, subline, images) to keep search documents lean.

---

## Parameter Reference

### Core Parameters

#### `pm-ot` – Object Type

| Property | Value |
|---|---|
| **Type** | Integer or comma-separated integers |
| **Required** | Yes |
| **Condition Class** | `Search\Condition\MongoDB\ObjectType` |

Filters by media object type ID. This is typically the first filter applied.

**MongoDB Query:**

```javascript
// Single type
{ "id_object_type": 123 }

// Multiple types
{ "id_object_type": { "$in": [123, 456] } }
```

**Examples:**

```
pm-ot=123            // Single object type
pm-ot=123,456        // Multiple object types
```

---

#### `pm-id` – Media Object ID

| Property | Value |
|---|---|
| **Type** | Integer or comma-separated integers |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\MediaObject` |

Filters by specific media object IDs. Supports **negative IDs for exclusion**.

**MongoDB Query:**

```javascript
// Include specific IDs
{ "id_media_object": { "$in": [100, 200, 300] } }

// Exclude specific IDs (negative values)
{ "id_media_object": { "$nin": [100, 200] } }
```

**Examples:**

```
pm-id=12345                  // Single object
pm-id=100,200,300            // Multiple objects
pm-id=-100,-200              // Exclude objects 100 and 200
pm-id=100,200,300,-400       // Include 100,200,300 but exclude 400
```

> When combined with `pm-o=list`, the results preserve the order of the given IDs.

---

#### `pm-co` – Code

| Property | Value |
|---|---|
| **Type** | String or comma-separated strings |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\Code` |

Filters by product code(s). Can optionally use regex matching.

**MongoDB Query:**

```javascript
// Exact match
{ "code": { "$in": ["ABC123", "DEF456"] } }

// Regex match (when asRegex=true)
{ "code": { "$regex": "ABC.*", "$options": "i" } }
```

**Examples:**

```
pm-co=ABC123                 // Single code
pm-co=ABC123,DEF456          // Multiple codes
```

> When combined with `pm-o=list`, the results preserve the order of the given codes.

---

### Touristic Filters

#### `pm-pr` – Price Range

| Property | Value |
|---|---|
| **Type** | String: `{min}-{max}` |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\PriceRange` |

Filters by total price range.

**MongoDB Query:**

```javascript
{
  "prices": {
    "$elemMatch": {
      "price_total": { "$gte": 500, "$lte": 2000 }
    }
  }
}
```

**Examples:**

```
pm-pr=500-2000               // Between 500 and 2000
pm-pr=0-1000                 // Up to 1000
pm-pr=1500-99999             // From 1500 upward
```

---

#### `pm-dr` – Departure Date Range

| Property | Value |
|---|---|
| **Type** | String (multiple formats, see below) |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\DateRange` |

Filters by departure date range. Also applies a second-stage filter to reduce the `date_departures` array within each price to only matching dates.

**Supported Formats:**

| Format | Pattern | Description |
|---|---|---|
| Date range | `YYYYMMDD-YYYYMMDD` | Absolute date range |
| Exact date | `YYYYMMDD` | Single departure day (from only, no upper bound) |
| Relative offset | `{days}` | From today to today + N days |
| Relative range | `{days}-{days}` | Relative range from today (supports `+`/`-` prefix) |

**MongoDB Query:**

```javascript
{
  "prices": {
    "$elemMatch": {
      "date_departures": {
        "$elemMatch": {
          "$gte": "2026-06-01",
          "$lte": "2026-08-31"
        }
      }
    }
  }
}
```

**Parsing Logic:**

```php
// src/Pressmind/Search/Query.php:848-874
// Pattern 1: YYYYMMDD-YYYYMMDD → absolute date range
preg_match('/^([0-9]{8})\-([0-9]{8})$/', $str)

// Pattern 2: YYYYMMDD → exact departure day
preg_match('/^([0-9]{8})$/', $str)

// Pattern 3: N → today to today + N days
preg_match('/^([\+\-]?[0-9]+)$/', $str)

// Pattern 4: N-M → today + N days to today + M days
preg_match('/^([\+\-]?[0-9]+)\-([\+\-]?[0-9]+)$/', $str)
```

**Examples:**

```
pm-dr=20260601-20260831       // Summer 2026 (June 1 to August 31)
pm-dr=20261220-20270105       // Christmas/New Year
pm-dr=20260715                // Exact departure day July 15, 2026
pm-dr=30                      // From today to 30 days from now
pm-dr=7-90                    // From 7 days to 90 days from now
pm-dr=0-365                   // From today to 1 year from now
```

---

#### `pm-du` – Duration Range

| Property | Value |
|---|---|
| **Type** | String: `{min}-{max}` |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\DurationRange` |

Filters by travel duration (nights).

**MongoDB Query:**

```javascript
{
  "prices": {
    "$elemMatch": {
      "duration": { "$gte": 7, "$lte": 14 }
    }
  }
}
```

**Examples:**

```
pm-du=3-7                    // 3 to 7 nights
pm-du=7-14                   // 1 to 2 weeks
pm-du=1-3                    // Short breaks
```

---

#### `pm-ho` – Occupancy (Adults)

| Property | Value |
|---|---|
| **Type** | Integer or comma-separated integers |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\Occupancy` |

Filters by number of adults.

**MongoDB Query:**

```javascript
{
  "prices": {
    "$elemMatch": {
      "occupancy": { "$in": [2] }
    }
  }
}
```

**Examples:**

```
pm-ho=2                      // 2 adults
pm-ho=1,2                    // 1 or 2 adults
```

---

#### `pm-hoc` – Occupancy (Children)

| Property | Value |
|---|---|
| **Type** | Integer or comma-separated integers |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\Occupancy` |

Filters by number of children. Combined with `pm-ho` in the same `Occupancy` condition.

**Examples:**

```
pm-hoc=1                     // 1 child
pm-ho=2&pm-hoc=1             // 2 adults + 1 child
```

---

#### `pm-bt` – Board Type

| Property | Value |
|---|---|
| **Type** | String or comma-separated strings |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\BoardType` |

Filters by board/meal type.

**MongoDB Query:**

```javascript
{
  "prices": {
    "$elemMatch": {
      "option_board_type": { "$in": ["HB", "AI"] }
    }
  }
}
```

**Examples:**

```
pm-bt=HB                     // Half board
pm-bt=AI                     // All-inclusive
pm-bt=HB,FB,AI               // Half board, full board, or all-inclusive
```

---

#### `pm-tr` – Transport Type

| Property | Value |
|---|---|
| **Type** | String or comma-separated strings |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\TransportType` |

Filters by transport/travel type. Values are fixed German enum strings from the pressmind PIM system.

**Allowed Values:**

| Value | Description |
|---|---|
| `BUS` | Bus |
| `PKW` | Self-drive / Car |
| `FLUG` | Flight |
| `SCHIFF` | Ship / Cruise |
| `BAHN` | Train / Rail |

> **Note:** The SDK ORM defines the transport type key as `BAH` (3 characters) in `Transport::getValidTypes()`, while the PIM and search index typically use `BAHN` (4 characters). Both values should be handled. See [Transport Entity](Touristic/Transport.md#type).

**MongoDB Query:**

```javascript
{
  "prices": {
    "$elemMatch": {
      "transport_type": { "$in": ["FLUG", "BUS"] }
    }
  }
}
```

**Examples:**

```
pm-tr=FLUG                   // Flight only
pm-tr=BUS                    // Bus only
pm-tr=FLUG,BUS               // Flight or bus
pm-tr=SCHIFF                 // Ship / Cruise only
pm-tr=BAHN                   // Train only
```

---

#### `pm-sc` – Starting Point City

| Property | Value |
|---|---|
| **Type** | Integer or comma-separated integers |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\StartingPointOptionCity` |

Filters by departure city ID.

**MongoDB Query:**

```javascript
{
  "prices.startingpoint_option.id_city": { "$in": [123, 456] }
}
```

Also applies a post-match stage to filter the prices array down to matching starting points.

**Examples:**

```
pm-sc=123                    // Departure from city 123
pm-sc=123,456,789            // Multiple departure cities
```

---

### Category & Content Filters

#### `pm-c[field]` – Category

| Property | Value |
|---|---|
| **Type** | String(s) with special operators |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\Category` |

Filters by category tree items. The `field` part of the parameter name specifies the category variable name. The `id_item` values are **MD5 hashes or GUIDs** (alphanumeric strings, including hyphens and underscores), not integers.

**Operators:**

| Operator | Meaning | Example |
|---|---|---|
| `,` (comma) | OR – any of these | `pm-c[land]=a1b2c3,d4e5f6` |
| `+` (plus) | AND – all of these | `pm-c[land]=a1b2c3+d4e5f6` |

> **Note:** The `+` sign is a reserved character in URLs. In query strings, use `%2B` instead of `+` (or submit via POST/form encoding which handles this automatically).

**MongoDB Query:**

```javascript
// OR: Any of these categories
{
  "$or": [
    { "categories": { "$elemMatch": { "field_name": "land_default", "id_item": "a1b2c3d4e5f6" } } },
    { "categories": { "$elemMatch": { "field_name": "land_default", "id_item": "f6e5d4c3b2a1" } } }
  ]
}

// AND: All of these categories
{
  "$and": [
    { "categories": { "$elemMatch": { "field_name": "land_default", "id_item": "a1b2c3d4e5f6" } } },
    { "categories": { "$elemMatch": { "field_name": "land_default", "id_item": "f6e5d4c3b2a1" } } }
  ]
}
```

**ID Item Format:**

The `id_item` is a string identifier from the pressmind PIM system. It can be:
- An **MD5 hash** (e.g. `5d41402abc4b2a76b9719d911017c592`)
- A **GUID/UUID** (e.g. `550e8400-e29b-41d4-a716-446655440000`)

Allowed characters: `[0-9a-zA-Z_-]`

**Examples:**

```
pm-c[zielgebiet_default]=5d41402abc4b2a76b9719d911017c592
pm-c[zielgebiet_default]=5d41402abc4b,a3c2b1d4e5f6,9f8e7d6c5b4a
pm-c[reiseart_default]=ab12cd34%2Bef56gh78
pm-c[zielgebiet_default]=5d41402abc4b&pm-c[reiseart_default]=ab12cd34
```

Multiple `pm-c[...]` parameters with different field names are always combined with AND.

---

#### `pm-t` – Fulltext Search

| Property | Value |
|---|---|
| **Type** | String |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\Fulltext` or `AtlasLuceneFulltext` |

Full-text search across indexed content fields.

**Standard MongoDB Query (regex-based):**

```javascript
{ "fulltext": { "$regex": "\\bmallorca", "$options": "i" } }
```

**Atlas Lucene Query (if Atlas Search is enabled):**

```javascript
{
  "$search": {
    "compound": {
      "should": [
        { "text": { "query": "mallorca", "path": "fulltext", "fuzzy": { "maxEdits": 1 } } },
        { "wildcard": { "query": "mallorca*", "path": "fulltext", "allowAnalyzedField": true } }
      ]
    }
  }
}
```

**Examples:**

```
pm-t=mallorca                // Search for "mallorca"
pm-t=hotel+strand            // Search for "hotel" and "strand"
```

---

#### `pm-gr` – Group

| Property | Value |
|---|---|
| **Type** | String or comma-separated strings |
| **Required** | No |
| **Condition Class** | `Search\Condition\MongoDB\Group` |

Filters by group membership. Objects with no groups assigned are also included (empty groups array).

Groups are **string values** stored in the MongoDB document. They are derived from the product data during indexing, based on the `search.groups` configuration per `id_object_type`.

**Group Configuration (`config.json`):**

Groups are defined under `data.search_mongodb.search.groups` as an array. Each entry maps an `id_object_type` to a field source and an optional filter function:

```json
{
  "data": {
    "search_mongodb": {
      "search": {
        "groups": [
          { "123": { "field": "agencies", "filter": null } },
          { "124": { "field": "id_pool", "filter": null } },
          { "125": { "field": "brand", "filter": null } },
          { "126": { "field": "website_ausgabe_default", "filter": "\\Custom\\Filter::treeToGroup" } }
        ]
      }
    }
  }
}
```

**Supported `field` values:**

| Field | Source | Group Values |
|---|---|---|
| `agencies` | `pmt2core_agency_to_media_object` | Agency IDs as strings (e.g. `"5"`, `"12"`) |
| `id_pool` | `MediaObject.id_pool` | Pool ID as string |
| `brand` | `MediaObject.brand.id` | Brand ID as string |
| `{custom_field}` | Any category tree field | `human_to_machine(item.name)` slug strings |

**Custom filter function:**

The optional `filter` property allows post-processing of group values via a callable (e.g. `\\Custom\\Filter::treeToGroup`). The callable receives `($groups, $mediaObject)` as parameters and must return a modified groups array.

**MongoDB Query:**

```javascript
{
  "$or": [
    { "groups": "5" },
    { "groups": { "$size": 0 } }
  ]
}
```

> Products with an **empty groups array** are always included in the results. This ensures products that have no group assignment are not accidentally hidden.

**Examples:**

```
pm-gr=5                      // Group "5" (e.g. agency ID 5)
pm-gr=5,12                   // Group "5" or "12"
```

---

### Status Filters

#### `pm-so` – Sold Out

| Property | Value |
|---|---|
| **Type** | `0` or `1` |
| **Condition Class** | `Search\Condition\MongoDB\SoldOut` |

```
pm-so=0     // Only available products
pm-so=1     // Only sold out products
```

#### `pm-ir` – Is Running

| Property | Value |
|---|---|
| **Type** | `0` or `1` |
| **Condition Class** | `Search\Condition\MongoDB\Running` |

```
pm-ir=1     // Only currently running trips
pm-ir=0     // Only not yet started trips
```

#### `pm-gu` – Guaranteed Departures

| Property | Value |
|---|---|
| **Type** | `0` or `1` |
| **Condition Class** | `Search\Condition\MongoDB\Guaranteed` |

```
pm-gu=1     // Only guaranteed departures
```

#### `pm-sp` – Sales Priority

| Property | Value |
|---|---|
| **Type** | String (format: `A000000`) |
| **Condition Class** | `Search\Condition\MongoDB\SalesPriority` |

```
pm-sp=A000000    // Specific sales priority
```

---

### Special Parameters

#### `pm-url` – URL Match

| Property | Value |
|---|---|
| **Type** | String (URL path) |
| **Condition Class** | `Search\Condition\MongoDB\Url` |

Finds a product by its pretty URL.

```
pm-url=/travel/5-beautiful-mallorca/
```

#### `pm-pf` – Powerfilter

| Property | Value |
|---|---|
| **Type** | Integer |
| **Condition Class** | `Search\Condition\MongoDB\Powerfilter` |

Applies a predefined Powerfilter via `$lookup` against the `powerfilter` collection. A Powerfilter is a **defined result set from the pressmind PIM** – it represents a curated list of media object IDs that has been compiled and saved within the PIM system. This allows editorial teams to manually or rule-based define product selections in the PIM, which can then be used as a search filter in the frontend.

```
pm-pf=42     // Apply powerfilter ID 42
```

#### `pm-loc[field]` – Location / Geo Search

| Property | Value |
|---|---|
| **Type** | String: `{longitude},{latitude},{radius_km}` |
| **Condition Class** | `Search\Condition\MongoDB\AtlasLuceneFulltext` |

Geographic proximity search (requires Atlas Search with geo index).

```
pm-loc[destination]=13.4050,52.5200,50    // Within 50km of Berlin
```

---

### Pagination & Sorting

#### `pm-l` – Pagination (Limit)

| Property | Value |
|---|---|
| **Type** | String: `{page},{page_size}` |
| **Default** | `1,12` |

Controls pagination. Page numbers are 1-based.

**Examples:**

```
pm-l=1,12        // Page 1, 12 results per page
pm-l=2,12        // Page 2, 12 results per page
pm-l=1,24        // Page 1, 24 results per page
pm-l=3,10        // Page 3, 10 results per page
```

---

#### `pm-o` – Sort Order

| Property | Value |
|---|---|
| **Type** | String: `{field}-{direction}` |
| **Default** | `price-asc` |

**Available Sort Options:**

| Value | Description |
|---|---|
| `price-asc` | Price ascending (cheapest first) |
| `price-desc` | Price descending (most expensive first) |
| `date_departure-asc` | Departure date ascending (soonest first) |
| `date_departure-desc` | Departure date descending (latest first) |
| `rand` | Random order |
| `score-asc` / `score-desc` | Search relevance (requires `pm-t`) |
| `recommendation_rate-asc` / `recommendation_rate-desc` | Customer rating |
| `priority` | Sales priority (A before B before C) |
| `list` | Preserve order of `pm-id` or `pm-co` values |
| `valid_from-asc` / `valid_from-desc` | Product validity date |
| `co.{shortname}-asc` / `co.{shortname}-desc` | Custom order field (from `search_mongodb.search.custom_order`) |

**Examples:**

```
pm-o=price-asc               // Cheapest first
pm-o=date_departure-asc      // Soonest departure first
pm-o=rand                    // Random order
pm-o=co.destination-asc      // Custom: by destination A-Z
pm-o=list                    // Preserve input order (pm-id or pm-co)
```

---

## Response Structure

```json
{
  "total": 156,
  "currentPage": 1,
  "pages": 13,
  "documents": [
    {
      "_id": 12345,
      "id_media_object": 12345,
      "id_object_type": 123,
      "code": ["ABC123"],
      "url": "/travel/5-beautiful-mallorca/",
      "description": {
        "headline": "Beautiful Mallorca",
        "subline": "7 nights at Hotel Example",
        "destination": "Spain > Mallorca",
        "images": { "uri": "/assets/images/teaser/image.jpg" }
      },
      "categories": [
        {
          "field_name": "zielgebiet_default",
          "id_item": "5d41402abc4b2a76b9719d911017c592",
          "name": "Mallorca",
          "path_str": ["Spain", "Mallorca"],
          "path_ids": ["a1b2c3d4e5f6", "5d41402abc4b2a76b9719d911017c592"]
        }
      ],
      "prices": {
        "occupancy": 2,
        "duration": 7.0,
        "price_total": 899.00,
        "price_regular_before_discount": 1099.00,
        "earlybird_discount": 200.00,
        "earlybird_discount_f": 0.18,
        "option_board_type": "HB",
        "transport_type": "flight",
        "date_departures": ["2026-06-15T00:00:00+00:00"],
        "housing_package_name": "Hotel Example ****",
        "state": 100
      },
      "transport_types": ["flight", "bus"],
      "board_types": ["HB", "AI"],
      "sold_out": false,
      "recommendation_rate": 4.5,
      "sales_priority": "A000050"
    }
  ],
  "categoriesGrouped": {
    "zielgebiet_default": [
      { "_id": 10, "name": "Mallorca", "count": 42 },
      { "_id": 11, "name": "Ibiza", "count": 18 }
    ],
    "reiseart_default": [
      { "_id": 5, "name": "Beach Holiday", "count": 55 }
    ]
  },
  "boardTypesGrouped": [
    { "_id": "HB", "count": 80 },
    { "_id": "AI", "count": 45 }
  ],
  "transportTypesGrouped": [
    { "_id": "flight", "count": 120 },
    { "_id": "bus", "count": 36 }
  ],
  "startingPointsGrouped": [
    { "_id": 123, "city": "Berlin", "count": 95 },
    { "_id": 456, "city": "Munich", "count": 88 }
  ],
  "minDuration": 3,
  "maxDuration": 21,
  "minPrice": 299,
  "maxPrice": 4500,
  "minDeparture": "2026-03-15T00:00:00+00:00",
  "maxDeparture": "2027-02-28T00:00:00+00:00"
}
```

### Response Fields

| Field | Type | Description |
|---|---|---|
| `total` | `integer` | Total number of matching documents |
| `currentPage` | `integer` | Current page number |
| `pages` | `integer` | Total number of pages |
| `documents` | `array` | Array of matching products (paginated) |
| `categoriesGrouped` | `object` | Faceted category counts, keyed by field name |
| `boardTypesGrouped` | `array` | Faceted board type counts |
| `transportTypesGrouped` | `array` | Faceted transport type counts |
| `startingPointsGrouped` | `array` | Faceted starting point counts |
| `minDuration` / `maxDuration` | `number` | Duration range across all results |
| `minPrice` / `maxPrice` | `number` | Price range across all results |
| `minDeparture` / `maxDeparture` | `string` | Date range across all results |

---

## Complete Query Examples

### Basic Search: Mallorca Holidays

```
pm-ot=123&pm-c[zielgebiet_default]=5d41402abc4b&pm-l=1,12&pm-o=price-asc
```

Finds all products of type 123 in destination "Mallorca", sorted by price, page 1 with 12 results.

### Filtered Search: Summer Beach Holiday for 2 Adults

```
pm-ot=123
&pm-c[zielgebiet_default]=5d41402abc4b,a3c2b1d4e5f6,9f8e7d6c5b4a
&pm-c[reiseart_default]=ab12cd34ef56
&pm-dr=2026-06-01-2026-08-31
&pm-du=7-14
&pm-ho=2
&pm-bt=HB,AI
&pm-pr=500-2000
&pm-o=price-asc
&pm-l=1,12
```

### Fulltext Search with Price Sort

```
pm-ot=123&pm-t=mallorca+hotel&pm-o=score-desc&pm-l=1,20
```

### Specific Products in Fixed Order

```
pm-ot=123&pm-id=100,200,300,400&pm-o=list&pm-l=1,10
```

### Departure from Berlin, All-Inclusive

```
pm-ot=123
&pm-sc=123
&pm-bt=AI
&pm-tr=FLUG
&pm-dr=2026-07-01-2026-07-31
&pm-o=price-asc
&pm-l=1,12
```

### Excluding Sold-Out Products

```
pm-ot=123&pm-so=0&pm-o=date_departure-asc&pm-l=1,24
```

---

## MongoDB Aggregation Pipeline

The search builds this pipeline internally:

```
Stage 1: $match
  └─ Combines all condition queries with $and

Stage 2: $addFields
  └─ Filters prices array by occupancy, price range,
     duration, board type, transport type

Stage 3: $addFields (departure filter)
  └─ Filters date_departures within each price

Stage 4: $project
  └─ Selects output fields
  └─ Reduces prices to best offer (by state → price → duration)
  └─ Extracts unique transport_types and board_types

Stage 5: $sort
  └─ Applies requested sort order

Stage 6: $facet
  ├─ documents: paginated result set
  ├─ categoriesGrouped: category counts
  ├─ boardTypesGrouped: board type counts
  ├─ transportTypesGrouped: transport type counts
  ├─ startingPointsGrouped: starting point counts
  ├─ min/max aggregations (price, duration, dates)
  └─ total: document count
```

---

[← Back to Architecture](architecture.md) | [Back to Configuration →](configuration.md)
