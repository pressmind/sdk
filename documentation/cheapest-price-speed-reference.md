# CheapestPriceSpeed Property Reference

[← Back to Booking Offer Table](booking-offer-table.md) | [→ CheapestPrice Aggregation](cheapest-price-aggregation.md)

**Namespace:** `Pressmind\ORM\Object\CheapestPriceSpeed`
**Table:** `pmt2core_cheapest_price_speed`

---

## Overview

`CheapestPriceSpeed` is a denormalized table that stores pre-calculated, final offer prices. Each row represents one bookable combination of date + option + transport + starting point, with all price components already resolved. This is the **only reliable source for displaying offer prices** to customers.

The table is populated during every import by `MediaObject::insertCheapestPrice()`. See [CheapestPrice Aggregation](cheapest-price-aggregation.md) for the full calculation pipeline.

---

## Properties

### Identity & References

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `id` | integer | — | Auto-increment primary key |
| `fingerprint` | string(64) | — (calculated) | SHA256 hash for deduplication. See [Fingerprint & Storage](cheapest-price-aggregation.md#step-10-fingerprint--storage) |
| `id_media_object` | string(32) | `Booking\Package.id_media_object` | Reference to the parent MediaObject |
| `id_booking_package` | string(32) | `Booking\Package.id` | Reference to the source booking package |
| `id_housing_package` | string(32) | `Housing\Package.id` | Reference to the source housing package. Empty for non-housing `price_mix` |
| `id_date` | string(32) | `Date.id` | Reference to the source date. Use for grouping offers by departure |
| `id_option` | string(32) | `Option.id` | Reference to the primary option (housing, ticket, extra, etc.) |
| `id_transport_1` | string(32) | `Transport.id` (way=1) | Reference to the outbound transport. Empty if no transport |
| `id_transport_2` | string(32) | `Transport.id` (way=2) | Reference to the return transport. Empty if no transport |
| `id_origin` | integer | `Booking\Package.id_origin` | Origin/market ID |
| `id_startingpoint` | string(32) | `Transport.id_starting_point` | Reference to the `Startingpoint` group |
| `id_startingpoint_option` | string(32) | `Startingpoint\Option.id` | Reference to the selected starting point option |

### Date & Duration

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `date_departure` | DateTime | `Date.departure` | Departure date |
| `date_arrival` | DateTime | `Date.arrival` | Return/arrival date |
| `duration` | float | `Booking\Package.duration` | Trip duration in days |

### Option (Primary Priced Element)

These fields are copied from the primary `Touristic\Option` that drives the price (determined by `price_mix`).

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `option_name` | string(255) | `Option.name` | Option name (e.g. "Doppelzimmer Meerblick") |
| `option_description_long` | string | `Option.description_long` | Long description of the option |
| `option_code` | string(32) | `Option.code` | Option code |
| `option_board_type` | string(32) | `Option.board_type` | Board type label (e.g. "Halbpension", "All Inclusive") |
| `option_board_code` | string(10) | `Option.board_code` | Board type code (e.g. "HP", "AI") |
| `option_occupancy` | integer | `Option.occupancy` | Standard room occupancy (e.g. 1 = single, 2 = double) |
| `option_occupancy_min` | integer | `Option.occupancy_min` | Minimum allowed occupancy |
| `option_occupancy_max` | integer | `Option.occupancy_max` | Maximum allowed occupancy |
| `option_occupancy_child` | integer | `Option.occupancy_child` | Number of child occupancy slots |
| `option_price_due` | string | `Option.price_due` | Price period mode. See [Option Price Due Modes](cheapest-price-aggregation.md#option-price-due-modes) |

### Price

All price fields are per-person values (unless `option_price_due` indicates otherwise).

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `price_total` | float | — (calculated) | **Final offer price.** Includes all components: option + transport + starting point + included options - early bird discount |
| `price_option` | float | `Option.price` | Base price of the primary option |
| `price_option_pseudo` | float | `Option.price_pseudo` | Crossed-out "was" price (if set in PIM) |
| `price_regular_before_discount` | float | — (calculated) | Total price before early bird discount. Equals `price_total` if no discount |
| `price_transport_total` | float | — (calculated) | Sum of `price_transport_1` + `price_transport_2` |
| `price_transport_1` | float | `Transport.price` (way=1) | Outbound transport price |
| `price_transport_2` | float | `Transport.price` (way=2) | Return transport price |
| `included_options_price` | float | — (calculated) | Sum of cheapest required secondary option prices |
| `diff_to_single_room` | float | — (calculated) | Single room supplement. Only if `generate_single_room_index` is enabled. See [Single Room Index](cheapest-price-aggregation.md#step-11-single-room-index) |
| `price_mix` | string(32) | `Booking\Package.price_mix` | Pricing mode. See [Price Mix Modes](cheapest-price-aggregation.md#price-mix-modes) |

### Early Bird Discount

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `earlybird_discount` | float | `EarlyBirdDiscountGroup\Item.discount_value` (type=P) | Early bird discount percentage (e.g. `10.0` for 10%) |
| `earlybird_discount_f` | float | `EarlyBirdDiscountGroup\Item.discount_value` (type=F) | Early bird discount fixed amount (e.g. `50.0` for 50 EUR) |
| `earlybird_discount_date_to` | DateTime | `EarlyBirdDiscountGroup\Item.booking_date_to` | Booking deadline for the discount |
| `earlybird_name` | string(255) | `EarlyBirdDiscountGroup\Item.name` | Display label (e.g. "Frühbucher", "Sparpreis") |

### Transport

All transport fields exist in pairs (`_1` = outbound, `_2` = return). Fields are empty/null when the product has no transport component.

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `transport_type` | string(10) | `Transport.type` | Transport type: `FLUG`, `BUS`, `BAHN`, `SCHIFF`, `PKW` |
| `transport_code` | string(10) | `Transport.code` | Combined transport code (e.g. IATA pair "FRAPMIMUC") |
| `transport_1_way` | integer | `Transport.way` (=1) | Outbound direction marker (always `1`) |
| `transport_2_way` | integer | `Transport.way` (=2) | Return direction marker (always `2`) |
| `transport_1_description` | string(255) | `Transport.description` (way=1) | Outbound transport description |
| `transport_2_description` | string(255) | `Transport.description` (way=2) | Return transport description |
| `transport_1_airport` | string(3) | `Transport.code` (way=1, first 3 chars) | Outbound departure airport IATA code (e.g. "FRA") |
| `transport_1_airport_name` | string(255) | `Transport.airport_name` (way=1) | Outbound departure airport name |
| `transport_2_airport` | string(3) | `Transport.code` (way=2, last 3 chars) | Return departure airport IATA code |
| `transport_2_airport_name` | string(255) | `Transport.airport_name` (way=2) | Return departure airport name |
| `transport_1_airline` | string(10) | `Transport.airline` (way=1) | Outbound airline code |
| `transport_2_airline` | string(10) | `Transport.airline` (way=2) | Return airline code |
| `transport_1_flight` | string(10) | `Transport.flight` (way=1) | Outbound flight number |
| `transport_2_flight` | string(10) | `Transport.flight` (way=2) | Return flight number |
| `transport_1_date_from` | DateTime | `Transport.transport_date_from` (way=1) | Outbound transport departure date/time |
| `transport_1_date_to` | DateTime | `Transport.transport_date_to` (way=1) | Outbound transport arrival date/time |
| `transport_2_date_from` | DateTime | `Transport.transport_date_from` (way=2) | Return transport departure date/time |
| `transport_2_date_to` | DateTime | `Transport.transport_date_to` (way=2) | Return transport arrival date/time |

### Starting Point

Starting points represent boarding locations for bus trips or similar departure points. Each starting point can carry a surcharge that is included in `price_total`. These fields are empty when the product has no starting point component.

The starting point data in `CheapestPriceSpeed` is populated based on the transport's `id_starting_point` reference. During aggregation, the cheapest starting point option is selected by default. If the config `generate_offer_for_each_startingpoint_option` is enabled, a **separate CheapestPriceSpeed row is created for each starting point**, allowing per-city price display and filtering.

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `id_startingpoint` | string(32) | `Transport.id_starting_point` | Reference to the `Startingpoint` group |
| `id_startingpoint_option` | string(32) | `Startingpoint\Option.id` | Reference to the selected starting point option |
| `startingpoint_name` | string(255) | `Startingpoint\Option.name` | Starting point name (e.g. "ZOB Hamburg") |
| `startingpoint_city` | string(100) | `Startingpoint\Option.city` | City name (e.g. "Hamburg") |
| `startingpoint_zip` | string(5) | `Startingpoint\Option.zip` | ZIP / postal code (e.g. "20097") |
| `startingpoint_id_city` | string(32) | `Startingpoint\Option.getCityId()` | MD5 hash of city name. Used for `GROUP BY` when `generate_offer_for_each_startingpoint_option` is enabled |
| `startingpoint_code_ibe` | string(32) | `Startingpoint\Option.code_ibe` | IBE code for the starting point (used in booking links) |

**Starting point price contribution to `price_total`:**

The starting point surcharge is calculated based on the `Startingpoint\Option` properties:

```
if (price_per_day == true):
    starting_point_price = option.price × duration
else:
    starting_point_price = option.price
```

This price is added to `price_total` alongside the option, transport, and included options prices. If `use_earlybird` is `true` on the starting point option, the surcharge also participates in the early bird discount base calculation.

**Configuration:**

| Config Path | Default | Description |
|---|---|---|
| `data.touristic.generate_offer_for_each_startingpoint_option` | `false` | If `true`, creates a separate `CheapestPriceSpeed` row per starting point city. If `false`, only the cheapest starting point option is used. See [Configuration: Touristic Data](config-touristic-data.md#datatouristicgenerate_offer_for_each_startingpoint_option) |

**Source entities:**

| Class | Table | Description |
|---|---|---|
| `Touristic\Startingpoint` | `pmt2core_touristic_startingpoints` | Group container with `name`, `code`, `text` |
| `Touristic\Startingpoint\Option` | `pmt2core_touristic_startingpoint_options` | Individual boarding point with `price`, `price_per_day`, `city`, `zip`, `street`, `lat`/`lon`, `entry`/`exit` flags, `start_time`, `use_earlybird` |

See also: [Startingpoint](Touristic/Startingpoint.md) | [Startingpoint Option](Touristic/Startingpoint/Option.md)

### Housing Package

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `housing_package_name` | string(255) | `Housing\Package.name` | Housing package name (e.g. "Hotel Mallorca Palace") |
| `housing_package_code` | string(32) | `Housing\Package.code` | Housing package code |
| `housing_package_id_name` | string(32) | `Housing\Package.getNameId()` | MD5 hash of the housing package name (for grouping across booking packages) |

### Booking Package Metadata

Denormalized fields from the parent `Touristic\Booking\Package`.

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `booking_package_name` | string(255) | `Booking\Package.name` | Booking package name |
| `booking_package_code` | string(255) | `Booking\Package.code` | Booking package code |
| `booking_package_ibe_type` | integer | `Booking\Package.ibe_type` | IBE booking type. `0`/`1` = standalone (id-based), `2`+ = code-based |
| `booking_package_product_type_ibe` | string(3) | `Booking\Package.product_type_ibe` | IBE product type code |
| `booking_package_type_of_travel` | string(32) | `Booking\Package.type_of_travel` | Travel type (e.g. "Pauschalreise", "Bausteinreise") |
| `booking_package_variant_code` | string(32) | `Booking\Package.variant_code` | Variant code |
| `booking_package_request_code` | string(10) | `Booking\Package.request_code` | Request code for CRS |
| `booking_package_price_group` | string(5) | `Booking\Package.price_group` | Price group |
| `booking_package_product_group` | string(5) | `Booking\Package.product_group` | Product group |

### IBE Codes

Codes used for deep linking into the IBE (Internet Booking Engine) and CRS communication.

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `date_code_ibe` | string(32) | `Date.code_ibe` | IBE code for the departure date. Used for [availability checks](booking-offer-table.md#availability-check-ibe-api) |
| `housing_package_code_ibe` | string(32) | `Housing\Package.code_ibe` | IBE code for the housing package |
| `option_code_ibe` | string(255) | `Option.code_ibe` | IBE code for the primary option |
| `option_code_ibe_board_type` | string(32) | `Option.code_ibe_board_type` | IBE code for the board type |
| `option_code_ibe_board_type_category` | string(32) | `Option.code_ibe_board_type_category` | IBE code for the board type category |
| `option_code_ibe_category` | string(32) | `Option.code_ibe_category` | IBE code for the option category |
| `option_request_code` | string(32) | `Option.request_code` | CRS request code for the option |
| `transport_1_code_ibe` | string(32) | `Transport.code_ibe` (way=1) | IBE code for outbound transport |
| `transport_2_code_ibe` | string(32) | `Transport.code_ibe` (way=2) | IBE code for return transport |
| `startingpoint_code_ibe` | string(32) | `Startingpoint\Option.code_ibe` | IBE code for the starting point |

### Included Options (Secondary Required Options)

When mandatory extras, tickets, or sightseeings are part of the offer, their prices and metadata are stored here.

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `included_options_price` | float | — (calculated) | Total price sum of all required secondary options |
| `included_options_description` | string(255) | — (aggregated `Option.name`) | Comma-separated names of included options |
| `id_included_options` | string(255) | — (aggregated `Option.id`) | Comma-separated IDs of included options |
| `code_ibe_included_options` | string(255) | — (aggregated `Option.code_ibe`) | Comma-separated IBE codes of included options |
| `id_option_auto_book` | integer | `Option.id` (where `auto_book`=1) | ID of the auto-book option (if any) |
| `id_option_required_group` | integer | `Option.required_group` | Required group identifier |

### State & Availability

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `state` | integer | — (calculated) | Combined availability state. `3` = bookable, `1` = on request, `5` = stop. See [State Machine](cheapest-price-aggregation.md#state-machine) |
| `guaranteed` | boolean | `Date.guaranteed` | `true` if departure is guaranteed regardless of minimum pax |
| `saved` | boolean | `Date.saved` | `true` if the date is marked as "saved" in the PIM |
| `quota_pax` | integer | — (calculated `min()`) | Minimum available quota across all components. `null` = unlimited |
| `infotext` | string(255) | `Date.text` | Additional info text (e.g. "Garantierte Durchführung") |

### Metadata

| Property | Type | ORM Source | Description |
|---|---|---|---|
| `agency` | string(32) | — (config-driven) | Agency ID. Set when agency-based pricing is enabled. See [Agency-Based Pricing](cheapest-price-aggregation.md#agency-based-pricing) |
| `is_virtual_created_price` | boolean | `Booking\Package.is_virtual_created_price` | `true` if generated from manual cheapest price (no full touristic data). See [Virtual Prices](cheapest-price-aggregation.md#virtual-prices) |

---

## Methods

### Static Methods

| Method | Return | Description |
|---|---|---|
| `getMinMaxPrices()` | `[float, float]` | Returns `[lowestPrice, highestPrice]` across all visible MediaObjects |
| `getLowestPrice()` | `float\|null` | Lowest `price_total` across all visible MediaObjects |
| `getHighestPrice()` | `float\|null` | Highest `price_total` across all visible MediaObjects |

### Instance Methods

| Method | Return | Description |
|---|---|---|
| `createFingerprint()` | string(64) | Generates the SHA256 fingerprint for deduplication |
| `generateSingleRoomIndex($id_media_object)` | void | Calculates `diff_to_single_room` for all occupancy=2 rows of the given MediaObject |
| `deleteByMediaObjectId($id)` | mixed | Deletes all CheapestPriceSpeed rows for a MediaObject (used before recalculation) |

---

## Database Indexes

The table uses several composite indexes for query performance:

| Index Name | Columns | Purpose |
|---|---|---|
| `search_filter_index` | `id_media_object`, `price_total`, `date_departure`, `date_arrival`, `option_occupancy`, `option_occupancy_min`, `option_occupancy_max`, `option_occupancy_child`, `duration` | Primary search filter queries |
| `cheapest_price_index` | `id_media_object`, `price_total`, `date_departure` | Fast cheapest price lookup |
| `state` | `state`, `date_departure` | State-filtered queries |
| `aggregate_prices_index` | `id_media_object`, `id_origin`, `price_mix`, `option_occupancy`, `duration` | MongoDB index aggregation |
| `aggregate_prices_agency_index` | `id_media_object`, `id_origin`, `agency`, `price_mix` | Agency-based aggregation |
| `calendar_query_index` | `id_media_object`, `id_origin`, `option_occupancy`, `transport_type` | Calendar view queries |
| `housing_package_id_name_index` | `housing_package_id_name` | Housing package grouping |
| `startingpoint_id_city_index_1` | `startingpoint_id_city` | Starting point city lookups |
| `startingpoint_id_city_index_2` | `date_departure`, `startingpoint_id_city` | Date + starting point queries |

---

## Related Documentation

- [Booking Offer Table](booking-offer-table.md) — How to render the offer table using CheapestPriceSpeed
- [CheapestPrice Aggregation](cheapest-price-aggregation.md) — How this table is populated during import
- [Price Selection Logic](price-selection-logic.md) — How `getCheapestPrice()` selects the best row
- [MediaObject](mediaobject.md) — Entry point: `getCheapestPrice()`, `getCheapestPrices()`
