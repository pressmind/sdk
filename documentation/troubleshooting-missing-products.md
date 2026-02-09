# Troubleshooting: Product Not Appearing in Search

[← Back to MongoDB Search API](search-mongodb-api.md) | [→ CheapestPrice Aggregation](cheapest-price-aggregation.md) | [→ MongoDB Index Configuration](search-mongodb-index-configuration.md)

---

## Table of Contents

- [Overview](#overview)
- [Diagnostic Tools](#diagnostic-tools)
  - [Built-in Validator](#built-in-validator)
  - [MongoDB Indexer Log](#mongodb-indexer-log)
  - [CheapestPrice Log](#cheapestprice-log)
  - [Direct Database Queries](#direct-database-queries)
- [Checklist: Why Is My Product Not Showing?](#checklist-why-is-my-product-not-showing)
- [Case 1: Object Type Not in build_for](#case-1-object-type-not-in-build_for)
- [Case 2: Duration Ranges Don't Match](#case-2-duration-ranges-dont-match)
- [Case 3: No Prices in CheapestPriceSpeed](#case-3-no-prices-in-cheapestpricespeed)
- [Case 4: All Dates Are in the Past](#case-4-all-dates-are-in-the-past)
- [Case 5: Date State Filter Excludes Dates](#case-5-date-state-filter-excludes-dates)
- [Case 6: No Valid Housing Options for Season](#case-6-no-valid-housing-options-for-season)
- [Case 7: Housing Option State Filter](#case-7-housing-option-state-filter)
- [Case 8: Transport State Filter](#case-8-transport-state-filter)
- [Case 9: Zero Price on Primary Option](#case-9-zero-price-on-primary-option)
- [Case 10: Price Mix Without Matching Options](#case-10-price-mix-without-matching-options)
- [Case 11: Visibility Filter](#case-11-visibility-filter)
- [Case 12: Agency-Based Pricing Mismatch](#case-12-agency-based-pricing-mismatch)
- [Case 13: Expired Early Bird Discount](#case-13-expired-early-bird-discount)
- [Case 14: Occupancy Not Configured](#case-14-occupancy-not-configured)
- [Case 15: Origin Mismatch](#case-15-origin-mismatch)
- [Case 16: max_offers_per_product Reached](#case-16-max_offers_per_product-reached)
- [Case 17: Product Not Imported](#case-17-product-not-imported)
- [Case 18: Touristic Data Import Disabled](#case-18-touristic-data-import-disabled)
- [Case 19: MongoDB Collection Mismatch](#case-19-mongodb-collection-mismatch)
- [Case 20: Search Query Filters Too Restrictive](#case-20-search-query-filters-too-restrictive)
- [Case 21: Groups Configuration Missing](#case-21-groups-configuration-missing)
- [Case 22: Pretty URL / Route Missing](#case-22-pretty-url--route-missing)
- [Case 23: Required Group Options All Invalid](#case-23-required-group-options-all-invalid)
- [Case 24: allow_invalid_offers Not Set](#case-24-allow_invalid_offers-not-set)
- [Case 25: Booking Package Has No Dates](#case-25-booking-package-has-no-dates)
- [Quick Reference: Diagnostic SQL Queries](#quick-reference-diagnostic-sql-queries)

---

## Overview

When a product (Reise) doesn't appear in MongoDB search results, the cause lies in one of three areas:

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                  │
│  Area 1: IMPORT                                                  │
│  Product not imported or touristic data missing                  │
│  → Check: pmt2core_media_objects, import log                     │
│                                                                  │
│  Area 2: CHEAPEST PRICE                                          │
│  No valid price combinations generated                           │
│  → Check: pmt2core_cheapest_price_speed, config filters          │
│                                                                  │
│  Area 3: MONGODB INDEX                                           │
│  Product not indexed or filtered out during indexing              │
│  → Check: MongoDB collection, build_for config, duration_ranges  │
│                                                                  │
│  Area 4: SEARCH QUERY                                            │
│  Query parameters filter out the product                         │
│  → Check: pm-* parameters, visibility, price range, dates        │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

Work through the areas from top to bottom. If the product fails at an earlier stage, later stages will also fail.

---

## Diagnostic Tools

### Built-in Validator

The SDK has a built-in validation method that checks common issues:

```php
$indexer = new \Pressmind\Search\MongoDB\Indexer();
$messages = $indexer->validateMediaObject($id_media_object);

foreach ($messages as $msg) {
    echo $msg . "\n";
}
```

Output example:
```
Validation: MongoDB Indexer
 ✅  id_object_type 607 is configured in build_for
 Origin 0, no agency: ✅  Durations [7, 14 days] match configured ranges [1-3, 4-7, 8-99]
 ✅  Groups config OK (field: agencies)
```

Or with errors:
```
Validation: MongoDB Indexer
 ❌  id_object_type 607 is not configured in search.build_for. CONFIGURED: [609]
```

### MongoDB Indexer Log

Check the log category `mongodb_indexer` for detailed warning messages:

```
MongoDB Indexer: MediaObject 12345 NOT INDEXED - No prices after aggregation.
ACTUAL DURATIONS in DB: [5 days]. CONFIGURED duration_ranges: [1-3 days, 8-99 days].
FIX: Add a duration_range covering 5-5 days to search.touristic.duration_ranges
```

### CheapestPrice Log

The `insertCheapestPrice()` method writes detailed logs:

```php
$log = MediaObject::$_insert_cheapest_price_log[$id_media_object];
foreach ($log as $entry) {
    echo $entry . "\n";
}
```

Output:
```
Creating index for media_object: 12345
current booking_package id = 100, price_mix = date_housing
Skipping date 2025-06-15 because of date filter
Skipping date 2026-03-01 because of no valid options found for price_mix = date_housing
Option Info: extras count = 0, ticket count: 0, sightseeing: 0
```

### Direct Database Queries

```sql
-- Check if product exists in MySQL
SELECT id, id_object_type, visibility, name FROM pmt2core_media_objects WHERE id = 12345;

-- Check cheapest prices
SELECT COUNT(*) as cnt FROM pmt2core_cheapest_price_speed WHERE id_media_object = 12345;

-- Check prices with details
SELECT id_origin, duration, date_departure, price_total, state, option_occupancy, transport_type, agency
FROM pmt2core_cheapest_price_speed
WHERE id_media_object = 12345
ORDER BY price_total ASC
LIMIT 20;

-- Check booking packages
SELECT id, price_mix, duration, id_origin FROM pmt2core_touristic_booking_packages WHERE id_media_object = 12345;

-- Check dates
SELECT d.departure, d.arrival, d.state, d.season, d.agencies
FROM pmt2core_touristic_dates d
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345
ORDER BY d.departure;
```

---

## Checklist: Why Is My Product Not Showing?

Work through this checklist systematically:

| # | Check | Diagnostic |
|---|---|---|
| 1 | Product exists in MySQL? | `SELECT * FROM pmt2core_media_objects WHERE id = {id}` |
| 2 | Object type in `build_for`? | Compare `id_object_type` with config |
| 3 | Has booking packages? | `SELECT * FROM pmt2core_touristic_booking_packages WHERE id_media_object = {id}` |
| 4 | Touristic data import disabled? | Check `disable_touristic_data_import` config |
| 5 | Has future dates? | `SELECT * FROM pmt2core_touristic_dates ... WHERE departure > NOW()` |
| 6 | Date states allowed? | Check `date_filter.allowed_states` config |
| 7 | Has options for seasons? | Check option-to-date season matching |
| 8 | Option states allowed? | Check `housing_option_filter.allowed_states` config |
| 9 | Prices > 0? | Check option prices are not zero |
| 10 | CheapestPriceSpeed has entries? | `SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE id_media_object = {id}` |
| 11 | Duration ranges match? | Compare actual durations with `duration_ranges` config |
| 12 | Occupancy configured? | Check `touristic.occupancies` config |
| 13 | Origin matches? | Check `touristic.origins` and `build_for` origin |
| 14 | Visibility allowed? | Check `media_types_allowed_visibilities` config |
| 15 | `allow_invalid_offers`? | Products without prices need this setting |
| 16 | MongoDB document exists? | Check in MongoDB collection directly |
| 17 | Search query matches? | Check pm-* parameters (pm-ot, pm-pr, pm-dr, etc.) |

---

## Case 1: Object Type Not in build_for

**Symptom:** Product exists in MySQL but not in MongoDB.

**Cause:** The product's `id_object_type` is not configured in `search_mongodb.search.build_for`.

**Diagnostic:**
```sql
SELECT id_object_type FROM pmt2core_media_objects WHERE id = 12345;
-- Result: id_object_type = 607
```

```json
// Config: build_for only has 609
"build_for": {
    "609": [{"origin": 0, "language": "de"}]
}
// → 607 is missing!
```

**Fix:** Add the object type to `build_for`:
```json
"build_for": {
    "607": [{"origin": 0, "language": "de"}],
    "609": [{"origin": 0, "language": "de"}]
}
```

---

## Case 2: Duration Ranges Don't Match

**Symptom:** Product has prices in `pmt2core_cheapest_price_speed` but MongoDB document has no `prices` array (or document is not created).

**Cause:** The product's actual duration doesn't fall within any configured `duration_ranges`. This is the **most common cause** of missing products.

**Diagnostic:**
```sql
SELECT DISTINCT duration FROM pmt2core_cheapest_price_speed WHERE id_media_object = 12345;
-- Result: duration = 5
```

```json
// Config
"duration_ranges": [[1, 3], [8, 99]]
// → Duration 5 falls in NO range! Gap between 3 and 8.
```

**Fix:** Add a range covering the actual duration:
```json
"duration_ranges": [[1, 3], [4, 7], [8, 99]]
```

**Important:** The SDK logs this with an exact fix suggestion:
```
FIX: Add a duration_range covering 5-5 days to search.touristic.duration_ranges
```

---

## Case 3: No Prices in CheapestPriceSpeed

**Symptom:** `SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE id_media_object = 12345` returns `0`.

**Cause:** The `insertCheapestPrice()` method found no valid price combinations. This is a cascading issue – investigate Cases 4-10.

**Diagnostic:** Check the CheapestPrice log for skip messages.

---

## Case 4: All Dates Are in the Past

**Symptom:** Booking packages exist but all departure dates are before today.

**Diagnostic:**
```sql
SELECT departure, state FROM pmt2core_touristic_dates d
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345
ORDER BY departure DESC;
-- All dates < NOW()
```

**Fix:** This is a data issue in the PIM. The tour operator needs to add future departure dates.

**Note:** The `date_filter.offset` config shifts the start date:
```json
"date_filter": {
    "active": true,
    "offset": 14  // Skip dates within the next 14 days
}
```

A too-large offset can exclude dates that are technically in the future but too close.

---

## Case 5: Date State Filter Excludes Dates

**Symptom:** Future dates exist but are all in a state not allowed by the config.

**Diagnostic:**
```sql
SELECT departure, state FROM pmt2core_touristic_dates d
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345 AND departure > NOW();
-- All states = 3 (booking stop) or 5 (hidden)
```

```json
"date_filter": {
    "active": true,
    "allowed_states": [0, 1, 2]  // State 3 and 5 not allowed!
}
```

**Fix:** Either adjust `allowed_states` or check the date states in the PIM.

**State reference:**
| State | Meaning |
|---|---|
| 0 | No status |
| 1 | Bookable |
| 2 | Request |
| 3 | Sold out / Booking stop |
| 4 | Booking stop |
| 5 | Hidden |

---

## Case 6: No Valid Housing Options for Season

**Symptom:** Dates exist but `insertCheapestPrice()` logs "no valid options found for price_mix = date_housing".

**Cause:** Options have a `season` field that must match the date's `season`. If no option has a matching season, no price is generated.

**Diagnostic:**
```sql
-- Check date seasons
SELECT DISTINCT d.season FROM pmt2core_touristic_dates d
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345;
-- Result: 'S2026'

-- Check option seasons
SELECT DISTINCT o.season FROM pmt2core_touristic_options o
JOIN pmt2core_touristic_housing_packages hp ON o.id_housing_package = hp.id
JOIN pmt2core_touristic_booking_packages bp ON hp.id_booking_package = bp.id
WHERE bp.id_media_object = 12345;
-- Result: 'W2025'  ← Mismatch!
```

**Fix:** Data issue in the PIM. Options must have seasons matching the dates.

---

## Case 7: Housing Option State Filter

**Symptom:** Options exist for the season but their states are filtered out.

**Diagnostic:**
```sql
SELECT o.name, o.state, o.price, o.season FROM pmt2core_touristic_options o
JOIN pmt2core_touristic_housing_packages hp ON o.id_housing_package = hp.id
JOIN pmt2core_touristic_booking_packages bp ON hp.id_booking_package = bp.id
WHERE bp.id_media_object = 12345;
-- All states = 4 (booking stop)
```

```json
"housing_option_filter": {
    "active": true,
    "allowed_states": [0, 1, 2, 3]  // State 4 not included
}
```

**Fix:** Add state `4` to `allowed_states` or fix states in the PIM.

---

## Case 8: Transport State Filter

**Symptom:** Options exist but all transport pairs are filtered out.

**Diagnostic:**
```sql
SELECT t.way, t.type, t.state, t.price FROM pmt2core_touristic_transports t
JOIN pmt2core_touristic_dates d ON t.id_date = d.id
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345;
-- All transport states = 1 (not in default allowed_states [0, 2, 3])
```

```json
"transport_filter": {
    "active": true,
    "allowed_states": [0, 2, 3]  // State 1 not included
}
```

**Fix:** Adjust `transport_filter.allowed_states` or fix transport states in the PIM.

---

## Case 9: Zero Price on Primary Option

**Symptom:** Options exist but price is 0. Log shows "Skipping primary option ... because of zero price".

**Cause:** The aggregation explicitly rejects primary options with zero prices.

**Diagnostic:**
```sql
SELECT o.name, o.price, o.type FROM pmt2core_touristic_options o
JOIN pmt2core_touristic_housing_packages hp ON o.id_housing_package = hp.id
WHERE hp.id_booking_package = 100;
-- price = 0.00
```

**Fix:** Data issue in the PIM. Prices must be > 0 for primary options.

**Exception:** For `price_mix = date_transport`, a dummy option with price=0 is created internally. The transport price must then be > 0.

---

## Case 10: Price Mix Without Matching Options

**Symptom:** Booking package has `price_mix` set to a type that has no corresponding options.

**Example:**
```sql
SELECT price_mix FROM pmt2core_touristic_booking_packages WHERE id_media_object = 12345;
-- Result: 'date_sightseeing'

-- But no sightseeing options exist for this product
SELECT COUNT(*) FROM pmt2core_touristic_options WHERE type = 'sightseeing' AND ...;
-- Result: 0
```

**Fix:** Check the `price_mix` setting in the PIM. It must match the available option types.

---

## Case 11: Visibility Filter

**Symptom:** Product exists, prices exist, but MongoDB document is not created.

**Cause:** The product's `visibility` is not in `media_types_allowed_visibilities`.

**Diagnostic:**
```sql
SELECT visibility FROM pmt2core_media_objects WHERE id = 12345;
-- Result: 50 (hidden)
```

```json
"media_types_allowed_visibilities": {
    "607": [10, 30]  // 50 not included
}
```

**Note:** Products with `visibility = 10` are treated specially – they can bypass the `allow_invalid_offers` check and be indexed without prices.

**Fix:** Adjust visibility in the PIM or add the visibility value to the config.

---

## Case 12: Agency-Based Pricing Mismatch

**Symptom:** Product works for some agencies but not others.

**Cause:** Dates have an `agencies` field that restricts which agencies can see them.

**Diagnostic:**
```sql
SELECT departure, agencies FROM pmt2core_touristic_dates d
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345;
-- agencies = '10,20'  ← Agency 30 is excluded
```

```json
"agency_based_option_and_prices": {
    "enabled": true,
    "allowed_agencies": [10, 20, 30]  // 30 configured but excluded by dates
}
```

**Fix:** Check date agency assignments in the PIM, or check if all options and early birds also match the target agency.

---

## Case 13: Expired Early Bird Discount

**Symptom:** Product shows in search but with a higher price than expected. Or prices disappear entirely.

**Cause:** The MongoDB price aggregation filters out expired early bird discounts:
```sql
AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
```

If a product only had prices with an early bird discount and the booking window has passed, the price may drop out entirely (the base price row may still exist, depending on the data).

**Diagnostic:**
```sql
SELECT earlybird_discount, earlybird_discount_date_to, price_total
FROM pmt2core_cheapest_price_speed
WHERE id_media_object = 12345;
-- earlybird_discount_date_to < NOW() → these rows are excluded from MongoDB aggregation
```

**Fix:** This is expected behavior. Re-import to recalculate prices without the expired discount.

---

## Case 14: Occupancy Not Configured

**Symptom:** Prices exist in `pmt2core_cheapest_price_speed` but MongoDB aggregation produces no prices.

**Cause:** The product's options have an occupancy value not listed in `touristic.occupancies`.

**Diagnostic:**
```sql
SELECT DISTINCT option_occupancy FROM pmt2core_cheapest_price_speed WHERE id_media_object = 12345;
-- Result: 6
```

```json
"touristic": {
    "occupancies": [1, 2, 3, 4, 5]  // 6 not included!
}
```

**Fix:** Add the missing occupancy value:
```json
"occupancies": [1, 2, 3, 4, 5, 6]
```

---

## Case 15: Origin Mismatch

**Symptom:** Product has prices but for a different origin than configured in `build_for`.

**Diagnostic:**
```sql
SELECT DISTINCT id_origin FROM pmt2core_cheapest_price_speed WHERE id_media_object = 12345;
-- Result: 1

-- But build_for only has origin 0
```

```json
"build_for": {
    "607": [{"origin": 0, "language": "de"}]  // Origin 0, not 1
}
```

**Fix:** Match `build_for` origins with `touristic.origins`:
```json
"touristic": {
    "origins": [0, 1]
},
"build_for": {
    "607": [
        {"origin": 0, "language": "de"},
        {"origin": 1, "language": "de"}
    ]
}
```

---

## Case 16: max_offers_per_product Reached

**Symptom:** Product appears but with incomplete prices (some dates/options missing).

**Cause:** The `max_offers_per_product` limit was reached during aggregation. The CheapestPrice log shows:

```
Reached maximum number of rows (5000)
```

**Fix:** Increase the limit (with performance trade-offs):
```json
"touristic": {
    "max_offers_per_product": 10000
}
```

Or reduce combinations by not generating offers for every transport type/starting point/board type.

---

## Case 17: Product Not Imported

**Symptom:** Product doesn't exist in MySQL at all.

**Diagnostic:**
```sql
SELECT id FROM pmt2core_media_objects WHERE id = 12345;
-- Empty result
```

**Possible causes:**
- Object type not in `primary_media_type_ids` or `media_types`
- Visibility not in `media_types_allowed_visibilities` (import level)
- API error during import (check import log)
- Product deleted as orphan (`max_orphan_delete_ratio` too low)

**Fix:** Check import configuration and run import again.

---

## Case 18: Touristic Data Import Disabled

**Symptom:** Media object exists but has no booking packages.

**Diagnostic:**
```sql
SELECT COUNT(*) FROM pmt2core_touristic_booking_packages WHERE id_media_object = 12345;
-- Result: 0
```

```json
"touristic": {
    "disable_touristic_data_import": [607]  // Object type 607 skipped!
}
```

**Fix:** Remove the object type from `disable_touristic_data_import`.

---

## Case 19: MongoDB Collection Mismatch

**Symptom:** Product is indexed but search queries the wrong collection.

**Cause:** The search uses a collection name based on `language + origin + agency`. If the search is querying a different combination than what was indexed, the product won't be found.

**Collection naming:** `best_price_search_based_{language}_origin_{origin}`

**Diagnostic:**
```
Indexed in:  best_price_search_based_de_origin_0
Searched in: best_price_search_based_en_origin_0  ← wrong language!
```

**Fix:** Ensure the search language and origin match the `build_for` configuration.

---

## Case 20: Search Query Filters Too Restrictive

**Symptom:** Product is in MongoDB but doesn't appear in specific searches.

**Cause:** The `pm-*` query parameters filter out the product.

**Common restrictive filters:**
| Parameter | Example | Issue |
|---|---|---|
| `pm-ot` | `pm-ot=609` | Product is type 607, not 609 |
| `pm-pr` | `pm-pr=100-500` | Product's cheapest price is 600 |
| `pm-dr` | `pm-dr=20260601-20260630` | Product has no departures in June |
| `pm-du` | `pm-du=7-7` | Product is 14 days |
| `pm-o` | `pm-o=2` | Product has no occupancy=2 prices |
| `pm-tr` | `pm-tr=FLUG` | Product only has BUS transport |
| `pm-c[field]` | `pm-c[zielgebiet]=abc123` | Product doesn't have this category |
| `pm-gr` | `pm-gr=5` | Product not in group 5 |
| `pm-t` | `pm-t=Mallorca` | Fulltext doesn't match |

**Fix:** Remove filters one by one to identify which one excludes the product. Use `?debug=1` if available.

---

## Case 21: Groups Configuration Missing

**Symptom:** Product exists but when searching with `pm-gr`, it doesn't appear.

**Cause:** The `groups` field in the MongoDB document is empty or the configuration is wrong.

**Diagnostic:** Check the Indexer log:
```
MongoDB Indexer: MediaObject 12345 - brand property is NULL but groups.field = "brand"
```

**Fix:** Assign a brand/agency/pool or the configured custom field to the product in the PIM. Or adjust the `groups` config.

---

## Case 22: Pretty URL / Route Missing

**Symptom:** Product appears in search but the `url` field is empty or wrong.

**Cause:** The `media_types_pretty_url` config is missing or malformed for this object type.

**Diagnostic:**
```sql
SELECT route, language FROM pmt2core_routes WHERE id_media_object = 12345;
-- Empty result
```

**Fix:** Check the `media_types_pretty_url` configuration for the object type.

---

## Case 23: Required Group Options All Invalid

**Symptom:** CheapestPrice log shows dates being processed but no entries created.

**Cause:** A `required_group` of options (e.g. all transfer options) has no valid options (all states are invalid). This causes the entire date to be skipped.

**Diagnostic:**
```sql
SELECT o.name, o.state, o.required, o.required_group, o.type
FROM pmt2core_touristic_options o
WHERE o.id_housing_package IN (
    SELECT id FROM pmt2core_touristic_housing_packages WHERE id_booking_package = 100
)
AND o.required = 1;
-- All states = 4 or 5 in a required_group → entire date skipped
```

**Fix:** At least one option per required group must have a valid state (1, 2, or 3).

---

## Case 24: allow_invalid_offers Not Set

**Symptom:** Product without touristic data (e.g. content-only pages, static products) doesn't appear in search.

**Cause:** By default, products without prices are **not indexed** in MongoDB. The `allow_invalid_offers` setting must explicitly allow this.

**Fix:**
```json
"search_mongodb": {
    "search": {
        "allow_invalid_offers": [607, 610]
    }
}
```

Or globally (legacy):
```json
"allow_invalid_offers": true
```

**Note:** Products with `visibility = 10` bypass this check automatically.

---

## Case 25: Booking Package Has No Dates

**Symptom:** Booking packages exist but have no dates assigned.

**Diagnostic:**
```sql
SELECT bp.id, bp.price_mix, bp.duration,
       (SELECT COUNT(*) FROM pmt2core_touristic_dates WHERE id_booking_package = bp.id) as date_count
FROM pmt2core_touristic_booking_packages bp
WHERE bp.id_media_object = 12345;
-- date_count = 0
```

**Fix:** Data issue in the PIM. Booking packages must have at least one date assigned.

---

## Quick Reference: Diagnostic SQL Queries

```sql
-- 1. Complete product check
SELECT mo.id, mo.id_object_type, mo.visibility, mo.name,
    (SELECT COUNT(*) FROM pmt2core_touristic_booking_packages WHERE id_media_object = mo.id) AS booking_packages,
    (SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE id_media_object = mo.id) AS price_entries,
    (SELECT MIN(price_total) FROM pmt2core_cheapest_price_speed WHERE id_media_object = mo.id) AS min_price,
    (SELECT MAX(price_total) FROM pmt2core_cheapest_price_speed WHERE id_media_object = mo.id) AS max_price
FROM pmt2core_media_objects mo
WHERE mo.id = 12345;

-- 2. Duration analysis
SELECT DISTINCT duration 
FROM pmt2core_cheapest_price_speed 
WHERE id_media_object = 12345
ORDER BY duration;

-- 3. Date analysis with state
SELECT d.departure, d.arrival, d.state, d.season, d.agencies, d.id_early_bird_discount_group
FROM pmt2core_touristic_dates d
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345
  AND d.departure > NOW()
ORDER BY d.departure;

-- 4. Option analysis per season
SELECT o.name, o.price, o.state, o.occupancy, o.season, o.type, o.board_type, o.required_group
FROM pmt2core_touristic_options o
JOIN pmt2core_touristic_housing_packages hp ON o.id_housing_package = hp.id
JOIN pmt2core_touristic_booking_packages bp ON hp.id_booking_package = bp.id
WHERE bp.id_media_object = 12345
ORDER BY o.season, o.type, o.price;

-- 5. Transport analysis
SELECT t.way, t.type, t.state, t.price, t.code, t.description
FROM pmt2core_touristic_transports t
JOIN pmt2core_touristic_dates d ON t.id_date = d.id
JOIN pmt2core_touristic_booking_packages bp ON d.id_booking_package = bp.id
WHERE bp.id_media_object = 12345
  AND d.departure > NOW()
LIMIT 20;

-- 6. Price per origin/agency
SELECT id_origin, agency, COUNT(*) as cnt, MIN(price_total) as min_price
FROM pmt2core_cheapest_price_speed
WHERE id_media_object = 12345
GROUP BY id_origin, agency;

-- 7. Expired early birds
SELECT earlybird_discount, earlybird_discount_date_to, COUNT(*) as cnt
FROM pmt2core_cheapest_price_speed
WHERE id_media_object = 12345
  AND earlybird_discount > 0
GROUP BY earlybird_discount, earlybird_discount_date_to;
```
