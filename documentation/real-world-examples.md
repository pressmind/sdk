# Real-World SDK Usage Patterns

[← Back to Template Interface](template-interface.md) | [→ Architecture](architecture.md)

---

## Table of Contents

- [Overview](#overview)
- [Project Structure Conventions](#project-structure-conventions)
- [Pattern 1: AJAX Search Endpoint](#pattern-1-ajax-search-endpoint)
- [Pattern 2: Query – SDK Search Pipeline](#pattern-2-query--sdk-search-pipeline)
- [Pattern 3: Category Filter UI](#pattern-3-category-filter-ui)
- [Pattern 4: Teaser Card (Search Result Item)](#pattern-4-teaser-card-search-result-item)
- [Pattern 5: Price Box (Detail Page Sidebar)](#pattern-5-price-box-detail-page-sidebar)
- [Pattern 6: Price Table / Booking Offers by Date](#pattern-6-price-table--booking-offers-by-date)
- [Pattern 7: Product Calendar](#pattern-7-product-calendar)
- [Pattern 8: Booking Entrypoint (IBE Integration)](#pattern-8-booking-entrypoint-ibe-integration)
- [Pattern 9: Transport Type Icons](#pattern-9-transport-type-icons)
- [Pattern 10: Custom Index Filters (MongoDB)](#pattern-10-custom-index-filters-mongodb)
- [Pattern 11: Wishlist / Favorites](#pattern-11-wishlist--favorites)
- [Pattern 12: Micro-Templates](#pattern-12-micro-templates)
- [Pattern 13: Custom Import Hooks](#pattern-13-custom-import-hooks)

---

## Overview

This document presents real-world SDK usage patterns extracted from ~80 production travelshop installations. The examples are anonymized – no project names are mentioned. Instead, the focus is on **recurring patterns** that have proven effective across many deployments.

The analysis covers:
- **712** files using `getCheapestPrice`
- **808** files integrating MongoDB search
- **362** files working with booking packages and dates
- **246** files using MongoDB Conditions directly
- **712** files accessing CategoryTree data

---

## Project Structure Conventions

Most travelshop projects follow a consistent directory structure:

```
project-root/
├── bootstrap.php              # SDK initialization, config loading
├── pm-config.php              # pressmind config.json overrides
├── pm-ajax-endpoint.php       # Lightweight AJAX endpoint (no WordPress stack)
├── src/
│   ├── Search.php             # ⚠️ DEPRECATED – replaced by SDK's Query::getResult()
│   ├── BuildSearch.php        # ⚠️ DEPRECATED – replaced by SDK's Query::fromRequest()
│   ├── PriceHandler.php       # Price formatting and discount logic
│   ├── IB3Tools.php           # IBE link generation
│   ├── Template.php           # Template rendering utility
│   └── Calendar.php           # Calendar/date grouping utilities
├── Custom/
│   ├── Filter.php             # MongoDB index field filters
│   ├── MediaType/             # Custom MediaType classes (per object type)
│   │   ├── Reise.php
│   │   ├── Tagesfahrt.php
│   │   └── ...
│   └── IBETeamImport.php      # Custom import hooks
├── template-parts/
│   ├── pm-views/              # SDK view templates (Teaser, Detail)
│   │   ├── Reise_Teaser1.php
│   │   ├── Reise_Detail1.php
│   │   ├── Reise_Detail2.php  # Typically the price/booking section
│   │   ├── Reise_Detail3.php
│   │   └── detail-blocks/     # Reusable detail page blocks
│   │       ├── price-box.php
│   │       ├── booking-entrypoint.php
│   │       ├── booking-offers-per-date.php
│   │       └── booking-offers-calendar.php
│   ├── pm-search/             # Search page templates
│   │   ├── result.php
│   │   ├── result-items.php
│   │   ├── filter-vertical.php
│   │   ├── filter-horizontal.php
│   │   └── filter/            # Individual filter components
│   │       ├── category-tree-horizontal.php
│   │       ├── transport_type.php
│   │       ├── price-range.php
│   │       └── order.php
│   ├── micro-templates/       # Small reusable template snippets
│   │   ├── price-1.php
│   │   ├── discount.php
│   │   ├── duration.php
│   │   ├── transport-icon.php
│   │   ├── travel-date-range.php
│   │   └── month-name.php
│   └── layout-blocks/         # CMS layout blocks
│       ├── product-teaser.php
│       ├── product-calendar.php
│       └── recommendation.php
├── cli/
│   ├── import.php             # CLI import runner
│   └── image_processor.php    # CLI image processing
└── config-theme.php           # Theme-level constants (TS_LANGUAGE_CODE, etc.)
```

**Naming Convention for Views:**

```
{MediaTypeName}_{TemplatePurpose}{Number}.php
```

Examples:
- `Reise_Teaser1.php` – Travel product listing card
- `Reise_Detail2.php` – Travel product detail, section 2 (typically pricing)
- `Kabinen_Detail2.php` – Cabin detail, section 2
- `Tagesfahrt_Detail2.php` – Day trip detail, section 2
- `Reise_DetailPrint.php` – Print-optimized detail view

---

## Pattern 1: AJAX Search Endpoint

**Frequency:** Found in **~90%** of all projects

Nearly every travelshop has a lightweight AJAX endpoint that bypasses the CMS stack (e.g. WordPress) for fast search responses:

```php
<?php
// pm-ajax-endpoint.php
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;

require_once 'vendor/autoload.php';
require_once 'bootstrap.php';

header('Content-type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'search') {
    $QueryFilter = new Filter();
    $QueryFilter->request = $_GET;
    $QueryFilter->page_size = 12;
    $QueryFilter->getFilters = true;

    $args = Query::getResult($QueryFilter);

    // Render search results + filters as HTML fragments
    ob_start();
    require 'template-parts/pm-search/result.php';
    $html_result = ob_get_clean();

    ob_start();
    require 'template-parts/pm-search/filter-vertical.php';
    $html_filter = ob_get_clean();

    echo json_encode([
        'error'  => false,
        'count'  => (int) $args['total_result'],
        'html'   => [
            'search-result' => $html_result,
            'search-filter' => $html_filter,
        ],
    ]);
    exit;
}
```

**Key aspects:**
- Bypasses full CMS loading for performance (no `admin-ajax.php`)
- Returns pre-rendered HTML fragments for partial page updates
- `$_GET` is passed directly – all `pm-*` params are parsed by `Query`

---

## Pattern 2: Query – SDK Search Pipeline

**Frequency:** Used in **all** projects

> **Note:** Older projects use custom `BuildSearch` and `Search` wrapper classes per project. These are **deprecated** and replaced by the SDK's `Pressmind\Search\Query`.

### Basic Usage

```php
<?php
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;

// Static config (typically set once in bootstrap.php)
Query::$language_code = 'de';
Query::$touristic_origin = 0;

// Build filter and execute
$QueryFilter = new Filter();
$QueryFilter->request = $_GET;        // all pm-* params are parsed automatically
$QueryFilter->page_size = 12;
$QueryFilter->getFilters = true;      // also return filter aggregations

$args = Query::getResult($QueryFilter);
```

### Filter Properties

| Property | Default | Description |
|---|---|---|
| `request` | `[]` | Raw request array (`$_GET`) with `pm-*` params |
| `occupancy` | `2` | Default occupancy (persons) |
| `page_size` | `12` | Items per page |
| `getFilters` | `false` | Return filter aggregations (categories, ranges) |
| `returnFiltersOnly` | `false` | Skip items, return only filters |
| `ttl_filter` | `null` | Cache TTL for filter query (seconds) |
| `ttl_search` | `null` | Cache TTL for search query (seconds) |
| `output` | `null` | Output mode (`null` or `'date_list'`) |
| `preview_date` | `null` | Preview date override (`DateTime`) |
| `custom_conditions` | `[]` | Additional MongoDB conditions |
| `allowed_visibilities` | `[30]` | Visibility filter |
| `search_type` | `SearchType::DEFAULT` | Search type enum |

### Result Structure

```php
$args = [
    // Pagination
    'total_result' => 150,
    'current_page' => 1,
    'pages'        => 13,
    'page_size'    => 12,

    // Items (template-ready)
    'items' => [
        [
            'id_media_object'    => 12345,
            'id_object_type'     => 607,
            'url'                => '/reisen/spanien-rundreise',
            'headline'           => 'Spanien Rundreise',
            'subline'            => 'Fluganreise ab Frankfurt',
            'image'              => ['url' => '...', 'copyright' => '...'],
            'cheapest_price'     => (object)[
                'price_total'                   => 899.0,
                'price_regular_before_discount' => 999.0,
                'earlybird_discount'            => 100.0,
                'duration'                      => 8,
                'transport_type'                => 'FLUG',
                'option_board_type'             => 'Halbpension',
                'state'                         => 100, // 100=Buchbar, 200=Anfrage, 300=Stop
                'date_departures'               => [DateTime, DateTime],
            ],
            'dates_per_month'      => [...],
            'possible_durations'   => [7, 10, 14],
            'departure_date_count' => 25,
            'is_running'           => false,
            'sold_out'             => false,
        ],
    ],

    // Filter aggregations (for UI)
    'categories'           => [...],  // field_name → level → id_item → {name, count_in_system, count_in_search}
    'board_types'          => [...],
    'transport_types'      => [...],
    'startingpoint_options'=> [...],

    // Ranges (for sliders)
    'price_min'     => 299.0,    'price_max'     => 4500.0,
    'duration_min'  => 3,        'duration_max'  => 21,
    'departure_min' => DateTime, 'departure_max' => DateTime,

    // Debug
    'mongodb' => ['duration_filter_ms' => 12.5, 'duration_search_ms' => 8.3],
];
```

### Migration from BuildSearch/Search

```php
// ❌ OLD (deprecated)
$search = BuildSearch::fromRequestMongoDB($_GET, 'pm', true, 12);
$result = $search->getResult(true, false);
// + manual document transformation in custom Search class

// ✅ NEW
$QueryFilter = new Filter();
$QueryFilter->request = $_GET;
$QueryFilter->page_size = 12;
$QueryFilter->getFilters = true;
$args = Query::getResult($QueryFilter);
// → complete, transformed result
```

**Key advantages:**
- **One SDK class** – no custom code copied into each project
- **Complete result** – items, filters, ranges, calendar, debug in one call
- **Runtime cache** – duplicate queries within a request are deduplicated
- **Self-contained** – each item has all data for rendering, no N+1 queries

---

## Pattern 3: Category Filter UI

**Frequency:** Found in **~85%** of all projects

Filters are rendered from `$args['categories']` – the aggregation data from `Query::getResult()`:

```php
<?php // template-parts/pm-search/filter-vertical.php ?>
<form id="filter" method="GET">
    <input type="hidden" name="pm-ot" value="<?php echo implode(',', $args['id_object_type']); ?>">

    <?php foreach (TS_FILTERS as $filter) {
        $fieldname = $filter['fieldname'];
        if (empty($args['categories'][$fieldname])) {
            continue;
        }
        $active = !empty($_GET['pm-c'][$fieldname])
            ? explode(',', $_GET['pm-c'][$fieldname])
            : [];
    ?>
        <div class="list-filter-box">
            <div class="list-filter-box--title"><?php echo $filter['name']; ?></div>
            <?php foreach ($args['categories'][$fieldname] as $level => $items) { ?>
                <?php foreach ($items as $id_item => $cat) { ?>
                    <label class="form-check">
                        <input type="checkbox" class="filter-item"
                               name="pm-c[<?php echo $fieldname; ?>]"
                               value="<?php echo $id_item; ?>"
                               data-behavior="<?php echo $filter['behavior']; ?>"
                               <?php echo in_array($id_item, $active) ? 'checked' : ''; ?> />
                        <?php echo $cat->name; ?>
                        <span class="count">(<?php echo $cat->count_in_search; ?>)</span>
                    </label>
                <?php } ?>
            <?php } ?>
        </div>
    <?php } ?>

    <button type="button" class="btn btn-secondary filter-prompt">Filter anwenden</button>
</form>
```

**Key aspects:**
- `TS_FILTERS` defines the available filters with `fieldname`, `name`, and `behavior` (OR/AND)
- Active filters are restored from `$_GET['pm-c']` for checkbox state
- `count_in_search` shows how many results match each option

---

## Pattern 4: Teaser Card (Search Result Item)

**Frequency:** Found in **every project**

Renders a single product from the search result. All data comes from the MongoDB document – no additional queries:

```php
<?php
/**
 * @var array $item – one element from $args['items'] (Query::getResult)
 */
$price = $item['cheapest_price'];
$url   = SITE_URL . $item['url'];
?>

<div class="product-teaser-card">
    <?php if (!empty($item['image'])) { ?>
        <a href="<?php echo $url; ?>">
            <img src="<?php echo $item['image']['url']; ?>"
                 alt="<?php echo htmlspecialchars($item['headline']); ?>"
                 loading="lazy" />
        </a>
    <?php } ?>

    <div class="product-teaser-card--body">
        <h3><a href="<?php echo $url; ?>"><?php echo $item['headline']; ?></a></h3>

        <?php if (!empty($item['subline'])) { ?>
            <p class="subline"><?php echo $item['subline']; ?></p>
        <?php } ?>

        <?php if (!empty($price)) { ?>
            <div class="price-info">
                <span class="duration"><?php echo $price->duration; ?> Tage</span>
                <span class="price">
                    ab <strong><?php echo number_format($price->price_total, 2, ',', '.'); ?> &euro;</strong>
                </span>
            </div>
        <?php } ?>

        <?php if (!empty($item['dates_per_month'])) { ?>
            <div class="departure-dates">
                <?php foreach ($item['dates_per_month'] as $month) { ?>
                    <span class="month-badge">
                        <?php echo $month['five_dates_in_month'][0]['date_departure']->format('M'); ?>
                    </span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
```

**Key aspects:**
- **No database queries** – everything comes from the MongoDB search document
- Images use derivative URLs embedded at index time
- Month badges use the `five_dates_per_month` aggregation

---

## Pattern 5: Price Box (Detail Page Sidebar)

**Frequency:** Found in **~70%** of projects

The sticky sidebar on the detail page. Uses `CheapestPriceSpeed` from MySQL (not MongoDB):

```php
<?php
/** @var Pressmind\ORM\Object\MediaObject $mo */
/** @var Pressmind\ORM\Object\CheapestPriceSpeed $cheapest_price */
$mo = $args['media_object'];
$cp = $args['cheapest_price'];
?>

<?php if (!empty($cp)) { ?>
    <div class="detail-price-box">
        <div class="price">
            <span><?php echo $cp->duration; ?> Tage ab</span>
            <strong><?php echo number_format($cp->price_total, 2, ',', '.'); ?> &euro;</strong>
        </div>
        <span>
            <?php echo $cp->date_departure->format('d.m.'); ?> -
            <?php echo $cp->date_arrival->format('d.m.Y'); ?>
        </span>
        <a class="btn btn-primary" href="#detail-booking">Termine &amp; Preise</a>
    </div>
<?php } else { ?>
    <div class="detail-price-box">
        <p>Nur auf Anfrage buchbar.</p>
        <a class="btn btn-primary" href="#detail-booking">Anfragen</a>
    </div>
<?php } ?>
```

**Key aspects:**
- Handles "has price" and "on request" states
- Links to the booking section via anchor

---

## Pattern 6: Price Table / Booking Offers by Date

**Frequency:** Found in **~60%** of projects

The most complex template – iterates booking packages, dates, and housing options:

```php
<?php
use Pressmind\Search\CheapestPrice;

/** @var Pressmind\ORM\Object\MediaObject $mo */
$mo = $args['media_object'];

// Step 1: Collect rows (date + cheapest price per occupancy)
$rows = [];
foreach ($mo->booking_packages as $bp) {
    foreach ($bp->dates as $date) {
        $row = ['date' => $date, 'bp' => $bp, 'occ' => []];

        foreach ($date->getHousingOptions() as $opt) {
            $filter = new CheapestPrice();
            $filter->id_option = $opt->id;
            $filter->id_booking_package = $opt->id_booking_package;
            $filter->id_date = $date->id;
            $price = $mo->getCheapestPrice($filter);

            $occ = $opt->occupancy;
            if (empty($row['occ'][$occ]) || $row['occ'][$occ]->price_total > $price->price_total) {
                $row['occ'][$occ] = $price;
            }
        }
        $rows[] = $row;
    }
}

// Step 2: Render grouped by month
$current_month = '';
foreach ($rows as $row) {
    $date   = $row['date'];
    $dbl    = $row['occ'][2] ?? null;  // double room
    $sgl    = $row['occ'][1] ?? null;  // single room
    $month  = $date->departure->format('m-Y');

    if ($current_month !== $month) {
        $current_month = $month;
        echo '<h3>' . $date->departure->format('F Y') . '</h3>';
    }
?>
    <div class="booking-row">
        <span class="col-duration"><?php echo $row['bp']->duration; ?> Tage</span>
        <span class="col-dates">
            <?php echo $date->departure->format('d.m.') . ' - ' . $date->arrival->format('d.m.Y'); ?>
        </span>
        <span class="col-price-dbl">
            <?php echo $dbl ? number_format($dbl->price_total, 2, ',', '.') . ' €' : '-'; ?>
        </span>
        <span class="col-price-sgl">
            <?php echo $sgl ? number_format($sgl->price_total, 2, ',', '.') . ' €' : '-'; ?>
        </span>
    </div>
<?php } ?>
```

**Key aspects:**
- Nested loop: `booking_packages → dates → getHousingOptions()`
- Cheapest price per occupancy (single/double) per date
- Grouped by month with headers
- `CheapestPrice` filter pins to specific `id_option`, `id_booking_package`, `id_date`

---

## Pattern 7: Product Calendar

**Frequency:** Found in **~30%** of projects

Groups products by departure month – one row per departure date:

```php
<?php
use Pressmind\Search\CheapestPrice;

// Pre-aggregated calendar items, grouped by month
$items = Calendar::get();
$grouped = [];
foreach ($items as $item) {
    $item->date_departure = new DateTime($item->date_departure);
    $grouped[$item->date_departure->format('m.Y')][] = $item;
}

foreach ($grouped as $month_items) { ?>
    <h3><?php echo $month_items[0]->date_departure->format('F Y'); ?></h3>

    <?php foreach ($month_items as $item) {
        $mo = new \Pressmind\ORM\Object\MediaObject($item->id);
        $moc = $mo->getDataForLanguage(TS_LANGUAGE_CODE);

        $filter = new CheapestPrice();
        $filter->date_from = $filter->date_to = $item->date_departure;
        $cp = $mo->getCheapestPrice($filter);
    ?>
        <div class="product-calendar-row">
            <span class="col-date">
                <?php echo $cp->date_departure->format('d.m.') . ' - ' . $cp->date_arrival->format('d.m.Y'); ?>
            </span>
            <span class="col-name"><?php echo $moc->headline_default; ?></span>
            <span class="col-duration"><?php echo $cp->duration; ?> Tage</span>
            <span class="col-price"><?php echo number_format($cp->price_total, 2, ',', '.'); ?> &euro;</span>
        </div>
    <?php } ?>
<?php } ?>
```

**Key aspects:**
- `CheapestPrice` filter pinned to the exact departure date
- Grouped by month with headers

---

## Pattern 8: Booking Entrypoint (IBE Integration)

**Frequency:** Found in **~65%** of projects

Interactive booking configurator on the detail page – renders filter options (transport, duration, airport) from the product's calendar data:

```php
<?php
/** @var \Pressmind\ORM\Object\CheapestPriceSpeed $cp */
$cp = $args['cheapest_price'];

if ($cp->is_virtual_created_price) {
    return; // virtual prices cannot be booked
}

// Build calendar from the current cheapest price context
$cal_filter = new \Pressmind\Search\CalendarFilter();
$cal_filter->occupancy      = $cp->option_occupancy;
$cal_filter->duration        = $cp->duration;
$cal_filter->transport_type  = $cp->transport_type;
$cal_filter->airport         = $cp->transport_1_airport;

$calendar = $args['media_object']->getCalendar($cal_filter);
if (empty($calendar->calendar)) {
    return;
}
$filter = $calendar->filter;
?>

<div class="booking-filter">
    <?php if (count($filter['transport_types']) > 1) { ?>
        <div class="booking-filter-item">
            <?php foreach ($filter['transport_types'] as $type => $data) { ?>
                <label>
                    <input type="radio" name="transport_type" value="<?php echo $type; ?>"
                           data-filter='<?php echo json_encode($data); ?>'
                           <?php echo $cp->transport_type == $type ? 'checked' : ''; ?> />
                    <?php echo $type; ?>
                </label>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (count($filter['durations']) > 1) { ?>
        <div class="booking-filter-item">
            <?php foreach ($filter['durations'] as $dur => $data) { ?>
                <label>
                    <input type="radio" name="duration" value="<?php echo $dur; ?>"
                           data-filter='<?php echo json_encode($data); ?>'
                           <?php echo $cp->duration == $dur ? 'checked' : ''; ?> />
                    <?php echo $dur; ?> Tage
                </label>
            <?php } ?>
        </div>
    <?php } ?>
</div>
```

**Key aspects:**
- `CalendarFilter` is initialized from the current cheapest price context
- `data-filter` JSON attributes enable client-side cascading filter logic
- Filters are hidden when only one option exists

---

## Pattern 9: Transport Type Icons

**Frequency:** Found in **~50%** of projects

Maps PIM transport type enums to SVG icons via a lookup array:

```php
<?php
// template-parts/micro-templates/transport-icon.php
$icons = [
    'FLUG'   => '<svg class="icon-plane" viewBox="0 0 24 24"><path d="M16 10h4..."/></svg>',
    'BUS'    => '<svg class="icon-bus"   viewBox="0 0 24 24"><path d="..."/></svg>',
    'PKW'    => '<svg class="icon-car"   viewBox="0 0 24 24"><path d="..."/></svg>',
    'BAHN'   => '<svg class="icon-train" viewBox="0 0 24 24"><path d="..."/></svg>',
    'SCHIFF' => '<svg class="icon-ship"  viewBox="0 0 24 24"><path d="..."/></svg>',
];
echo $icons[$args['transport_type']] ?? '<svg class="icon-default" viewBox="0 0 24 24"><path d="..."/></svg>';
?>
```

**Key aspects:**
- Transport type enums from pressmind PIM: `FLUG`, `BUS`, `PKW`, `BAHN`, `SCHIFF`
- Inline SVG for performance (no HTTP requests)
- Fallback icon for unknown types via null coalescing

---

## Pattern 10: Custom Index Filters (MongoDB)

**Frequency:** Found in **100%** of projects

`Custom/Filter.php` provides methods called during MongoDB index creation. They transform raw data into search-optimized values. Referenced in `config.json` → `search_mongodb.descriptions`:

```php
<?php
namespace Custom;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;

class Filter
{
    public $mediaObject = null; // set by Indexer for instance methods
    public $agency = null;

    // Strip HTML tags – used for every text field
    public static function strip($str)
    {
        return trim(strip_tags((string) $str));
    }

    // Extract first image as URL + metadata
    public static function firstPicture($array, $derivative, $section = null)
    {
        if (empty($array)) {
            return null;
        }
        foreach ($array as $pic) {
            if (!$pic->disabled) {
                $p = new Picture();
                $p->fromArray($pic);
                return [
                    'url'       => $p->getUri($derivative, false, $section),
                    'copyright' => $p->copyright,
                    'caption'   => $p->caption,
                ];
            }
        }
        return null;
    }

    // Dynamic subline: "Fluganreise ab Frankfurt und München"
    public function generateSubline($value)
    {
        if (!empty(strip_tags($value ?? ''))) {
            return $value;
        }
        $airports = [];
        $types = [];
        $filter = new \Pressmind\Search\CheapestPrice();
        $filter->agency = $this->agency;

        foreach ($this->mediaObject->getCheapestPrices($filter) as $price) {
            $types[$price->transport_type] = true;
            if ($price->transport_type == 'FLUG' && !empty($price->transport_1_airport_name)) {
                $airports[] = $price->transport_1_airport_name;
            }
        }
        $airports = array_unique($airports);
        $parts = [];
        if (!empty($types['FLUG'])) {
            $parts[] = 'Fluganreise' . (count($airports) ? ' ab ' . implode(' und ', $airports) : '');
        }
        if (!empty($types['BUS'])) {
            $parts[] = 'Busreise';
        }
        return implode(' oder ', $parts);
    }
}
```

**Key aspects:**
- **Static methods** (e.g. `strip`, `firstPicture`) are called for each field during index build
- **Instance methods** (e.g. `generateSubline`) have access to `$this->mediaObject`
- Executed at import time, not at search time – keeps queries fast

---

## Pattern 11: Wishlist / Favorites

**Frequency:** Found in **~40%** of projects

Wishlist IDs are stored client-side (localStorage) and resolved via `Query`:

```php
<?php
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;

$ids = $_POST['ids'] ?? [];
if (empty($ids)) {
    echo json_encode(['items' => []]);
    exit;
}

$QueryFilter = new Filter();
$QueryFilter->request = [];
$QueryFilter->custom_conditions = [
    new \Pressmind\Search\Condition\MongoDB\MediaObject($ids),
];
$QueryFilter->page_size = count($ids);

$args = Query::getResult($QueryFilter);
// $args['items'] → template-ready, render as teasers
```

**HTML (add/remove buttons):**

```html
<div data-pm-id="123" data-pm-ot="607" class="add-to-wishlist">♡</div>
<div data-pm-id="123" class="remove-from-wishlist">entfernen</div>
```

**Key aspects:**
- Client-side storage, no server session
- `custom_conditions` with `MongoDB\MediaObject` to query specific IDs

---

## Pattern 12: Micro-Templates

**Frequency:** Found in **every project**

Small, single-purpose snippets rendered via output buffering:

```php
<?php
// Usage: echo Template::render('template-parts/micro-templates/duration.php', ['duration' => 7]);
class Template
{
    public static function render($path, $args = [])
    {
        ob_start();
        require $path;
        return ob_get_clean();
    }
}
```

| Template | Output | Arguments |
|---|---|---|
| `price-1.php` | `ab 899,00 €` | `cheapest_price` |
| `discount.php` | Early bird badge | `cheapest_price` |
| `duration.php` | `7 Tage` / `1 Tag` | `duration` |
| `travel-date-range.php` | `15.06. - 22.06.2026` | `date_departure`, `date_arrival` |
| `transport-icon.php` | SVG icon | `transport_type` |
| `transport_type_human_string.php` | `Fluganreise` | `transport_type` |

---

## Pattern 13: Custom Import Hooks

**Frequency:** Found in **~25%** of projects

Extend the import pipeline via `config.json`:

```json
{
  "import": {
    "hooks": {
      "MediaObjectData": {
        "after": [{"class": "Custom\\IBETeamImport", "method": "afterImport"}]
      }
    }
  }
}
```

```php
<?php
namespace Custom;

use Pressmind\ORM\Object\MediaObject;

class IBETeamImport
{
    public static function afterImport($id_media_object, $id_object_type)
    {
        $mo = new MediaObject($id_media_object);
        foreach ($mo->getBookingPackages() as $bp) {
            foreach ($bp->dates as $date) {
                // modify transport data, create virtual prices, etc.
            }
        }
    }
}
```

**Key aspects:**
- Executes after the standard import for each MediaObject
- Common use cases: virtual booking packages, IBE data migration, external API enrichment

---

## Summary: Pattern Frequency

| # | Pattern | Frequency | Complexity |
|---|---|---|---|
| 1 | AJAX Search Endpoint | ~90% | Low |
| 2 | Query (SDK Search Pipeline) | 100% | Medium |
| 3 | Category Filter UI | ~85% | Medium |
| 4 | Teaser Card | 100% | Low |
| 5 | Price Box (Detail) | ~70% | Low |
| 6 | Price Table (Dates + Options) | ~60% | High |
| 7 | Product Calendar | ~30% | Medium |
| 8 | Booking Entrypoint (IBE) | ~65% | High |
| 9 | Transport Icons | ~50% | Low |
| 10 | Custom Index Filters | 100% | Medium |
| 11 | Wishlist | ~40% | Medium |
| 12 | Micro-Templates | 100% | Low |
| 13 | Custom Import Hooks | ~25% | High |
