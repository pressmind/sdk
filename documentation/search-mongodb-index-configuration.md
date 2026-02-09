# MongoDB Index Configuration

[← Back to MongoDB Search API](search-mongodb-api.md) | [← Back to Architecture](architecture.md)

---

## Table of Contents

- [The Core Concept: Self-Contained Search Documents](#the-core-concept-self-contained-search-documents)
- [Why This Pattern Exists](#why-this-pattern-exists)
- [Configuration Overview](#configuration-overview)
- [build_for – Which Products to Index](#build_for--which-products-to-index)
- [descriptions – View-Ready Content](#descriptions--view-ready-content)
  - [Direct Fields](#direct-fields)
  - [Fields from Linked Objects (from)](#fields-from-linked-objects-from)
  - [Filter Functions](#filter-functions)
- [categories – Searchable Category Trees](#categories--searchable-category-trees)
  - [Direct Category Trees](#direct-category-trees)
  - [Categories from Linked Objects](#categories-from-linked-objects)
  - [Plaintext from Linked Objects](#plaintext-from-linked-objects)
  - [Aggregation Methods](#aggregation-methods)
  - [Category Document Structure in MongoDB](#category-document-structure-in-mongodb)
- [groups – Multi-Tenancy & Product Segmentation](#groups--multi-tenancy--product-segmentation)
- [touristic – Price Aggregation Settings](#touristic--price-aggregation-settings)
  - [occupancies](#occupancies)
  - [occupancy_additional](#occupancy_additional)
  - [duration_ranges](#duration_ranges)
  - [Price Aggregation Process](#price-aggregation-process)
- [custom_order – Custom Sort Fields](#custom_order--custom-sort-fields)
- [Additional Options](#additional-options)
  - [code_delimiter](#code_delimiter)
  - [five_dates_per_month_list](#five_dates_per_month_list)
  - [possible_duration_list](#possible_duration_list)
  - [allow_invalid_offers](#allow_invalid_offers)
  - [order_by_primary_object_type_priority](#order_by_primary_object_type_priority)
- [locations – Geo Search Fields](#locations--geo-search-fields)
- [The Complete Document Structure](#the-complete-document-structure)
- [Collection Naming & Storage](#collection-naming--storage)
- [Common Configuration Mistakes](#common-configuration-mistakes)

---

## The Core Concept: Self-Contained Search Documents

The MongoDB search index follows one fundamental architectural rule:

> **Every search result document MUST contain ALL data needed to render the view. No additional data sources may be consulted when rendering individual search result items.**

This means: when a search query returns a list of products, the frontend view **must** be fully renderable using only the data within each MongoDB document. There must be **no additional database queries**, **no API calls**, and **no secondary lookups** per result item.

This is not a suggestion – it is a strict design pattern that the entire index configuration revolves around.

### Why This Pattern Exists

```
┌──────────────────────────────────────────────────────────┐
│  WRONG: N+1 Pattern (FORBIDDEN)                         │
│                                                          │
│  Search → MongoDB returns 24 IDs                         │
│    └─ For each ID:                                       │
│        ├─ Query MySQL for headline        ← 24 queries   │
│        ├─ Query MySQL for image           ← 24 queries   │
│        └─ Query MySQL for category name   ← 24 queries   │
│                                                          │
│  Total: 1 + 72 = 73 database queries per page load       │
│  Result: Slow, unscalable, defeats the purpose of cache  │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│  CORRECT: Self-Contained Documents                       │
│                                                          │
│  Search → MongoDB returns 24 complete documents          │
│    └─ Each document contains:                            │
│        ├─ headline, subline, image URI                   │
│        ├─ category names and paths                       │
│        ├─ price, duration, board type, transport          │
│        └─ URL, code, all view-relevant data              │
│                                                          │
│  Total: 1 query                                          │
│  Result: Fast, scalable, cache-friendly                  │
└──────────────────────────────────────────────────────────┘
```

This is why the index configuration has dedicated sections for `descriptions`, `categories`, `groups`, and `custom_order` – each one pre-computes and denormalizes data at index time so it is instantly available at search time.

### The Data Flow

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ pressmind    │     │ MySQL / MariaDB  │     │ MongoDB         │
│ PIM          │────>│ (SDK Database)   │────>│ (Search Index)  │
│ (Source)     │     │ ORM Objects      │     │ Denormalized    │
└─────────────┘     └──────────────────┘     └─────────────────┘
                           │                         │
                     Import Pipeline            Search Query
                    (Import.php)            (Query::fromRequest)
                           │                         │
                     Indexer builds              Aggregation
                    complete document            Pipeline
                           │                         │
                     categories, descriptions,   $match, $addFields,
                     prices, groups, fulltext,   $project, $sort,
                     locations, custom_order     $facet
                           │                         │
                           ▼                         ▼
                    Self-contained              View-ready
                    MongoDB document            search results
```

---

## Configuration Overview

All MongoDB index configuration is under `data.search_mongodb.search` in the `config.json`:

```json
{
  "data": {
    "search_mongodb": {
      "enabled": true,
      "database": {
        "uri": "mongodb+srv://user:pass@cluster.mongodb.net/",
        "db": "my_travel_db"
      },
      "search": {
        "build_for": { ... },
        "descriptions": { ... },
        "categories": { ... },
        "groups": [ ... ],
        "custom_order": { ... },
        "locations": { ... },
        "touristic": { ... },
        "code_delimiter": ",",
        "five_dates_per_month_list": false,
        "possible_duration_list": false,
        "allow_invalid_offers": [123, 124]
      }
    }
  }
}
```

Each section controls what data is included in each MongoDB document. The key principle: **configure everything the view needs here, at index time.**

---

## build_for – Which Products to Index

`build_for` defines which media object types are indexed and for which language/origin combinations a collection is created.

```json
{
  "build_for": {
    "123": [
      {
        "language": "de",
        "origin": 0,
        "disable_language_prefix_in_url": false
      }
    ],
    "456": [
      {
        "language": "de",
        "origin": 0
      },
      {
        "language": "en",
        "origin": 0
      }
    ]
  }
}
```

| Property | Type | Description |
|---|---|---|
| Key (e.g. `"123"`) | Integer (as string) | The `id_object_type` from the pressmind PIM |
| `language` | String | Language code (e.g. `"de"`, `"en"`) |
| `origin` | Integer | Market/origin ID (e.g. `0` = default market) |
| `disable_language_prefix_in_url` | Boolean | If `true`, the pretty URL is generated without language prefix |

Each combination of `id_object_type` × `language` × `origin` creates a separate MongoDB collection. The collection name follows this convention:

```
best_price_search_based_{language}_origin_{origin}
```

**Important:** Only media objects whose `id_object_type` is listed in `build_for` will be indexed. Products with unlisted object types are silently skipped with a warning log.

---

## descriptions – View-Ready Content

The `descriptions` section defines which content fields are pre-computed and stored in each search document. This is the key to the self-contained document pattern – everything the teaser/list view needs to render must be configured here.

```json
{
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
}
```

The outer key (e.g. `"123"`) is the `id_object_type`. Each inner key (e.g. `"headline"`, `"subline"`) becomes a property in the document's `description` object.

### Direct Fields

When `from` is `null` or omitted, the field is read directly from the media object's content data:

```json
{
  "headline": {
    "field": "name",
    "from": null
  }
}
```

The special field name `"name"` reads from `MediaObject.name` (the object's title), not from the content data class.

### Fields from Linked Objects (from)

When `from` is set, the field is read from a **linked media object** (object link). The `from` value is the object link field name, and `field` is the property on the linked object:

```json
{
  "subline": {
    "field": "subline_default",
    "from": "unterkuenfte_default"
  }
}
```

This means: "Follow the `unterkuenfte_default` object link, load the linked MediaObject, and read its `subline_default` field."

The first linked object is used (only one value per description field).

### Filter Functions

The optional `filter` property specifies a PHP callable that transforms the raw field value before storing it in the document:

```json
{
  "images": {
    "field": "bilder_default",
    "filter": "\\Custom\\Filter::firstPicture",
    "params": {
      "derivative": "teaser"
    }
  }
}
```

| Property | Type | Description |
|---|---|---|
| `filter` | String | Fully qualified `ClassName::methodName` |
| `params` | Object | Named parameters passed to the filter method |

The filter class is instantiated and receives two additional properties:
- `$this->mediaObject` – the current MediaObject being indexed
- `$this->agency` – the current agency (if agency-based indexing is active)

The method signature must accept the raw value as the first parameter, followed by any named `params`:

```php
class Filter {
    public $mediaObject; // automatically set by the indexer
    public $agency;      // automatically set by the indexer

    public static function firstPicture($value, $derivative = 'teaser') {
        // $value = raw field data (e.g. array of image objects)
        // $derivative = from params config
        // return: transformed value for the document
    }

    public static function strip($value) {
        return strip_tags($value);
    }

    public static function lastTreeItemAsString($value) {
        // Extract last tree path item as a readable string
    }
}
```

**Common use cases for filter functions:**
- Extract the first image and return only the teaser derivative URI
- Strip HTML tags from text fields
- Convert a category tree to a human-readable string (e.g. "Spain > Mallorca")
- Apply custom formatting or truncation

---

## categories – Searchable Category Trees

The `categories` section defines which category tree fields are indexed and made searchable via the `pm-c[field]` parameter.

```json
{
  "categories": {
    "123": {
      "zielgebiet_default": null,
      "reiseart_default": null,
      "sterne_default": {
        "from": "unterkuenfte_default"
      }
    }
  }
}
```

The outer key is the `id_object_type`. Each inner key is a category tree field name that becomes searchable.

### Direct Category Trees

When the value is `null`, the category tree is read directly from the media object's content data:

```json
{
  "zielgebiet_default": null
}
```

This indexes the `zielgebiet_default` tree with all its items, paths, and hierarchy information.

### Categories from Linked Objects

When `from` is specified, the category tree is read from a linked media object:

```json
{
  "sterne_default": {
    "from": "unterkuenfte_default"
  }
}
```

This means: "Follow the `unterkuenfte_default` object link and index the `sterne_default` category tree from the linked object." The `field_name` in the resulting MongoDB document will be the key name (i.e. `sterne_default`).

You can also rename the `field_name` in the index by using the `field` property:

```json
{
  "hotel_stars": {
    "field": "sterne_default",
    "from": "unterkuenfte_default"
  }
}
```

Here, the category data comes from `sterne_default` on the linked object, but is stored under `field_name: "hotel_stars"` in the document.

### Plaintext from Linked Objects

For cases where linked objects don't have category trees but you want to create searchable "virtual categories" from plaintext fields:

```json
{
  "ship_name": {
    "type": "plaintext_from_objectlink",
    "from": "schiffe_default",
    "field": "ship_name",
    "virtual_id_tree": "virtual_ships",
    "filter": "\\Custom\\Filter::shipName"
  }
}
```

| Property | Type | Description |
|---|---|---|
| `type` | `"plaintext_from_objectlink"` | Enables virtual category mode |
| `from` | String | Object link field name |
| `field` | String | The `field_name` used in MongoDB |
| `virtual_id_tree` | String | A virtual tree ID (required, used for `id_tree`) |
| `filter` | String | Optional filter function for the value |

The `id_item` is generated as an MD5 hash: `md5(id_media_object_link + varName + fieldName + name + virtual_id_tree)`.

### Aggregation Methods

For complex category logic, you can use custom aggregation methods:

```json
{
  "custom_categories": {
    "aggregation": {
      "method": "\\Custom\\Aggregator::buildCategories",
      "params": {}
    }
  }
}
```

The method must return an array of category objects in the same structure as the standard category mapping.

### Category Document Structure in MongoDB

Each indexed category item has this structure in the `categories` array:

```json
{
  "id_item": "5d41402abc4b2a76b9719d911017c592",
  "name": "Mallorca",
  "id_tree": "abc123",
  "id_parent": "def456",
  "code": "MAL",
  "sort": 5,
  "field_name": "zielgebiet_default",
  "level": 1,
  "path_str": ["Spain", "Balearic Islands", "Mallorca"],
  "path_ids": ["aaa111", "bbb222", "5d41402abc4b2a76b9719d911017c592"]
}
```

The `path_str` and `path_ids` arrays contain the full tree path from root to leaf, enabling breadcrumb-style display and hierarchical filtering – all without any additional query.

---

## groups – Multi-Tenancy & Product Segmentation

Groups allow products to be segmented by agency, brand, pool, or custom criteria. See [pm-gr documentation](search-mongodb-api.md#pm-gr--group) for the query-side details.

```json
{
  "groups": [
    { "123": { "field": "agencies", "filter": null } },
    { "124": { "field": "id_pool", "filter": null } },
    { "125": { "field": "brand", "filter": null } },
    { "126": { "field": "website_ausgabe_default", "filter": "\\Custom\\Filter::treeToGroup" } }
  ]
}
```

Each entry maps an `id_object_type` to a source field. The groups array in the MongoDB document contains string values.

| Field | Source | Resulting Values |
|---|---|---|
| `agencies` | `pmt2core_agency_to_media_object` | Agency IDs as strings |
| `id_pool` | `MediaObject.id_pool` | Pool ID as string |
| `brand` | `MediaObject.brand.id` | Brand ID as string |
| `{tree_field}` | Any category tree field | `human_to_machine(item.name)` slugs |

---

## touristic – Price Aggregation Settings

The `touristic` section controls how price data from the `pmt2core_cheapest_price_speed` table is aggregated into the MongoDB document's `prices` array.

```json
{
  "touristic": {
    "occupancies": [1, 2, 3, 4, 5, 6],
    "occupancy_additional": [1, 2],
    "duration_ranges": [
      [1, 3],
      [4, 7],
      [8, 99]
    ]
  }
}
```

### occupancies

An array of adult occupancy values to generate price entries for. Each occupancy level creates a separate price document in the `prices` array.

```json
"occupancies": [1, 2, 3, 4, 5, 6]
```

For example: occupancy `2` means "price for 2 adults in one room/cabin." The search parameter `pm-ho=2` filters to these entries.

### occupancy_additional

An array of child occupancy values. Used in combination with adult occupancy.

```json
"occupancy_additional": [1, 2]
```

### duration_ranges

**This is a critical setting.** Duration ranges define "buckets" for grouping travel durations. The indexer uses SQL `CASE` statements to partition prices by duration bucket.

```json
"duration_ranges": [
  [1, 3],
  [4, 7],
  [8, 99]
]
```

| Range | Description |
|---|---|
| `[1, 3]` | Short trips: 1-3 days |
| `[4, 7]` | Week trips: 4-7 days |
| `[8, 99]` | Long trips: 8+ days |

**Important:** If a product has prices with durations that don't fall into any configured range, those prices will not be indexed. The product may appear as having no prices and will be excluded from the index entirely (unless `allow_invalid_offers` is enabled for that object type).

For example, if your products have 14-day cruises but your `duration_ranges` only cover `[1, 3], [4, 7], [8, 12]`, those 14-day offers will be lost. You must configure a range that includes them, e.g. `[8, 99]`.

### Price Aggregation Process

The indexer runs two SQL queries per product:

1. **`price_mix = 'date_housing'`** – Standard prices with occupancy. Partitioned by `date_departure × occupancy × duration_bucket` (and optionally by `transport_type`, `startingpoint_id_city`, `option_board_type`). The cheapest valid price per partition wins.

2. **`price_mix != 'date_housing'`** – Prices without occupancy (e.g. flat-rate packages). Partitioned by `date_departure × duration_bucket`.

Each resulting price entry in the MongoDB document contains:

```json
{
  "occupancy": 2,
  "occupancy_child": 0,
  "duration": 7.0,
  "price_total": 899.00,
  "price_regular_before_discount": 1099.00,
  "earlybird_discount": 200.00,
  "earlybird_discount_f": 0.18,
  "earlybird_discount_date_to": "2026-05-01",
  "earlybird_name": "Early Bird 20%",
  "option_name": "Standard Room",
  "option_board_type": "HB",
  "price_mix": "date_housing",
  "transport_type": "FLUG",
  "housing_package_name": "Hotel Example ****",
  "housing_package_id_name": "hotel-example",
  "state": 100,
  "quota_pax": 10,
  "date_departures": ["2026-06-15T00:00:00.000+02:00", "2026-06-22T00:00:00.000+02:00"],
  "guaranteed_departures": ["2026-06-15T00:00:00.000+02:00"],
  "startingpoint_option": {
    "id_city": 123,
    "city": "Berlin"
  }
}
```

State values (remapped from CheapestPriceSpeed states for sortable ordering):

| MongoDB State | Name | CheapestPriceSpeed Source |
|---|---|---|
| `100` | Buchbar | State `3` (Buchbar) |
| `200` | Anfrage | State `1` (Anfrage) |
| `300` | Stop | State `5` (Stop) or any other |

Lower value = better availability. This allows simple numeric sorting: `100 < 200 < 300`.
See [CheapestPrice Aggregation → State Machine](cheapest-price-aggregation.md#state-machine) for the full state determination logic.

All this data is available directly in the search result – no secondary query needed.

---

## custom_order – Custom Sort Fields

Custom order fields allow sorting search results by arbitrary product properties (used with `pm-o=co.{shortname}-asc`):

```json
{
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
}
```

Each `shortname` (e.g. `"destination"`) is stored in `custom_order.destination` on the document. The field resolution follows the same `field` / `from` / `filter` pattern as `descriptions`.

Products of a different `id_object_type` or with empty values get the placeholder `"ZZZZZ"` so they sort at the end.

MongoDB indexes are automatically created for each custom_order field (both ascending and descending).

---

## Additional Options

### code_delimiter

```json
"code_delimiter": ","
```

Delimiter for splitting the `MediaObject.code` field into an array. The product code is stored as `code: ["ABC123", "DEF456"]` in the document.

### five_dates_per_month_list

```json
"five_dates_per_month_list": false
```

When `true`, each document includes a `dates_per_month` array with up to 5 departure dates per month, including price details. Useful for calendar-style date pickers.

### possible_duration_list

```json
"possible_duration_list": false
```

When `true`, each document includes a `possible_durations` array with all available duration/transport combinations and their prices.

### allow_invalid_offers

```json
"allow_invalid_offers": [123, 124]
```

An array of `id_object_type` values (or `true` for all) that should be indexed even when they have no valid prices after aggregation. Useful for content-only products (e.g. editorial articles, POIs) that should appear in search results but have no touristic pricing.

Products with `visibility = 10` (hidden) are always excluded regardless of this setting.

### order_by_primary_object_type_priority

```json
"order_by_primary_object_type_priority": false
```

When `true`, search results are ordered by the position of their `id_object_type` in the `primary_media_type_ids` config array. This allows prioritizing certain product types over others.

---

## locations – Geo Search Fields

For geographic search with `pm-loc[field]`, configure which location fields to index:

```json
{
  "locations": {
    "123": {
      "destination": null
    }
  }
}
```

The key is the location field name from the content data. The indexer reads latitude/longitude pairs and stores them as GeoJSON `MultiPoint` objects:

```json
{
  "locations": {
    "destination": {
      "type": "MultiPoint",
      "coordinates": [[13.4050, 52.5200], [11.5820, 48.1351]]
    }
  }
}
```

Requires Atlas Search with a geo index for `pm-loc` queries.

---

## The Complete Document Structure

When fully configured, a MongoDB document looks like this:

```json
{
  "_id": 12345,
  "id_object_type": 123,
  "object_type_order": 0,
  "id_media_object": 12345,
  "url": "/travel/5-beautiful-mallorca/",
  "code": ["ABC123"],

  "description": {
    "headline": "Beautiful Mallorca",
    "subline": "7 nights at Hotel Example",
    "destination": "Spain > Mallorca",
    "images": { "uri": "/assets/images/teaser/image.jpg" }
  },

  "categories": [
    {
      "id_item": "5d41402abc4b",
      "name": "Mallorca",
      "id_tree": "abc123",
      "id_parent": "def456",
      "field_name": "zielgebiet_default",
      "level": 1,
      "path_str": ["Spain", "Mallorca"],
      "path_ids": ["aaa111", "5d41402abc4b"]
    }
  ],

  "groups": ["5", "12"],

  "prices": [
    {
      "occupancy": 2,
      "duration": 7.0,
      "price_total": 899.00,
      "option_board_type": "HB",
      "transport_type": "FLUG",
      "date_departures": ["2026-06-15T00:00:00.000+02:00"],
      "state": 100
    }
  ],

  "best_price_meta": { "...": "first price entry (cheapest)" },
  "has_price": true,
  "departure_date_count": 42,

  "fulltext": "mallorca spain hotel example beach ...",

  "custom_order": {
    "destination": "mallorca",
    "region": "balearic-islands"
  },

  "locations": {
    "destination": {
      "type": "MultiPoint",
      "coordinates": [[2.6502, 39.5696]]
    }
  },

  "sold_out": false,
  "is_running": false,
  "has_guaranteed_departures": true,
  "recommendation_rate": 4.5,
  "sales_priority": "A000005",
  "visibility": 30,
  "valid_from": "2026-01-01T00:00:00.000+01:00",
  "valid_to": "2026-12-31T23:59:59.000+01:00",
  "last_modified_date": "2026-02-08T14:30:00.000+01:00"
}
```

**Every field in this document is available to the view. No additional queries needed.**

---

## Collection Naming & Storage

The indexer creates two collections per language/origin/agency combination:

| Collection | Content |
|---|---|
| `best_price_search_based_{lang}_origin_{origin}` | Search document (prices, categories, groups, etc.) |
| `description_{lang}_origin_{origin}` | Description data (separated for performance) |

With agency-based indexing:

```
best_price_search_based_de_origin_0_agency_5
description_de_origin_0_agency_5
```

The description data is stored separately and joined at query time via `$lookup`. This keeps the main search collection lean for faster aggregation.

### Automatic MongoDB Indexes

The indexer automatically creates these indexes on each collection:

| Index | Purpose |
|---|---|
| `id_media_object` (unique) | Fast lookup by product ID |
| `groups` | Group/tenant filtering |
| `prices.price_total` (asc + desc) | Price sorting |
| `prices.date_departure` (asc + desc) | Date sorting |
| `prices.duration` | Duration filtering |
| `prices.occupancy` | Occupancy filtering |
| `prices.occupancy_additional` | Child occupancy filtering |
| `prices.id_startingpoint_option` | Starting point filtering |
| `categories.id_item` | Category filtering |
| `categories.name` | Category name search |
| `categories.id_item + field_name` | Combined category filter |
| `sold_out` | Sold-out filtering |
| `sales_priority` | Priority sorting |
| `fulltext` (text index) | Fulltext search (when OpenSearch is disabled) |
| `custom_order.*` (asc + desc) | Custom sort fields |

---

## Common Configuration Mistakes

### 1. Missing duration_ranges

**Symptom:** Products disappear from search results.
**Cause:** The product has prices with durations outside all configured ranges.
**Fix:** Check the indexer log for "No prices after aggregation" and add a range covering the actual durations.

```
# Log message example:
MongoDB Indexer: MediaObject 12345 NOT INDEXED - No prices after aggregation.
ACTUAL DURATIONS in DB: [14, 21 days].
CONFIGURED duration_ranges: [1-3, 4-7, 8-12].
FIX: Add a duration_range covering 14-21 days
```

### 2. Missing descriptions field

**Symptom:** Empty description in search results, warning in log.
**Cause:** `field` key is missing in the descriptions config.
**Fix:** Every description entry must have a `field` property.

### 3. Missing groups field

**Symptom:** Exception during indexing.
**Cause:** Groups config for the object type has no `field` key.
**Fix:** Add `"field": "agencies"` (or another source) to the groups config.

### 4. Rendering additional data in view

**Symptom:** Slow search results, N+1 queries.
**Cause:** The view template queries MySQL for data that should be in the MongoDB document.
**Fix:** Add the missing field to `descriptions` or `categories` config and re-index. **Never** query additional data per search result item.

### 5. Object type not in build_for

**Symptom:** Products of a certain type are not searchable.
**Cause:** The `id_object_type` is not listed in `build_for`.
**Fix:** Add the object type with language and origin to `build_for`.
