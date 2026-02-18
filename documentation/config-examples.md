# Configuration Examples & Best Practices

[← Back to Configuration](configuration.md) | [→ Touristic Config](config-touristic-data.md) | [→ Search Config](config-search.md)

---

## Table of Contents

- [Overview](#overview)
- [pm-config.php Structure](#pm-configphp-structure)
- [Minimal Setup](#minimal-setup)
- [Touristic Configuration Patterns](#touristic-configuration-patterns)
  - [Date Filter](#date-filter)
  - [State Filters (Housing, Transport)](#state-filters-housing-transport)
  - [Offer Generation](#offer-generation)
  - [Agency-Based Pricing](#agency-based-pricing)
- [MongoDB Search Configuration](#mongodb-search-configuration)
  - [build_for](#build_for)
  - [categories](#categories)
  - [descriptions (with Custom Filters)](#descriptions-with-custom-filters)
  - [touristic (Occupancies & Duration Ranges)](#touristic-occupancies--duration-ranges)
  - [groups](#groups)
- [Image Derivatives](#image-derivatives)
  - [Standard Set](#standard-set)
  - [Extended Set (Cruise / Complex Products)](#extended-set-cruise--complex-products)
- [Pretty URLs](#pretty-urls)
- [Cache Configuration](#cache-configuration)
- [OpenSearch Integration](#opensearch-integration)
- [Custom Import Hooks](#custom-import-hooks)
- [Multi-Language Setup](#multi-language-setup)
- [Complete Example: Standard Tour Operator](#complete-example-standard-tour-operator)
- [Complete Example: Cruise Operator](#complete-example-cruise-operator)
- [Common Pitfalls](#common-pitfalls)

---

## Overview

This document presents **real-world configuration patterns** extracted from ~40 production travelshop installations. All values are anonymized but represent actual proven setups.

The `pm-config.php` file is the PHP equivalent of `config.json`. Most travelshop projects use the PHP format because it allows environment-specific overrides and dynamic values.

---

## pm-config.php Structure

The file returns a PHP array with environment keys. The `development` key is the base configuration; `production` overrides only the values that differ (typically server URLs, database credentials, and MongoDB URIs):

```php
<?php
$config = [
    'development' => [
        'server'    => [ ... ],
        'database'  => [ ... ],
        'rest'      => [ ... ],
        'data'      => [ ... ],
        'cache'     => [ ... ],
        'image_handling' => [ ... ],
        // ...
    ],
    'testing'    => [],
    'production' => [
        'server'   => [ 'webserver_http' => 'https://www.example.com' ],
        'database' => [ 'username' => '...', 'password' => '...', 'dbname' => '...' ],
        'data'     => [
            'search_mongodb' => [
                'database' => [ 'uri' => '...', 'db' => '...' ],
            ],
        ],
    ],
];
```

> **Best Practice:** Define everything in `development`. In `production`, override only credentials, URIs, and server URLs. This keeps the configuration DRY and avoids drift between environments.

---

## Minimal Setup

The absolute minimum configuration for a travelshop with one touristic product type:

```php
'data' => [
    'primary_media_type_ids' => [607],
    'media_types' => [
        607 => 'Reise',
    ],
    'media_types_pretty_url' => [
        607 => [
            'prefix' => '/reisen/',
            'fields' => ['name' => 'name'],
            'strategy' => 'count-up',
            'suffix' => '/',
        ],
    ],
    'media_types_allowed_visibilities' => [
        607 => [30],
    ],
    'sections' => [
        'allowed' => ['Default'],
        'default' => 'Default',
        'fallback' => 'Default',
        'fallback_on_empty_values' => true,
    ],
    'languages' => [
        'allowed' => ['de'],
        'default' => 'de',
    ],
    'touristic' => [
        'origins' => ['0'],
    ],
    'search_mongodb' => [
        'enabled' => true,
        'database' => [
            'uri' => 'mongodb://user:pass@127.0.0.1:27017/dbname?authSource=admin',
            'db'  => 'dbname',
        ],
        'search' => [
            'build_for' => [
                607 => [['language' => null, 'origin' => 0]],
            ],
        ],
    ],
],
```

---

## Touristic Configuration Patterns

### Date Filter

Controls which departure dates are included in the CheapestPrice aggregation. **87% of projects** enable this filter.

```php
'touristic' => [
    'date_filter' => [
        'active'         => true,
        'orientation'    => 'departure',  // or 'arrival'
        'offset'         => 0,            // days from now to start
        'allowed_states' => [0, 1, 2, 4, 5],
        'max_date_offset'=> 730,          // ~2 years into the future
    ],
],
```

**Common `orientation` values:**

| Value | Usage | When to use |
|---|---|---|
| `departure` | ~35% of projects | Standard tour operators, day trips |
| `arrival` | ~60% of projects | Package tours where return date matters |

**Common `offset` values:**

| Value | Meaning | When to use |
|---|---|---|
| `0` | Include departures from today | Default, most projects |
| `1` | Skip today's departures | When same-day booking is not possible |
| `3` | Skip next 3 days | Short booking lead time required |
| `7` | Skip next week | Week-long lead time required |
| `14` | Skip next 2 weeks | Long lead time (e.g. youth travel, charter flights) |

**Common `max_date_offset` values:**

| Value | Meaning | When to use |
|---|---|---|
| `730` | ~2 years | Standard tour operators (most common) |
| `910` | ~2.5 years | Cruise operators with long-term planning |
| `1460` | ~4 years | River cruise operators with very early planning |

> **Note:** `allowed_states` is **universally `[0, 1, 2, 4, 5]`** across all production systems. State `3` (Gesperrt/blocked) is always excluded.

### State Filters (Housing, Transport)

Only ~20% of projects explicitly configure these. When set, they always use the same values:

```php
'touristic' => [
    'housing_option_filter' => [
        'active'         => true,
        'allowed_states' => [0, 1, 2, 3],
    ],
    'transport_filter' => [
        'active'         => true,
        'allowed_states' => [0, 2, 3],
    ],
],
```

> **Note:** When not configured, the SDK uses defaults that include all states. Explicitly setting these filters is recommended for projects with many touristic options to avoid including invalid prices.

### Offer Generation

Controls how the CheapestPrice matrix is expanded:

```php
'touristic' => [
    'max_offers_per_product'                      => 5000,   // safety limit
    'generate_single_room_index'                   => false,  // calculate EZ surcharge
    'generate_offer_for_each_transport_type'        => false,  // separate entry per transport
    'generate_offer_for_each_option_board_type'     => false,  // separate entry per board type
    'generate_offer_for_each_startingpoint_option'  => false,  // separate entry per departure city
    'include_negative_option_in_cheapest_price'     => true,   // include discount options
    'label_price_mix_date_transport'                => null,   // custom label for transport price_mix
],
```

**When to enable specific generators:**

| Setting | Enable when... | Example |
|---|---|---|
| `generate_single_room_index` | You need to display "EZ-Zuschlag: +150 €" | ~20% of projects |
| `generate_offer_for_each_transport_type` | Product has BUS + FLUG and you want separate prices | ~15% of projects |
| `generate_offer_for_each_startingpoint_option` | Each departure city should have its own price entry | ~7% of projects |

**`max_offers_per_product` values in production:**

| Value | When to use |
|---|---|
| `5000` | Standard (most projects) |
| `100000-150000` | Complex products with many date/option/transport combinations |

> **Warning:** Setting `max_offers_per_product` too high can cause import timeouts. Only increase when products genuinely have many valid combinations.

### Agency-Based Pricing

Only ~7% of projects use this. Enables separate price calculations per agency:

```php
'touristic' => [
    'agency_based_option_and_prices' => [
        'enabled'          => true,
        'allowed_agencies' => [389, 11782, 50303, 569, 29091],
    ],
],
```

---

## MongoDB Search Configuration

### build_for

Defines which object types are indexed for MongoDB search. Each entry specifies language and touristic origin:

```php
'search_mongodb' => [
    'search' => [
        'build_for' => [
            // Touristic product (main type)
            607 => [['language' => null, 'origin' => 0]],

            // Content-only types (no touristic data)
            608 => [['language' => null, 'origin' => 0]],
            609 => [['language' => null, 'origin' => 0]],
        ],
    ],
],
```

**Number of indexed types in production:**

| Count | Frequency | Example |
|---|---|---|
| 1–2 types | ~30% | Simple operators (one product type + maybe day trips) |
| 3–5 types | ~40% | Standard operators (products + destinations + accommodations) |
| 6–16 types | ~30% | Complex operators (cruises, multi-brand, many content types) |

### categories

Define which category tree fields are indexed for faceted search filters. For the full reference (all five types, virtual trees, aggregations, Filter class), see **[Categories Configuration Guide](categories-configuration-guide.md)**.

```php
'categories' => [
    607 => [
        'zielgebiet_default'  => null,  // destination tree
        'reiseart_default'    => null,  // travel type tree
        'saison_default'      => null,  // season tree
        'stoerer_default'     => null,  // badge/flag tree
    ],
    608 => [
        'zielgebiet_default'  => null,  // also index for content types
    ],
],
```

**Most common category fields across all projects:**

| Field | Usage | Purpose |
|---|---|---|
| `zielgebiet_default` | ~95% | Destination filter |
| `reiseart_default` | ~80% | Travel type filter |
| `saison_default` | ~50% | Season filter |
| `stoerer_default` | ~40% | Badge/highlight filter |
| `befoerderung_default` | ~30% | Transport type filter |
| `farbsteuerung_default` | ~30% | Color/design control |

### descriptions (with Custom Filters)

Map content fields into the MongoDB search document. These are the fields available in search results without additional queries:

```php
'descriptions' => [
    607 => [
        // Text fields – strip HTML tags
        'headline' => [
            'field'  => 'headline_default',
            'filter' => '\\Custom\\Filter::strip',
        ],
        'subline' => [
            'field'  => 'subline_default',
            'filter' => '\\Custom\\Filter::strip',
        ],
        'intro' => [
            'field'  => 'einleitung_default',
            'filter' => '\\Custom\\Filter::strip',
        ],

        // Image – extract first picture with derivative URL
        'image' => [
            'field'  => 'bilder_default',
            'filter' => '\\Custom\\Filter::firstPicture',
            'params' => ['derivative' => 'teaser'],
        ],

        // Category tree – extract deepest level as string
        'destination' => [
            'field'  => 'zielgebiet_default',
            'filter' => '\\Custom\\Filter::lastTreeItemAsString',
        ],

        // Dynamic subline – generated from CheapestPrice data
        'subline_dynamic' => [
            'field'  => 'subline_default',
            'filter' => '\\Custom\\Filter::generateSubline',
        ],
    ],
],
```

**Common filter methods (Custom\Filter):**

| Method | Usage | Purpose |
|---|---|---|
| `strip` | ~100% | Strip HTML from text fields |
| `firstPicture` | ~95% | Extract first image as URL + metadata |
| `lastTreeItemAsString` | ~60% | Get deepest category level as string |
| `generateSubline` | ~40% | Dynamic "Fluganreise ab Frankfurt" text |
| `allPictures` | ~10% | All images as array (for galleries) |
| `extractUniqueItemNamesFirstLevel` | ~10% | Unique top-level category names |
| `treeToGroup` | ~10% | Convert category to group assignment |

### touristic (Occupancies & Duration Ranges)

```php
'touristic' => [
    'occupancies'          => [1, 2, 3, 4, 5, 6],
    'occupancy_additional' => [1, 2],
    'duration_ranges'      => [
        [1, 3],     // 1-3 nights (short trips)
        [4, 7],     // 4-7 nights (1 week)
        [8, 99],    // 8+ nights (longer trips)
    ],
],
```

> **Note:** Occupancies `[1, 2, 3, 4, 5, 6]` and `occupancy_additional` `[1, 2]` are **universal** across all production systems. No project uses different values.

**Duration range variants:**

| Pattern | When to use |
|---|---|
| `[[1,3],[4,7],[8,99]]` | Standard tour operators (most common) |
| `[[1,1],[2,3],[4,7],[8,99]]` | When single-day trips need a separate range |
| `[[1,3],[4,7],[8,365]]` | River cruise operators (trips up to 1 year) |
| `[[1,3],[4,7],[8,999]]` | Cruise operators (very long voyages) |

### groups

Groups allow restricting search results to specific subsets (e.g. per brand, agency, or website):

```php
// Most common pattern (~40% of projects):
'groups' => [
    607 => [
        'field'  => 'brand',       // built-in: uses media_object.brand
        'filter' => null,
    ],
],

// Category-based groups (~15%):
'groups' => [
    607 => [
        'field'  => 'website_ausgabe_default',
        'filter' => '\\Custom\\Filter::treeToGroup',
    ],
],
```

**Common group sources:**

| Source | Usage | Purpose |
|---|---|---|
| `brand` | ~40% | Built-in brand field from media object |
| `agencies` | ~40% | Agency assignment |
| `id_pool` | ~30% | Pool assignment |
| Category field (via `treeToGroup`) | ~15% | Custom grouping from PIM category tree |

---

## Image Derivatives

### Standard Set

The most common derivative configuration used by ~70% of projects:

```php
'image_handling' => [
    'processor' => [
        'adapter'      => 'ImageMagick',
        'webp_support' => true,
        'derivatives'  => [
            'thumbnail' => [
                'max_width'  => 125,  'max_height' => 78,
                'preserve_aspect_ratio' => true, 'crop' => true,
                'horizontal_crop' => 'center', 'vertical_crop' => 'center',
                'webp_create' => true, 'webp_quality' => 80,
            ],
            'teaser' => [
                'max_width'  => 480,  'max_height' => 300,
                'preserve_aspect_ratio' => true, 'crop' => true,
                'horizontal_crop' => 'center', 'vertical_crop' => 'center',
                'webp_create' => true, 'webp_quality' => 80,
            ],
            'square' => [
                'max_width'  => 300,  'max_height' => 300,
                'preserve_aspect_ratio' => true, 'crop' => true,
                'horizontal_crop' => 'center', 'vertical_crop' => 'center',
                'webp_create' => true, 'webp_quality' => 80,
            ],
            'detail' => [
                'max_width'  => 730,  'max_height' => 460,
                'preserve_aspect_ratio' => true, 'crop' => true,
                'horizontal_crop' => 'center', 'vertical_crop' => 'center',
                'webp_create' => true, 'webp_quality' => 80,
            ],
            'detail_gallery' => [
                'max_width'  => 1200, 'max_height' => 750,
                'preserve_aspect_ratio' => true, 'crop' => true,
                'horizontal_crop' => 'center', 'vertical_crop' => 'center',
                'webp_create' => true, 'webp_quality' => 80,
            ],
            'bigslide' => [
                'max_width'  => 1920, 'max_height' => 600,
                'preserve_aspect_ratio' => true, 'crop' => true,
                'horizontal_crop' => 'center', 'vertical_crop' => 'center',
                'webp_create' => true, 'webp_quality' => 80,
            ],
        ],
    ],
    'storage' => [
        'provider' => 'filesystem',
        'bucket'   => 'WEBSERVER_DOCUMENT_ROOT/wp-content/uploads/pressmind/images',
    ],
    'http_src' => 'WEBSERVER_HTTP/wp-content/uploads/pressmind/images',
],
```

### Extended Set (Cruise / Complex Products)

Cruise operators and projects with complex layouts add additional derivatives:

```php
// Additional derivatives for complex projects:
'detail_thumb' => [
    'max_width' => 180, 'max_height' => 180,
    // ... standard crop settings
],
'detail_original' => [
    'max_width' => 1280, 'max_height' => 9999,  // uncropped, preserves full height
    'preserve_aspect_ratio' => true, 'crop' => false,
    'webp_create' => true, 'webp_quality' => 80,
],
'slider_fullwidth' => [
    'max_width' => 1920, 'max_height' => 900,
    // ... standard crop settings
],
'small_square' => [
    'max_width' => 480, 'max_height' => 480,
    // ... standard crop settings
],
```

> **Note:** `webp_quality: 80` and `adapter: 'ImageMagick'` are **universal** across all production systems. No project deviates from these values.

---

## Pretty URLs

Two strategy patterns are used:

```php
// Strategy 'count-up' (~60% of projects) – appends a counter for duplicates
'media_types_pretty_url' => [
    607 => [
        'prefix'   => '/reisen/',
        'fields'   => ['name' => 'name'],
        'strategy' => 'count-up',
        'suffix'   => '/',
    ],
],
// Result: /reisen/mallorca-rundreise/
//         /reisen/mallorca-rundreise-2/ (if duplicate)

// Strategy 'none' (~35% of projects) – no duplicate handling
'media_types_pretty_url' => [
    607 => [
        'prefix'   => '/reisen/',
        'fields'   => ['name' => 'name'],
        'strategy' => 'none',
        'suffix'   => '/',
    ],
],
```

> **Best Practice:** Use `count-up` to avoid duplicate URL conflicts. Use `none` only if product names are guaranteed to be unique.

---

## Cache Configuration

All production systems use the same base cache setup:

```php
'cache' => [
    'enabled' => false,  // enable via deployment, not in config
    'adapter' => [
        'name'   => 'Redis',
        'config' => ['host' => '127.0.0.1', 'port' => 6379],
    ],
    'key_prefix'       => 'DATABASE_NAME',
    'update_frequency' => 3600,
    'max_idle_time'    => 86400,
    'types' => ['REST', 'SEARCH', 'SEARCH_FILTER', 'OBJECT', 'MONGODB'],
],
```

**Cache type patterns:**

| Types | Usage | When to use |
|---|---|---|
| `[REST, SEARCH, SEARCH_FILTER, OBJECT, MONGODB]` | ~60% | Full caching (recommended) |
| `[REST, SEARCH, SEARCH_FILTER, OBJECT]` | ~30% | Without MongoDB query caching |
| `[REST, SEARCH, OBJECT]` | ~10% | Minimal caching (no filter caching) |

> **Note:** `cache.enabled` is `false` in all checked development configs. It is typically enabled via environment variables or deployment scripts in production.

---

## OpenSearch Integration

Only ~20% of projects configure OpenSearch, and it is typically used for fulltext search within the MongoDB search flow:

```php
'search_opensearch' => [
    'enabled' => false,
    'enabled_in_mongo_search' => true,  // use OpenSearch for pm-t fulltext
    'uri'      => 'http://opensearch:9200',
    'user'     => '',
    'password' => '',
    'number_of_shards'   => 1,
    'number_of_replicas' => 0,
    'index' => [
        607 => [
            'headline_default' => ['type' => 'text'],
            'subline_default'  => ['type' => 'text', 'boost' => 2],
            'zielgebiet_default' => ['type' => 'text', 'boost' => 2],
            'code' => ['type' => 'keyword'],
        ],
    ],
],
```

> **Note:** `enabled: false` with `enabled_in_mongo_search: true` means OpenSearch is only used as a fulltext backend for the `pm-t` search parameter, not as a standalone search engine.

---

## Custom Import Hooks

The most common import hook pattern (~60% of projects):

```php
'media_type_custom_import_hooks' => [
    607 => ['Custom\\IBETeamImport'],
],
```

For post-import hooks (rare, ~7%):

```php
'media_type_custom_post_import_hooks' => [
    607 => ['Custom\\TreeBuilder'],
],
```

---

## Multi-Language Setup

~87% of projects are German-only. For multi-language setups:

```php
'sections' => [
    'allowed' => ['Default'],
    'default' => 'Default',
    'fallback' => 'Default',
    'fallback_on_empty_values' => true,
    // Map PIM section names to 'default'
    'replace' => [
        'regular_expression' => '/DE|EN|FR|NL/m',
        'replacement' => 'default',
    ],
],
'languages' => [
    'allowed' => ['de', 'en'],
    'default' => 'de',
],
```

---

## Complete Example: Standard Tour Operator

A typical configuration for a bus/flight tour operator with one main product type, destinations, and day trips:

```php
<?php
$config = [
    'development' => [
        'server' => [
            'document_root'  => 'BASE_PATH',
            'webserver_http' => 'https://dev.example.com',
            'php_cli_binary' => '/usr/bin/php',
            'timezone'       => 'Europe/Berlin',
        ],
        'database' => [
            'username' => 'dev_user',
            'password' => 'dev_pass',
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'dbname'   => 'travelshop_dev',
            'engine'   => 'MySQL',
        ],
        'rest' => [
            'client' => [
                'api_key'      => '<from pressmind>',
                'api_user'     => '<from pressmind>',
                'api_password' => '<from pressmind>',
            ],
            'server' => [
                'api_endpoint' => '/wp-content/themes/travelshop/rest',
            ],
        ],
        'logging' => [
            'mode'       => ['ERROR'],
            'categories' => ['ALL'],
            'storage'    => 'database',
            'lifetime'   => 86400,
        ],
        'data' => [
            'touristic' => [
                'origins' => ['0'],
                'disable_touristic_data_import' => [608, 609],
                'generate_single_room_index'    => false,
                'max_offers_per_product'         => 5000,
                'date_filter' => [
                    'active'          => true,
                    'orientation'     => 'arrival',
                    'offset'          => 0,
                    'allowed_states'  => [0, 1, 2, 4, 5],
                    'max_date_offset' => 730,
                ],
                'housing_option_filter' => [
                    'active'         => true,
                    'allowed_states' => [0, 1, 2, 3],
                ],
                'transport_filter' => [
                    'active'         => true,
                    'allowed_states' => [0, 2, 3],
                ],
            ],
            'media_type_custom_import_hooks' => [
                607 => ['Custom\\IBETeamImport'],
            ],
            'primary_media_type_ids' => [607, 608, 609],
            'media_types' => [
                607 => 'Reise',
                608 => 'Tagesfahrt',
                609 => 'Zielgebiet',
            ],
            'media_types_pretty_url' => [
                607 => ['prefix' => '/reisen/',      'fields' => ['name' => 'name'], 'strategy' => 'count-up', 'suffix' => '/'],
                608 => ['prefix' => '/tagesfahrt/',  'fields' => ['name' => 'name'], 'strategy' => 'count-up', 'suffix' => '/'],
                609 => ['prefix' => '/zielgebiet/',  'fields' => ['name' => 'name'], 'strategy' => 'count-up', 'suffix' => '/'],
            ],
            'media_types_allowed_visibilities' => [
                607 => [30], 608 => [30], 609 => [30],
            ],
            'sections' => [
                'allowed' => ['Default'], 'default' => 'Default',
                'fallback' => 'Default', 'fallback_on_empty_values' => true,
            ],
            'languages' => ['allowed' => ['de'], 'default' => 'de'],
            'search_mongodb' => [
                'enabled'  => true,
                'database' => [
                    'uri' => 'mongodb://user:pass@127.0.0.1:27017/db?authSource=admin',
                    'db'  => 'travelshop_dev',
                ],
                'search' => [
                    'build_for' => [
                        607 => [['language' => null, 'origin' => 0]],
                        608 => [['language' => null, 'origin' => 0]],
                        609 => [['language' => null, 'origin' => 0]],
                    ],
                    'categories' => [
                        607 => [
                            'zielgebiet_default' => null,
                            'reiseart_default'   => null,
                        ],
                    ],
                    'descriptions' => [
                        607 => [
                            'headline' => ['field' => 'headline_default', 'filter' => '\\Custom\\Filter::strip'],
                            'subline'  => ['field' => 'subline_default',  'filter' => '\\Custom\\Filter::strip'],
                            'image'    => ['field' => 'bilder_default',   'filter' => '\\Custom\\Filter::firstPicture', 'params' => ['derivative' => 'teaser']],
                        ],
                    ],
                    'touristic' => [
                        'occupancies'          => [1, 2, 3, 4, 5, 6],
                        'occupancy_additional' => [1, 2],
                        'duration_ranges'      => [[1, 3], [4, 7], [8, 99]],
                    ],
                    'groups' => [
                        607 => ['field' => 'brand', 'filter' => null],
                    ],
                    'five_dates_per_month_list' => true,
                    'possible_duration_list'    => false,
                    'allow_invalid_offers'      => false,
                ],
            ],
        ],
        'cache' => [
            'enabled' => false,
            'adapter' => ['name' => 'Redis', 'config' => ['host' => '127.0.0.1', 'port' => 6379]],
            'key_prefix' => 'DATABASE_NAME',
            'types' => ['REST', 'SEARCH', 'SEARCH_FILTER', 'OBJECT', 'MONGODB'],
        ],
        'image_handling' => [
            'processor' => [
                'adapter'      => 'ImageMagick',
                'webp_support' => true,
                'derivatives'  => [
                    'thumbnail'      => ['max_width' => 125, 'max_height' => 78,  'preserve_aspect_ratio' => true, 'crop' => true, 'horizontal_crop' => 'center', 'vertical_crop' => 'center', 'webp_create' => true, 'webp_quality' => 80],
                    'teaser'         => ['max_width' => 480, 'max_height' => 300, 'preserve_aspect_ratio' => true, 'crop' => true, 'horizontal_crop' => 'center', 'vertical_crop' => 'center', 'webp_create' => true, 'webp_quality' => 80],
                    'detail'         => ['max_width' => 730, 'max_height' => 460, 'preserve_aspect_ratio' => true, 'crop' => true, 'horizontal_crop' => 'center', 'vertical_crop' => 'center', 'webp_create' => true, 'webp_quality' => 80],
                    'detail_gallery' => ['max_width' => 1200,'max_height' => 750, 'preserve_aspect_ratio' => true, 'crop' => true, 'horizontal_crop' => 'center', 'vertical_crop' => 'center', 'webp_create' => true, 'webp_quality' => 80],
                    'bigslide'       => ['max_width' => 1920,'max_height' => 600, 'preserve_aspect_ratio' => true, 'crop' => true, 'horizontal_crop' => 'center', 'vertical_crop' => 'center', 'webp_create' => true, 'webp_quality' => 80],
                ],
            ],
            'storage'  => ['provider' => 'filesystem', 'bucket' => 'WEBSERVER_DOCUMENT_ROOT/wp-content/uploads/pressmind/images'],
            'http_src' => 'WEBSERVER_HTTP/wp-content/uploads/pressmind/images',
        ],
        'view_scripts' => ['base_path' => 'APPLICATION_PATH/template-parts/pm-views'],
    ],
    'testing'    => [],
    'production' => [
        'server'   => ['webserver_http' => 'https://www.example.com'],
        'database' => ['username' => 'prod_user', 'password' => 'prod_pass', 'dbname' => 'travelshop_prod'],
    ],
];
```

---

## Complete Example: Cruise Operator

A cruise operator with ships, cabins, excursions, and extended date ranges:

```php
// Key differences from standard tour operator:
'data' => [
    'touristic' => [
        'origins' => ['0'],
        'disable_touristic_data_import' => [702, 703, 704, 705],  // non-touristic content types
        'max_offers_per_product' => 5000,
        'date_filter' => [
            'active'          => true,
            'orientation'     => 'departure',
            'offset'          => 1,
            'allowed_states'  => [0, 1, 2, 4, 5],
            'max_date_offset' => 910,  // ~2.5 years for cruise planning
        ],
    ],
    'primary_media_type_ids' => [701, 702, 703, 704, 705, 706, 707, 708, 709, 710, 711],
    'media_types' => [
        701 => 'Reise',
        702 => 'Unterkunft',           // ship
        703 => 'Kabinenkategorie',
        704 => 'Schiff',
        705 => 'Ausfluege',
        706 => 'Verpflegung',
        707 => 'Vor_nachprogramm',
        708 => 'Crew',
        709 => 'Termine',
        710 => 'Agentur',
        711 => 'Ausflug',
    ],
    'search_mongodb' => [
        'search' => [
            'build_for' => [
                701 => [['language' => null, 'origin' => 0]],
                702 => [['language' => null, 'origin' => 0]],
                703 => [['language' => null, 'origin' => 0]],
                709 => [['language' => null, 'origin' => 0]],
            ],
            'touristic' => [
                'occupancies'          => [1, 2, 3, 4, 5, 6],
                'occupancy_additional' => [1, 2],
                'duration_ranges'      => [[1, 3], [4, 7], [8, 999]],  // up to 999 nights
            ],
            'five_dates_per_month_list' => true,
            'possible_duration_list'    => true,   // important for variable cruise durations
            'allow_invalid_offers'      => [702, 703, 704, 705],  // non-touristic types
        ],
    ],
],
```

---

## Common Pitfalls

| Problem | Cause | Solution |
|---|---|---|
| Products not appearing in search | `build_for` missing for the object type | Add the object type to `search_mongodb.search.build_for` |
| Import takes very long | `max_offers_per_product` too high or no date_filter | Enable date_filter, reduce max_offers_per_product |
| Wrong prices in search results | State filters not configured | Set `housing_option_filter` and `transport_filter` |
| Images not processing | Adapter mismatch | Use `ImageMagick` (PHP extension) or `ImageMagickCLI` (command line) |
| Duplicate URLs | Pretty URL strategy `none` | Switch to `count-up` strategy |
| Search results missing categories | Category field not in `categories` config | Add the field name to `search_mongodb.search.categories` |
| No filter counts in search | `getFilters` not enabled | Set `$QueryFilter->getFilters = true` |
| Empty subline in search results | Field not mapped in `descriptions` | Add the field to `search_mongodb.search.descriptions` |
| Touristic data not indexed | Object type in `disable_touristic_data_import` | Remove the type ID from the array |
| Very old departures in results | `date_filter.offset` is 0 and `active` is false | Enable the date_filter with appropriate offset |
