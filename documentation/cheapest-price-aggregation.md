# CheapestPrice Aggregation

[← Back to Import Process](import-process.md) | [→ Architecture](architecture.md)

---

## Table of Contents

- [Overview](#overview)
- [Entity Model](#entity-model)
  - [Touristic Booking Package](#touristic-booking-package)
  - [Touristic Date](#touristic-date)
  - [Housing Package](#housing-package)
  - [Option (Room / Board)](#option-room--board)
  - [Transport (Flight, Bus, etc.)](#transport-flight-bus-etc)
  - [Starting Point Option](#starting-point-option)
  - [Early Bird Discount Group](#early-bird-discount-group)
  - [Manual Discount](#manual-discount)
  - [CheapestPriceSpeed (Output)](#cheapestpricespeed-output)
- [Entity Relationships](#entity-relationships)
- [Price Mix Modes](#price-mix-modes)
- [The Aggregation Algorithm](#the-aggregation-algorithm)
  - [Step 1: Configuration Loading](#step-1-configuration-loading)
  - [Step 2: Date Filtering](#step-2-date-filtering)
  - [Step 3: Option Resolution (Primary)](#step-3-option-resolution-primary)
  - [Step 4: Included Options (Secondary)](#step-4-included-options-secondary)
  - [Step 5: Transport Pairing](#step-5-transport-pairing)
  - [Step 6: Starting Point Pricing](#step-6-starting-point-pricing)
  - [Step 7: Price Calculation](#step-7-price-calculation)
  - [Step 8: Early Bird Discount](#step-8-early-bird-discount)
  - [Step 9: State Determination](#step-9-state-determination)
  - [Step 10: Fingerprint & Storage](#step-10-fingerprint--storage)
  - [Step 11: Single Room Index](#step-11-single-room-index)
- [Price Calculation Formula](#price-calculation-formula)
  - [Base Price](#base-price)
  - [Early Bird Base Price](#early-bird-base-price)
  - [Final Price](#final-price)
- [Early Bird Discount System](#early-bird-discount-system)
  - [Discount Types](#discount-types)
  - [Validity Check](#validity-check)
  - [Booking Days Before Departure](#booking-days-before-departure)
  - [Room Condition Filter](#room-condition-filter)
  - [Manual Discount Conversion](#manual-discount-conversion)
- [State Machine](#state-machine)
- [Option Price Due Modes](#option-price-due-modes)
  - [Housing Option](#housing-option-type--housing_option)
  - [Extras, Tickets, Sightseeing](#extras-tickets-sightseeing-type--ticket--sightseeing--extra)
  - [Conversion Logic](#conversion-logic-optioncalculateprice)
  - [Price Due Flow in Aggregation](#price-due-flow-in-cheapestprice-aggregation)
  - [Validator Reference](#validator-reference)
- [Agency-Based Pricing](#agency-based-pricing)
- [Quota Tracking](#quota-tracking)
- [Virtual Prices](#virtual-prices)
- [Single Room Index](#step-11-single-room-index)
- [Performance Optimizations](#performance-optimizations)
- [Configuration Reference](#configuration-reference)

---

## Overview

The CheapestPrice aggregation is the core pricing engine of the SDK. It pre-calculates all possible price combinations from the touristic data structure and stores them in a flat, highly optimized table (`pmt2core_cheapest_price_speed`) that serves as the basis for:

- **Search results** – Price display in listings
- **MongoDB index** – Price aggregation for the search engine
- **Filter ranges** – Min/max price sliders, date ranges
- **IBE deep links** – Direct booking links with all required parameters

The aggregation runs during every import and combines:

```
BookingPackage × Date × Option × TransportPair × StartingPoint × EarlyBird
= CheapestPriceSpeed entries
```

For a typical product with 2 booking packages, 50 dates, 5 housing options, 3 transport pairs, and 2 early bird discounts, this produces:

```
2 × 50 × 5 × 3 × 1 × 2 = 3,000 CheapestPriceSpeed entries
```

---

## Entity Model

### Touristic Booking Package

**Table:** `pmt2core_touristic_booking_packages`
**Class:** `Pressmind\ORM\Object\Touristic\Booking\Package`

The top-level container for all touristic data of a product. A media object can have multiple booking packages (e.g. one for flights, one for bus trips).

| Property | Type | Description |
|---|---|---|
| `id` | Integer | Primary key |
| `id_media_object` | Integer | Reference to media object |
| `price_mix` | String | Pricing mode (see [Price Mix Modes](#price-mix-modes)) |
| `duration` | Integer | Trip duration in days |
| `id_origin` | Integer | Origin/market ID |
| `ibe_type` | Integer | IBE booking type |
| `product_type_ibe` | String | Product type for booking engine |
| `type_of_travel` | String | Travel type |
| `variant_code` | String | Variant identifier |
| `name` | String | Package name |
| `code` | String | Package code |
| `price_group` | String | Price group |
| `product_group` | String | Product group |
| `is_virtual_created_price` | Boolean | Whether created from virtual price calculation |

**Relations:**
- `dates[]` – `Touristic\Date`
- `housing_packages[]` – `Touristic\Housing\Package`
- `sightseeings[]`, `tickets[]`, `extras[]` – secondary option types

### Touristic Date

**Table:** `pmt2core_touristic_dates`
**Class:** `Pressmind\ORM\Object\Touristic\Date`

Represents a departure/arrival date combination with its availability state.

| Property | Type | Description |
|---|---|---|
| `departure` | DateTime | Departure date |
| `arrival` | DateTime | Arrival date |
| `state` | Integer | Availability state (see [State Machine](#state-machine)) |
| `season` | String | Season code (links options to this date) |
| `guaranteed` | Boolean | Departure guaranteed |
| `saved` | Boolean | Date marked as saved |
| `agencies` | String | Comma-separated agency IDs allowed for this date |
| `id_early_bird_discount_group` | Integer | Reference to early bird discounts |
| `code_ibe` | String | IBE code for this date |
| `text` | String | Info text for this date |

**Key methods:**
- `getHousingOptions()` – Returns options matching this date's season
- `getTransportPairs()` – Builds outbound/return transport combinations
- `getEarlybirds()` – Returns applicable early bird discounts
- `getAllOptionsButExcludePriceMixOptions()` – Returns cheapest secondary options

### Housing Package

**Table:** `pmt2core_touristic_housing_packages`
**Class:** `Pressmind\ORM\Object\Touristic\Housing\Package`

Groups related room/accommodation options together.

| Property | Type | Description |
|---|---|---|
| `name` | String | Housing package name (e.g. "Hotel Mallorca Palace") |
| `code` | String | Housing package code |
| `code_ibe` | String | IBE code |
| `nights` | Integer | Number of nights in this accommodation |

**Relations:**
- `options[]` – `Touristic\Option` (rooms/boards within this housing)

### Option (Room / Board)

**Table:** `pmt2core_touristic_options`
**Class:** `Pressmind\ORM\Object\Touristic\Option`

The actual priced element – a room type, board type, extra, ticket, or sightseeing.

| Property | Type | Description |
|---|---|---|
| `price` | Float | Price per unit |
| `price_pseudo` | Float | Crossed-out "was" price |
| `price_due` | String | Price period (see [Option Price Due Modes](#option-price-due-modes)) |
| `occupancy` | Integer | Standard occupancy (e.g. 2 for double room) |
| `occupancy_min` | Integer | Minimum occupancy |
| `occupancy_max` | Integer | Maximum occupancy |
| `occupancy_child` | Integer | Child occupancy |
| `type` | String | `housing`, `sightseeing`, `extra`, `ticket` |
| `board_type` | String | Board type (e.g. "Halbpension") |
| `board_code` | String | Board code (e.g. "HP") |
| `state` | Integer | Availability state |
| `use_earlybird` | Boolean | Whether early bird discounts apply to this option |
| `required` | Boolean | Whether this option is mandatory |
| `required_group` | String | Group name for required option sets |
| `code_ibe` | String | IBE code |
| `season` | String | Season code (for matching with dates) |
| `quota` | Integer | Available units |

**Key method:**

```php
public function calculatePrice($duration_days, $nights)
```

Converts periodic prices to one-time amounts – but **only for option types `ticket`, `sightseeing`, `extra`**. Housing option prices are never converted. See [Option Price Due Modes](#option-price-due-modes) for details.

| `price_due` | Applies To | Calculation | Example |
|---|---|---|---|
| `person_stay` | Housing | Passed through (per person, per stay) | 900 € |
| `stay` | Housing | Passed through (per stay, total) | 1800 € |
| `nights_person` | Housing | Passed through (per night, per person) | 80 €/night |
| `once` | Extras | `price` (no change) | 120 € |
| `once_stay` | Extras | `price` (no change) | 150 € |
| `nightly` | Extras | `price × nights` → converted to `once` | 80 € × 7 = 560 € |
| `daily` | Extras | `price × duration_days` → converted to `once` | 50 € × 8 = 400 € |
| `weekly` | Extras | `price × ceil(days / 7)` → converted to `once` | 500 € × 2 = 1000 € |

### Transport (Flight, Bus, etc.)

**Table:** `pmt2core_touristic_transports`
**Class:** `Pressmind\ORM\Object\Touristic\Transport`

A single transport leg (outbound or return).

| Property | Type | Description |
|---|---|---|
| `way` | Integer | `1` = outbound, `2` = return |
| `type` | String | `BUS`, `FLUG`, `SCHIFF`, `BAHN`, `PKW` |
| `price` | Float | Price for this transport leg |
| `state` | Integer | Availability state |
| `use_earlybird` | Boolean | Whether early bird discounts apply |
| `transport_group` | Integer | Group ID for pairing way1/way2 |
| `code` | String | Transport code (e.g. airport IATA codes) |
| `code_ibe` | String | IBE code |
| `description` | String | Description |
| `airline` | String | Airline name (for flights) |
| `flight` | String | Flight number |
| `id_starting_point` | Integer | Reference to starting point |
| `transport_date_from` | DateTime | Transport departure date |
| `transport_date_to` | DateTime | Transport arrival date |
| `quota` | Integer | Available seats |

**Transport Pairing:**
Transports come in pairs (outbound + return). The `getTransportPairs()` method matches `way=1` with `way=2` transports using `transport_group`. For flights, it creates airport pair combinations.

### Starting Point Option

**Table:** `pmt2core_touristic_startingpoint_options`
**Class:** `Pressmind\ORM\Object\Touristic\Startingpoint\Option`

Departure point with optional surcharge (e.g. departure city for bus trips).

| Property | Type | Description |
|---|---|---|
| `price` | Float | Surcharge for this starting point |
| `price_per_day` | Boolean | If true, price × duration |
| `name` | String | Starting point name |
| `city` | String | City name |
| `zip` | String | ZIP code |
| `code_ibe` | String | IBE code |
| `use_earlybird` | Boolean | Whether early bird discounts apply |

### Early Bird Discount Group

**Table:** `pmt2core_touristic_earlybird_discount_groups`
**Class:** `Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup`

Container for early bird discount rules, linked to dates.

**Items** (`EarlyBirdDiscountGroup\Item`):

| Property | Type | Description |
|---|---|---|
| `type` | String | `P` = percentage, `F` = fixed amount |
| `discount_value` | Float | Discount value (percentage or absolute) |
| `travel_date_from` | DateTime | Earliest travel date |
| `travel_date_to` | DateTime | Latest travel date |
| `booking_date_from` | DateTime | Booking window start |
| `booking_date_to` | DateTime | Booking window end |
| `booking_days_before_departure` | Integer | Alternative: days before departure |
| `room_condition_code_ibe` | String | Optional: only for specific room type |
| `agency` | String | Optional: only for specific agency |
| `origin` | String | `manual_discount` if converted from ManualDiscount |
| `name` | String | Discount name |

### Manual Discount

**Table:** `pmt2core_media_object_manual_discounts`
**Class:** `Pressmind\ORM\Object\MediaObject\ManualDiscount`

Editor-defined discounts from the PIM that are converted to early bird format during import.

| Property | Type | Description |
|---|---|---|
| `type` | String | `fixed_price` or `percent` |
| `value` | Float | Discount value |
| `travel_date_from` / `to` | DateTime | Travel date range |
| `booking_date_from` / `to` | DateTime | Booking date range |

### CheapestPriceSpeed (Output)

**Table:** `pmt2core_cheapest_price_speed`
**Class:** `Pressmind\ORM\Object\CheapestPriceSpeed`

The denormalized output table containing one row per price combination.

| Column Group | Columns | Description |
|---|---|---|
| **References** | `id_media_object`, `id_booking_package`, `id_housing_package`, `id_date`, `id_option`, `id_transport_1`, `id_transport_2`, `id_startingpoint`, `id_startingpoint_option`, `id_origin` | Links back to source entities |
| **Price** | `price_total`, `price_option`, `price_option_pseudo`, `price_transport_total`, `price_transport_1`, `price_transport_2`, `price_regular_before_discount`, `included_options_price` | All price components |
| **Early Bird** | `earlybird_discount` (%), `earlybird_discount_f` (fixed), `earlybird_discount_date_to`, `earlybird_name` | Discount details |
| **Date** | `date_departure`, `date_arrival`, `duration` | Travel dates |
| **Option** | `option_name`, `option_code`, `option_board_type`, `option_board_code`, `option_occupancy`, `option_occupancy_min`, `option_occupancy_max`, `option_occupancy_child`, `option_price_due`, `option_description_long` | Room/option details |
| **Transport** | `transport_type`, `transport_code`, `transport_1_description`, `transport_2_description`, `transport_1_airline`, `transport_2_airline`, `transport_1_airport`, `transport_2_airport`, `transport_1_airport_name`, `transport_2_airport_name`, `transport_1_flight`, `transport_2_flight` | Transport details |
| **Starting Point** | `startingpoint_name`, `startingpoint_city`, `startingpoint_id_city`, `startingpoint_zip`, `startingpoint_code_ibe` | Departure point details |
| **IBE Codes** | `date_code_ibe`, `housing_package_code_ibe`, `option_code_ibe`, `option_code_ibe_board_type`, `option_code_ibe_category`, `transport_1_code_ibe`, `transport_2_code_ibe`, `booking_package_ibe_type` | Booking engine codes |
| **Booking Package** | `booking_package_name`, `booking_package_code`, `booking_package_price_group`, `booking_package_product_group`, `booking_package_product_type_ibe`, `booking_package_type_of_travel`, `booking_package_variant_code` | Package metadata |
| **State** | `state`, `guaranteed`, `saved`, `quota_pax` | Availability info |
| **Metadata** | `price_mix`, `agency`, `fingerprint`, `is_virtual_created_price`, `diff_to_single_room`, `infotext`, `included_options_description`, `id_included_options`, `code_ibe_included_options` | Additional metadata |

---

## Entity Relationships

```
MediaObject (1)
  └── BookingPackage (n)        ← price_mix, duration
       ├── Date (n)              ← departure, arrival, state, season
       │    ├── Transport (n)    ← way1/way2 pairs, type, price
       │    │    └── StartingPoint → StartingPointOption (n) ← city, surcharge
       │    ├── EarlyBirdDiscountGroup (0..1)
       │    │    └── Item (n)    ← type, discount_value, date ranges
       │    └── (season match) → Options via HousingPackage
       │
       └── HousingPackage (n)    ← name, nights
            └── Option (n)       ← price, occupancy, board_type, state
                                   type: housing | sightseeing | extra | ticket
```

**Season Matching:**
Options are connected to dates through a **season code**. Each date has a `season` property, and each option has a `season` property. Only options whose season matches the date's season are considered for pricing.

---

## Price Mix Modes

The `price_mix` field on the BookingPackage determines which entity is the **primary priced element**:

| `price_mix` | Primary Element | Description | Typical Use |
|---|---|---|---|
| `date_housing` | Housing Option | Room/accommodation is the main price | Package tours, hotel bookings |
| `date_transport` | Transport | Transport is the main price (option price = 0) | Ferry crossings, flight-only |
| `date_extra` | Extra | An extra service is the main price | Additional services |
| `date_ticket` | Ticket | A ticket is the main price | Event tickets, entrance fees |
| `date_sightseeing` | Sightseeing | A sightseeing tour is the main price | Excursions, day trips |
| `date_startingpoint` | Starting Point | Starting point is the main price | Transfer services |

For `date_housing`, the iteration is:
```
For each Date → For each HousingOption (from season match) → Add transport + extras
```

For `date_transport`, a dummy option with `price=0` is created, and transport becomes the main priced element.

---

## The Aggregation Algorithm

**Method:** `MediaObject::insertCheapestPrice()`

### Step 1: Configuration Loading

```php
$max_rows           // max entries per product (default: 5000)
$ibe_client         // IBE client identifier
$travel_date_offset // days from now to start (date_filter.offset)
$max_date_offset    // max days into the future (default: 730 = ~2 years)
$travel_date_allowed_states  // which date states to include
$housing_option_allowed_states // which option states to include
$transport_allowed_states      // which transport states to include
```

### Step 2: Date Filtering

Each date is checked against:

1. **Date range:** `departure >= now + offset` AND `departure <= now + max_date_offset`
2. **State filter:** `date.state in allowed_states`
3. **Agency filter:** If agency-based pricing, date must be allowed for current agency

```
Date: 2026-03-15, state=1 (request), agencies="10,20"
Config: offset=0, max_offset=730, allowed_states=[0,1,2,4,5]
Agency: 10
→ INCLUDED (date is in range, state allowed, agency matches)
```

### Step 3: Option Resolution (Primary)

Based on `price_mix`, the primary options are loaded:

| `price_mix` | Method | Returns |
|---|---|---|
| `date_housing` | `$date->getHousingOptions($state_filter, true, $agency)` | Housing options matching season + state |
| `date_sightseeing` | `$date->getSightseeings(true, $agency)` | Sightseeing options |
| `date_extra` | `$date->getExtras(true, $agency)` | Extra options |
| `date_ticket` | `$date->getTickets(true, $agency)` | Ticket options |
| `date_transport` | Creates dummy option with price=0 | Empty option (transport is the price) |

### Step 4: Included Options (Secondary)

For each primary option, the **cheapest required secondary options** are calculated:

```php
$option_list = $date->getAllOptionsButExcludePriceMixOptions($price_mix, true, $agency);
```

This returns the cheapest option per `required_group` (excluding the primary price_mix type). Only options with valid states (`1`, `2`, `3`) are considered.

The included options' prices are summed:

```php
$included_options_price = sum(cheapest option per required_group)
```

For periodic prices (`nightly`, `daily`, `weekly`), `calculatePrice()` converts them to a one-time amount.

### Step 5: Transport Pairing

```php
$transport_pairs = $date->getTransportPairs($state_filter, [], [], null, true, $agency);
```

Transport pairing logic:
1. Separate transports into `way=1` (outbound) and `way=2` (return)
2. Match by `transport_group` ID
3. For flights: create all valid airport pair combinations
4. Filter by state (`transport_allowed_states`)
5. If no transports exist: use `[null]` (product without transport)

### Step 6: Starting Point Pricing

If the transport has a `id_starting_point`, starting point options are loaded:

- If `generate_offer_for_each_startingpoint_option = true`: **all** starting point options create separate entries
- Otherwise: only the **cheapest** starting point option is used

```php
$starting_point_price = $StartingPointOption->price_per_day 
    ? $StartingPointOption->price × $duration 
    : $StartingPointOption->price;
```

### Step 7: Price Calculation

See [Price Calculation Formula](#price-calculation-formula) below.

### Step 8: Early Bird Discount

See [Early Bird Discount System](#early-bird-discount-system) below.

### Step 9: State Determination

See [State Machine](#state-machine) below.

### Step 10: Fingerprint & Storage

A SHA256 fingerprint is created to detect duplicates:

```php
// For ibe_type >= 2 (code-based):
hash('sha256', join('_', [
    id_media_object, date_departure, date_arrival, date_code_ibe,
    housing_package_code_ibe, option_code_ibe, option_code_ibe_board_type,
    option_code_ibe_board_type_category, option_code_ibe_category,
    transport_1_code_ibe, transport_2_code_ibe, startingpoint_code_ibe,
    code_ibe_included_options, agency
]))

// For ibe_type < 2 (id-based):
hash('sha256', join('_', [
    id, id_media_object, id_booking_package, id_housing_package,
    id_date, id_option, id_transport_1, id_transport_2,
    id_option_auto_book, id_option_required_group, id_startingpoint_option,
    id_origin, id_startingpoint, id_included_options,
    startingpoint_id_city, housing_package_id_name, agency
]))
```

Entries are batch-inserted in chunks of 500 for performance.

### Step 11: Single Room Index

If `generate_single_room_index` is enabled, the `diff_to_single_room` field is calculated:

```sql
-- For each occupancy=2 entry, find the matching occupancy=1 entry
SELECT (single_room_price - double_room_price) AS diff_to_single_room
FROM pmt2core_cheapest_price_speed
WHERE option_occupancy = 1
  AND date_departure = [same]
  AND duration = [same]
  AND option_board_type = [same]
  AND transport_type = [same]
  AND id_housing_package = [same]
  AND id_booking_package = [same]
ORDER BY price_total ASC
LIMIT 1
```

This allows displaying the single room supplement: "ab 899 € (EZ-Zuschlag: +150 €)".

---

## Price Calculation Formula

### Base Price

```
price_total = option.price 
            + transport_way1.price + transport_way2.price 
            + starting_point.price 
            + included_options_price
```

Where `included_options_price` is the sum of the cheapest option per required group (secondary options like extras, tickets, sightseeings that are mandatory).

**Example:**

```
Housing Option "DZ Meerblick":     890.00 €   (price_mix primary)
Transport Outbound (Flight FRA→PMI):  189.00 €
Transport Return (Flight PMI→FRA):    189.00 €
Starting Point (Frankfurt):           0.00 €
Included Extra "Reiseschutz":         29.00 €   (cheapest in required_group)
────────────────────────────────────────────────
price_total:                        1,297.00 €
```

### Early Bird Base Price

Not all price components participate in early bird discounts. Only components with `use_earlybird = true` contribute:

```
earlybird_base = (option.use_earlybird ? option.price : 0)
               + (transport_way1.use_earlybird ? transport_way1.price : 0)
               + (transport_way2.use_earlybird ? transport_way2.price : 0)
               + (starting_point.use_earlybird ? starting_point.price : 0)
               + (included_options with use_earlybird ? their prices : 0)
```

**Example:**

```
Housing Option (use_earlybird=true):    890.00 €
Transport Way1 (use_earlybird=false):     0.00 €  ← excluded
Transport Way2 (use_earlybird=false):     0.00 €  ← excluded
Extra (use_earlybird=true):              29.00 €
────────────────────────────────────────────────
earlybird_base:                         919.00 €
```

### Final Price

```
If early bird discount applies:
    discount = -1 × (earlybird_base / 100 × discount_value)     // percentage
    discount = -1 × discount_value                                // fixed

    price_total = price_regular_before_discount + discount
Else:
    price_total = price_regular_before_discount
```

**Example (10% early bird):**

```
price_regular_before_discount:  1,297.00 €
earlybird_base:                   919.00 €
discount (10%):                   -91.90 €
────────────────────────────────────────────
price_total:                    1,205.10 €
```

---

## Early Bird Discount System

### Discount Types

| Type | Field | Calculation |
|---|---|---|
| `P` (Percentage) | `earlybird_discount` | `-(earlybird_base / 100 × value)` |
| `F` (Fixed) | `earlybird_discount_f` | `-value` |

### Validity Check

An early bird discount is applied only if **all** conditions are met:

```php
function _checkEarlyBirdDiscount($discount, $date, $housing_code_ibe):

1. room_condition_code_ibe matches (or is empty)    // optional room filter
2. now >= booking_date_from (or booking_date_from is null)
3. now <= booking_date_to   (or booking_date_to is null)
4. date.departure >= travel_date_from (or travel_date_from is null)
5. date.departure <= travel_date_to   (or travel_date_to is null)
```

**Example:**

```
Discount: "10% Frühbucher"
  booking_date_from: 2025-12-01
  booking_date_to:   2026-03-31
  travel_date_from:  2026-05-01
  travel_date_to:    2026-10-31

Today:     2026-02-08  → ✓ within booking window
Departure: 2026-07-15  → ✓ within travel date range
→ DISCOUNT APPLIES
```

### Booking Days Before Departure

An alternative to fixed booking dates. If `booking_days_before_departure` is set (and `booking_date_from`/`to` are null):

```
booking_date_from = departure - booking_days_before_departure days
booking_date_to   = departure
```

**Example:**

```
Discount: booking_days_before_departure = 60
Departure: 2026-07-15

Effective booking window:
  from: 2026-05-16  (July 15 - 60 days)
  to:   2026-07-15  (departure day)
```

### Room Condition Filter

The `room_condition_code_ibe` field allows discounts to apply only to specific room types:

```
Discount: room_condition_code_ibe = "DZ-MB"
Option code_ibe: "DZ-MB"  → ✓ matches → discount applies
Option code_ibe: "EZ"     → ✗ no match → discount skipped
```

### Manual Discount Conversion

Manual discounts from the PIM editor are converted to early bird format during import:

```php
ManualDiscount::convertManualDiscountsToEarlyBird($id_media_object)
```

Mapping:
- `fixed_price` → Type `F`
- `percent` → Type `P`
- Origin is tagged as `manual_discount`

This allows the same discount engine to handle both system-generated and editor-defined discounts.

---

## State Machine

**Important:** The numeric state values have **different meanings** depending on the entity type. This is a known inconsistency in the pressmind PIM data model. The same number (e.g. `0`) can mean "sold out" for an option but "no status" for a date or transport. Additionally, housing options and extras/tickets/sightseeing use different labels for some states (e.g. state `3` is "Aktiv" for housing but "Buchbar" for extras).

### State Values Per Entity Type

**Touristic Date** (`pmt2core_touristic_dates.state`):

| State | Name (German) | Meaning | Default Filter |
|---|---|---|---|
| `0` | Kein Status | No status – treated as bookable | ✅ Included |
| `1` | Buchbar | Bookable | ✅ Included |
| `2` | Anfrage | Available on request | ✅ Included |
| `3` | Gesperrt | **Blocked / locked** | ❌ Excluded |
| `4` | Wenig | Low availability / few seats left | ✅ Included |
| `5` | Ausgebucht | **Sold out** | ✅ Included |

Config: `data.touristic.date_filter.allowed_states` (default: `[0, 1, 2, 4, 5]`)

**Housing Option** (`pmt2core_touristic_options.state`, `type = 'housing_option'`):

| State | Name (German) | Meaning | Default Filter |
|---|---|---|---|
| `0` | Ausgebucht | **Sold out** (no availability) | ✅ Included |
| `1` | Anfrage | Available on request | ✅ Included |
| `2` | Wenig | Low availability / few left | ✅ Included |
| `3` | Aktiv | Active / bookable | ✅ Included |
| `4` | Buchungsstopp | Booking stop | ❌ Excluded |
| `5` | Ausblenden | Hidden – not shown to customers | ❌ Excluded |
| `6` | Kontingentverfall | Quota expired – contingent no longer available | ❌ Excluded |

Config: `data.touristic.housing_option_filter.allowed_states` (default: `[0, 1, 2, 3]`)

**Ticket / Extra / Sightseeing** (`pmt2core_touristic_options.state`, `type = 'ticket' | 'extra' | 'sightseeing'`):

| State | Name (German) | Meaning |
|---|---|---|
| `0` | Ausgebucht | **Sold out** (no availability) |
| `1` | Anfrage | Available on request |
| `2` | Wenig | Low availability / few left |
| `3` | Buchbar | Bookable |
| `4` | Buchungsstopp | Booking stop |
| `5` | Ausblenden | Hidden – not shown to customers |

> **Note:** Tickets, extras and sightseeing options share the same `state` column but have a different label for state `3`: **"Buchbar"** instead of **"Aktiv"** (housing). State `6` (Kontingentverfall) does not exist for these types. These options are **not** filtered by `housing_option_filter` – they are loaded separately via `getOptions()` without a state filter, but only states `[1, 2, 3]` are considered when selecting the cheapest required option (see state determination logic below).

**Touristic Transport** (`pmt2core_touristic_transports.state`):

| State | Name (German) | Meaning | Default Filter |
|---|---|---|---|
| `0` | Kein Status | No status – treated as available | ✅ Included |
| `1` | Gesperrt | **Blocked / locked** | ❌ Excluded |
| `2` | Anfrage | Available on request | ✅ Included |
| `3` | Buchbar | Bookable | ✅ Included |

Config: `data.touristic.transport_filter.allowed_states` (default: `[0, 2, 3]`)

**CheapestPriceSpeed** (output, `pmt2core_cheapest_price_speed.state`):

| State | Name | Meaning |
|---|---|---|
| `1` | Anfrage | Available on request – at least one component is in "anfrage" state |
| `3` | Buchbar | Bookable – all components are in a bookable state |
| `5` | Stop | **Not bookable** – at least one component is in an invalid/blocked state |

**MongoDB Index Mapping:** When prices are aggregated for the MongoDB search index, the CheapestPriceSpeed states are **remapped** to different values via SQL `CASE WHEN`:

| CheapestPriceSpeed | → | MongoDB Index | Name |
|---|---|---|---|
| `3` | → | `100` | Buchbar |
| `1` | → | `200` | Anfrage |
| `5` | → | `300` | Stop |
| any other | → | `300` | Stop (fallback) |

```sql
-- Source: Pressmind\Search\MongoDB\Indexer::_aggregatePrices()
CASE
    WHEN state = 3 THEN 100
    WHEN state = 1 THEN 200
    WHEN state = 5 THEN 300
    ELSE 300
END AS state
```

This remapping ensures a clean, sortable numeric scale in the MongoDB documents: lower values = better availability (`100 < 200 < 300`).

### Critical Differences

```
⚠️  State 0:
    Date:      "Kein Status"   → treated as BOOKABLE
    Housing:   "Ausgebucht"    → included in filter, but NOT bookable (sold out)
    Extras:    "Ausgebucht"    → NOT bookable (sold out)
    Transport: "Kein Status"   → treated as BOOKABLE

⚠️  State 1:
    Date:      "Buchbar"       → IS bookable
    Housing:   "Anfrage"       → triggers REQUEST state
    Extras:    "Anfrage"       → triggers REQUEST state
    Transport: "Gesperrt"      → NOT included (excluded by default!)

⚠️  State 2:
    Date:      "Anfrage"       → triggers REQUEST state
    Housing:   "Wenig"         → IS bookable (few left)
    Extras:    "Wenig"         → IS bookable (few left)
    Transport: "Anfrage"       → triggers REQUEST state

⚠️  State 3:
    Date:      "Gesperrt"      → NOT included (excluded by default!)
    Housing:   "Aktiv"         → IS bookable
    Extras:    "Buchbar"       → IS bookable
    Transport: "Buchbar"       → IS bookable

⚠️  State 4:
    Date:      "Wenig"         → treated as BOOKABLE (low avail.)
    Housing:   "Buchungsstopp" → excluded from filter by default
    Extras:    "Buchungsstopp" → blocks booking
    Transport: —               → (not defined for transports)

⚠️  State 5:
    Date:      "Ausgebucht"    → included in filter, but NOT bookable
    Housing:   "Ausblenden"    → excluded from filter by default
    Extras:    "Ausblenden"    → hidden
    Transport: —               → (not defined for transports)

⚠️  State 6:
    Date:      —               → (not defined for dates)
    Housing:   "Kontingentverfall" → excluded from filter by default
    Extras:    —               → (not defined for tickets/extras/sightseeing)
    Transport: —               → (not defined for transports)
```

### State Determination Logic

The final `CheapestPriceSpeed.state` is calculated by combining all component states:

```
is_bookable = date.state IN [1, 4, 0]          // Date: buchbar, wenig, or kein status
              AND option.state IN [3, 2]        // Option: aktiv or wenig
              AND transport_way1.state IN [3, 0] // Transport: buchbar or kein status
              AND transport_way2.state IN [3, 0]

is_request  = date.state IN [2]                 // Date: anfrage
              OR option.state IN [1]            // Option: anfrage
              OR transport_way1.state IN [2]    // Transport: anfrage
              OR transport_way2.state IN [2]

Special cases for included options:
  if included_option.state == 4  → is_bookable = false, is_request = false  // Buchungsstopp
  if included_option.state == 1  → is_bookable = false, is_request = true   // Anfrage

Final CheapestPriceSpeed state:
  if is_bookable → state = 3 (bookable)
  if is_request  → state = 1 (request)
  else           → state = 5 (stop)
```

**Key rules:**
- A single component in "request/anfrage" state makes the **entire price** "request"
- A single component in an invalid state **hides the entire price**
- Date state `4` (Wenig) is treated as **bookable** – few seats left, but still available
- Date state `5` (Ausgebucht) passes the default filter but is NOT in `[1, 4, 0]` → produces stop prices (CheapestPriceSpeed state 5)
- Date state `3` (Gesperrt) is excluded from the default filter entirely
- Option state `0` (Ausgebucht/Sold out) is included in the default filter but does **not** make the price bookable (state 0 is NOT in `[3, 2]`)

### State Mapping Diagram

```
Date               Housing Option     Transport          → CheapestPriceSpeed
─────────────────────────────────────────────────────────────────────────────
0 (kein status) +  3 (aktiv)       +  3 (buchbar)      →  3 (bookable)
1 (buchbar)     +  3 (aktiv)       +  0 (kein status)  →  3 (bookable)
4 (wenig)       +  3 (aktiv)       +  3 (buchbar)      →  3 (bookable)
0 (kein status) +  2 (wenig)       +  3 (buchbar)      →  3 (bookable)
2 (anfrage)     +  3 (aktiv)       +  3 (buchbar)      →  1 (request)
1 (buchbar)     +  1 (anfrage)     +  3 (buchbar)      →  1 (request)
0 (kein status) +  3 (aktiv)       +  2 (anfrage)      →  1 (request)
5 (ausgebucht)  +  3 (aktiv)       +  3 (buchbar)      →  5 (stop) ← date passes filter but not bookable
0 (kein status) +  0 (ausgebucht)  +  3 (buchbar)      →  5 (stop) ← option passes filter but not bookable
3 (gesperrt)    +  3 (aktiv)       +  3 (buchbar)      →  (filtered out by date_filter)
0 (kein status) +  4 (buchungsstopp)+ 3 (buchbar)      →  (filtered out by housing_option_filter)
0 (kein status) +  5 (ausblenden)  +  3 (buchbar)      →  (filtered out by housing_option_filter)
0 (kein status) +  6 (kontingentv.)+  3 (buchbar)      →  (filtered out by housing_option_filter)
1 (buchbar)     +  3 (aktiv)       +  1 (gesperrt)     →  (filtered out by transport_filter)

Included Extras (required options within price_mix):
Date               Extra              Transport          → Effect on CheapestPriceSpeed
─────────────────────────────────────────────────────────────────────────────
*                +  3 (buchbar)     +  *                →  no effect (included in price)
*                +  1 (anfrage)     +  *                →  forces is_request = true → state 1
*                +  4 (buchungsstopp)+  *               →  forces is_bookable = false, is_request = false → state 5 (stop)
*                +  0 (ausgebucht)  +  *                →  not selected (state 0 NOT in [1,2,3])
```

---

## Option Price Due Modes

The `price_due` field defines how the option price is structured. **Important:** The available `price_due` values and their calculation behavior differ by option type. Not every `price_due` value is valid for every option type.

### Housing Option (`type = 'housing_option'`)

Housing options represent the primary accommodation pricing. Their `price_due` values are **never** converted by `calculatePrice()` – they are stored and passed through as-is to `CheapestPriceSpeed.option_price_due`.

| `price_due` | German | Meaning | Stored As |
|---|---|---|---|
| `person_stay` | Pro Person pro Aufenthalt | Price per person for the entire stay | Used as-is (default) |
| `stay` | Pro Aufenthalt | Total price for the entire stay, regardless of persons | Used as-is |
| `nights_person` | Pro Nacht pro Person | Price per night per person | Used as-is |

The downstream consumer (e.g. IBE, template) is responsible for interpreting `option_price_due` correctly when displaying housing option prices. For example, a `nights_person` housing option with `price = 80` and 7 nights would need the display layer to calculate `80 × 7 = 560 €`.

### Extras, Tickets, Sightseeing (`type = 'ticket' | 'sightseeing' | 'extra'`)

These option types represent additional services. Their periodic prices **are** converted to one-time amounts via `Option::calculatePrice()` during CheapestPrice aggregation. After conversion, the `price_due` is set to `'once'`.

| `price_due` | German | Meaning | Calculation |
|---|---|---|---|
| `once` | Einmalig pro Person | One-time price per person | `price` (no change) |
| `once_stay` | Einmalig pro Aufenthalt | One-time price per stay | `price` (no change) |
| `nightly` | Nächtlich | Per night | `price × nights` → converted to `once` |
| `daily` | Täglich | Per day | `price × duration_days` → converted to `once` |
| `weekly` | Wöchentlich | Per week | `price × ceil(duration_days / 7)` → converted to `once` |

### Conversion Logic (`Option::calculatePrice()`)

```php
// Source: Pressmind\ORM\Object\Touristic\Option::calculatePrice()
public function calculatePrice($duration_days, $nights)
{
    $price = $this->price;

    // ONLY converts for these three types – housing_option is NOT included!
    if (in_array($this->type, ['ticket', 'sightseeing', 'extra'])
        && in_array($this->price_due, ['nightly', 'daily', 'weekly'])) {

        if (in_array($this->price_due, ['nightly', 'nights_person'])) {
            $price = $this->price * $nights;
        }
        if ($this->price_due == 'daily') {
            $price = $this->price * $duration_days;
        }
        if ($this->price_due == 'weekly') {
            $price = $this->price * ceil($duration_days / 7);
        }

        // After calculation, flatten to 'once'
        $this->price_due = 'once';
        $this->price = $price;
    }
    return $price;
}
```

**Key observations:**
- The method **mutates** the option object – after calling `calculatePrice()`, both `price` and `price_due` are permanently changed on that instance
- `housing_option` is deliberately excluded from the type check because housing prices carry their `price_due` semantics through to the display layer
- `nights_person` appears in the validator for housing options but is also handled in the conversion guard for extras – if an extra were to have `nights_person`, it would be converted; for housing options, it is preserved
- `once` and `once_stay` are never converted regardless of option type (they are not in the periodic price list)
- This conversion only runs for **included options** (required extras) during aggregation, since the main housing option price is added directly without calling `calculatePrice()`

### Price Due Flow in CheapestPrice Aggregation

```
Housing Option (main price driver)
  └── price_due: person_stay / stay / nights_person
      └── NOT converted → stored directly as option_price_due
      └── Added to total price as-is: $price = $option->price + ...

Included Options (required extras/tickets/sightseeing)
  └── price_due: once / once_stay / nightly / daily / weekly
      └── calculatePrice() called with booking_package.duration and housing_package.nights
      └── Periodic values (nightly/daily/weekly) → multiplied and flattened to 'once'
      └── Result added to included_options_price
```

### Validator Reference

The complete list of allowed `price_due` values (from the ORM definition):

```php
// Source: Pressmind\ORM\Object\Touristic\Option (property definition)
'validators' => [[
    'name' => 'inarray',
    'params' => [
        'once',          // extras: one-time per person (default for extras)
        'once_stay',     // extras: one-time per stay
        'nightly',       // extras: per night
        'daily',         // extras: per day
        'weekly',        // extras: per week
        'person_stay',   // housing_option: per person per stay (default)
        'stay',          // housing_option: per stay
        'nights_person', // housing_option: per night per person
    ],
]]
```

---

## Agency-Based Pricing

When `agency_based_option_and_prices` is enabled, the entire aggregation runs separately for each agency:

```json
{
  "data": {
    "touristic": {
      "agency_based_option_and_prices": {
        "enabled": true,
        "allowed_agencies": [10, 20, 30]
      }
    }
  }
}
```

Each iteration filters:
- Dates by `date.agencies` field (comma-separated list)
- Options by agency
- Early bird discounts by `discount.agency`

This produces separate CheapestPriceSpeed entries per agency, each stored with the `agency` field.

---

## Quota Tracking

The `quota_pax` field on CheapestPriceSpeed tracks the minimum available quota across all components:

```php
$quota = min(
    option.quota × option.occupancy,
    transport_way1.quota,
    transport_way2.quota,
    included_option_1.quota,
    included_option_2.quota,
    ...
)
```

A null quota is treated as unlimited (999). This enables "last seats" or "limited availability" indicators in the frontend.

---

## Virtual Prices

When products have no touristic booking packages but have manually entered cheapest prices in the PIM, the `MediaObjectCheapestPrice` importer creates **virtual prices**:

```php
// Import\MediaObjectCheapestPrice::import()
// Creates virtual BookingPackage + Date + Option from manual price data
$booking_package->is_virtual_created_price = true;
$booking_package->price_mix = 'date_housing';
```

This allows products without full touristic data to still appear in price-based search results. Virtual prices are marked with `is_virtual_created_price = true`.

This behavior can be disabled per object type:

```json
{
  "data": {
    "touristic": {
      "disable_virtual_price_calculation": [789]
    }
  }
}
```

---

## Performance Optimizations

1. **Batch insert:** Entries are collected in memory and inserted in chunks of 500 rows
2. **Max rows limit:** `max_offers_per_product` (default 5000) prevents explosion for products with many combinations
3. **Delete-before-insert:** All existing entries for the media object are deleted before recalculation
4. **Fingerprint deduplication:** SHA256 fingerprints detect identical price combinations
5. **Global import flags:** EarlyBird imports run only once per session
6. **Single room index:** Calculated as a post-processing step via a single SQL query

---

## Configuration Reference

| Config Path | Default | Description |
|---|---|---|
| `data.touristic.max_offers_per_product` | `5000` | Maximum CheapestPriceSpeed entries per product |
| `data.touristic.ibe_client` | `null` | IBE client identifier |
| `data.touristic.include_negative_option_in_cheapest_price` | `true` | Include options with negative prices |
| `data.touristic.generate_single_room_index` | `false` | Calculate single room supplement |
| `data.touristic.generate_offer_for_each_startingpoint_option` | `false` | Create entry per starting point |
| `data.touristic.generate_offer_for_each_transport_type` | `false` | Create entry per transport type |
| `data.touristic.generate_offer_for_each_option_board_type` | `false` | Create entry per board type |
| `data.touristic.date_filter.active` | `false` | Enable date filtering |
| `data.touristic.date_filter.orientation` | `departure` | `departure` or `arrival` |
| `data.touristic.date_filter.offset` | `0` | Days from now (skip past dates) |
| `data.touristic.date_filter.max_date_offset` | `730` | Max days into the future |
| `data.touristic.date_filter.allowed_states` | `[0,1,2,4,5]` | Allowed date states |
| `data.touristic.housing_option_filter.active` | `false` | Enable housing option state filter |
| `data.touristic.housing_option_filter.allowed_states` | `[0,1,2,3]` | Allowed option states |
| `data.touristic.transport_filter.active` | `false` | Enable transport state filter |
| `data.touristic.transport_filter.allowed_states` | `[0,2,3]` | Allowed transport states |
| `data.touristic.agency_based_option_and_prices.enabled` | `false` | Enable agency-based pricing |
| `data.touristic.agency_based_option_and_prices.allowed_agencies` | `[]` | Agency IDs to process |
| `data.touristic.disable_virtual_price_calculation` | `[]` | Object type IDs without virtual prices |
| `data.touristic.disable_manual_cheapest_price_import` | `[]` | Object type IDs without manual prices |
