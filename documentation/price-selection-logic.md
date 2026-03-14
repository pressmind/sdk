# Price Selection Logic

[← Back to Documentation](documentation.md)

---

## Table of Contents

- [Overview](#overview)
- [Part 1: Business Logic](#part-1-business-logic)
  - [Goal](#goal)
  - [Selection Priority](#selection-priority)
  - [Occupancy Fallback Chain](#occupancy-fallback-chain)
  - [State Priority](#state-priority)
  - [Examples](#examples)
  - [Where This Logic Applies](#where-this-logic-applies)
- [Part 2: Developer Reference](#part-2-developer-reference)
  - [Available Methods](#available-methods)
  - [CheapestPrice Filter Object](#cheapestprice-filter-object)
  - [Configuration Reference](#configuration-reference)
  - [MongoDB $reduce Pipeline](#mongodb-reduce-pipeline)
  - [MongoDB Indexer and best_price_meta](#mongodb-indexer-and-best_price_meta)
- [Relation to CheapestPrice Aggregation](#relation-to-cheapestprice-aggregation)

---

## Overview

This document describes **how the displayed “cheapest” price is chosen** when showing products in search results, detail pages, and calendar views. It is intended for both **project managers** (to understand the business rules) and **developers** (to see which methods and configuration options apply).

For how prices are **calculated and stored** during import, see [CheapestPrice Aggregation](cheapest-price-aggregation.md).

---

## Part 1: Business Logic

### Goal

**Always display the cheapest bookable double room price.**

If that is not available, the system falls back in a defined order (occupancy, then booking status, then price and duration).

### Selection Priority

The order in which we choose among multiple prices is:

1. **Occupancy** – Prefer double room (2 persons), then single room (1), then other room types (3, 4, …).
2. **State (booking status)** – Prefer bookable, then on request, then stop.
3. **Price** – Among equal occupancy and state, prefer the lowest total price.
4. **Duration** – Among equal occupancy, state and price, prefer the longest duration.

### Occupancy Fallback Chain

- **Double room (occupancy = 2)** is preferred.
- If no double room price exists → **Single room (occupancy = 1)** is used.
- If no single room either → **Any other room type** (e.g. 3-bed, 4-bed) is used.

This applies when no explicit occupancy filter is set (e.g. in standard search). When the user selects a specific occupancy in the search or CheapestPrice filter, only prices for that occupancy are considered (no automatic fallback, unless the implementation explicitly allows it).

### State Priority

- **Bookable** (state 3 in MySQL / 100 in MongoDB index) is preferred.
- If no bookable price exists → **On request** (state 1 / 200) is used.
- If neither exists → **Stop** (state 5 / 300) is used.

So a bookable double room at 4.899 € is preferred over an on-request double room at 3.200 €, and over a bookable single room at 3.399 € (occupancy wins over price).

### Examples

| Scenario | Result |
|----------|--------|
| Standard search, no occupancy filter | System tries DZ first, then EZ, then other; within each, best state then lowest price. |
| Search with explicit occupancy filter (e.g. “2 persons”) | Only prices for that occupancy are considered; state and price decide. |
| DZ bookable 4.899 € vs EZ bookable 3.399 € | **4.899 €** (DZ preferred over EZ). |
| DZ bookable 4.899 € vs DZ on-request 3.200 € | **4.899 €** (bookable preferred over on-request). |
| DZ on-request 3.200 € vs EZ bookable 3.399 € | **3.200 €** (DZ preferred over EZ even with worse state). |

### Where This Logic Applies

- **Search listings (MongoDB)** – The aggregation pipeline reduces the `prices` array to a single “best” price per product using the same priority (occupancy → state → price → duration).
- **MongoDB Indexer** – When building the search document, `_aggregatePrices()` fills the `prices` array; `_priceSort()` sorts it so that the first element is the “best” by the same priority. That first element is stored as `best_price_meta` (e.g. for filters or display).
- **Detail page / API (MySQL)** – `MediaObject::getCheapestPrice()` and related methods use occupancy fallback (DZ → EZ → all) and state fallback (3 → 1 → 5), then sort by state and price.
- **Calendar view** – When merging calendar data from multiple sources, the merge prefers the day with the better state; if the state is the same, the lower price wins.

---

## Part 2: Developer Reference

### Available Methods

| Method | Class | Purpose |
|--------|--------|--------|
| `getCheapestPrice($filters)` | `MediaObject` | Returns a single cheapest price (primary method). Applies occupancy and state fallback when using default filter. |
| `getCheapestPrices($filters, $order, $limit)` | `MediaObject` | Returns multiple prices with sorting and limit. Result is sorted by state priority then price/duration. |
| `getCheapestPricesOptions($origin, $agency)` | `MediaObject` | Returns available filter options (durations, transport types, occupancies) for the product. |
| `getCheapestPriceCount($id_media_object)` | `MediaObject` (static) | Returns price count per media object. |
| `getCheapestPrice()` | `Housing\Package` | Wrapper: delegates to `MediaObject::getCheapestPrice()` with `id_housing_package` set. |
| `getCheapestPrice()` | `Booking\Package` | Wrapper: delegates to `MediaObject::getCheapestPrice()` with `id_booking_package` set. |
| `getCalendar($filters, ...)` | `MediaObject` | Calendar view from MongoDB; merge uses state then price for the same day. |

### CheapestPrice Filter Object

Class: `Pressmind\Search\CheapestPrice`

Used to filter and control fallback behaviour when calling `getCheapestPrice()` / `getCheapestPrices()`.

| Property | Description |
|----------|-------------|
| `occupancies` | Array of occupancies to allow (e.g. `[2]`). When set, no automatic DZ→EZ→all fallback. |
| `occupancies_disable_fallback` | If `true`, disables the DZ→EZ→all fallback; one query without occupancy filter. |
| `state` | Default `STATE_BOOKABLE` (3). Only prices with this state are returned unless fallback is used. |
| `state_fallback_order` | Default `[3, 1, 5]`. If no price for current `state`, recursively try next state in this order. |
| `date_from`, `date_to` | Restrict to departure date range. |
| `price_from`, `price_to` | Restrict to price range. |
| `id_booking_package` | Restrict to a specific booking package (e.g. used by `Booking\Package::getCheapestPrice()`). |
| `id_housing_package` | Restrict to a specific housing package (e.g. used by `Housing\Package::getCheapestPrice()`). |
| `duration_from`, `duration_to` | Restrict to duration range. |
| `transport_types`, `transport_1_airport` | Restrict by transport. |
| `origin`, `agency` | Restrict by origin/agency. |

### Configuration Reference

These settings (e.g. in `pm-config.php` or `config.json`) affect which prices exist or are considered:

| Config path | Effect on price selection |
|-------------|---------------------------|
| `search.touristic.occupancies` | Occupancies for which prices are indexed in MongoDB (limits which prices are available in search). |
| `search.touristic.occupancy_additional` | Additional occupancy values included in the index. |
| `data.touristic.housing_option_filter.allowed_states` | Option states that are stored in `pmt2core_cheapest_price_speed`. |
| `data.touristic.date_filter.allowed_states` | Date states that produce prices during aggregation. |
| `data.touristic.generate_single_room_index` | When enabled, fills `diff_to_single_room` for displaying single-room supplement. |

### MongoDB $reduce Pipeline

The MongoDB search index stores a `prices` array per product. The aggregation pipeline uses a `$reduce` stage (with `$let` and `$switch` for occupancy rank) to reduce this array to a single “best” price per product. The comparison order is:

1. Occupancy rank (2 → 0, 1 → 1, other → 2; lower rank wins).
2. State (lower value wins; in the index: 100 bookable, 200 request, 300 stop).
3. `price_total` (lower wins).
4. `duration` (higher wins).

So the same business rule (occupancy → state → price → duration) is applied in the search result “best price” as in the MySQL-based methods.

### MongoDB Indexer and best_price_meta

When the MongoDB search index is built (`Pressmind\Search\MongoDB\Indexer`), prices are read from `pmt2core_cheapest_price_speed` and aggregated into a `prices` array per product. Before writing the document:

1. **`_aggregatePrices()`** – Builds the list of prices (with state mapped to 100 / 200 / 300 for the index).
2. **`_priceSort()`** – Sorts the array so that the **first** element is the “best” by the same priority: occupancy rank (DZ > EZ > other), then state (100 > 200 > 300), then `price_total` ascending, then `duration` descending.
3. **`best_price_meta`** – Set to `prices[0]`, so it matches the same “cheapest” as the aggregation `$reduce` and `getCheapestPrice()`.

Unit tests: `Pressmind\Tests\Unit\Search\MongoDB\IndexerTest::testPriceSortPutsBestFirst`, `testOccupancyRank`.

---

## Relation to CheapestPrice Aggregation

- **[CheapestPrice Aggregation](cheapest-price-aggregation.md)** describes how prices are **calculated and written** into `pmt2core_cheapest_price_speed` and the MongoDB index (import pipeline, state machine, early bird, etc.).
- **This document** describes how, given that data, we **select and display** one “cheapest” price (occupancy and state priority, fallbacks, and where this is used).

Both documents together cover the full path from touristic data to the price shown to the user.
