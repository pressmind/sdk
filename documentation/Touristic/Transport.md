# Touristic: Transport

[← Back to Documentation](../documentation.md) | [→ Date](Date.md) | [→ Startingpoint](Startingpoint.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Transport`

---

## Description

Transports define the outbound and return travel for a departure date. Each Date is assigned either **no transports** (self-organized travel) or **at least two transports** (one outbound `way=1`, one return `way=2`) forming a transport pair.

Transport pairs are generated according to a defined logic and offered as inclusive services. Transports can be bus rides, flights, train journeys, car transfers, etc.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string(32) | yes | Primary key |
| `id_date` | string(32) | yes | Reference to the parent Date |
| `id_media_object` | integer | yes | Reference to the parent MediaObject |
| `id_booking_package` | string(32) | yes | Reference to the parent Booking Package |
| `id_early_bird_discount_group` | string(32) | no | Reference to Early Bird Discount Group |
| `code` | string(255) | no | For flights (`type=FLUG`): IATA-compliant 3-letter route code. First 3 = departure airport, last 3 = arrival airport. Examples: `CGNPMI`, `CGN-PMI`, `CGNHAMPMI` |
| `airline` | string(255) | no | Airline 2-letter IATA code |
| `flight` | string | no | Flight number |
| `description` | string | no | Free-text description |
| `type` | string(5) | no | Transport type. See [Type Enum](#type) |
| `way` | integer | yes | `1` = outbound, `2` = return |
| `price` | float | yes | Price (surcharge for this transport) |
| `order` | integer | no | Sort order |
| `state` | integer | no | Status. See [State Enum](#state) |
| `code_ibe` | string(255) | no | CRS booking code |
| `transport_group` | string(255) | no | For combined travel (e.g. train + flight or stopover flights): groups transports into a single package. Not visible to the end user |
| `id_starting_point` | string(32) | no | Reference to boarding point group (for bus travel) |
| `transport_date_from` | datetime | no | When `transport_group` is set, transports within the group are ordered by these times. See `Date.getTransportPairs()` |
| `transport_date_to` | datetime | no | End time for grouped transports |
| `time_departure` | time | no | Departure time (local time) |
| `time_arrival` | time | no | Arrival time (local time) |
| `age_from` | integer | no | Age restriction (minimum) |
| `age_to` | integer | no | Age restriction (maximum) |
| `use_earlybird` | boolean | no | If `true`, Early Bird discounts may be applied |
| `dont_use_for_offers` | boolean | no | If `true`, excluded from base price calculation (e.g. child-only transport) |
| `seatplan_required` | boolean | no | If `true`, a seat reservation is required. Seat availability must be queried live from the CRS |
| `request_code` | string(25) | no | CRS request code |
| `agencies` | string | no | Comma-separated list of allowed agencies |
| `crs_meta_data` | longtext | no | Free-form CRS metadata (JSON) |

### Relations

| Relation | Type | Description |
|---|---|---|
| `starting_points` | Startingpoint[] | Boarding point groups (for bus travel) |
| `early_bird_discount_group` | EarlyBirdDiscountGroup | Early bird discount schedule |
| `discount` | Discount | Age/occupancy-based discount |

### Deprecated Properties

| Property | Note |
|---|---|
| `auto_book` | No longer used |
| `required` | No longer used |
| `required_group` | No longer used |

---

## Type

| Value | Label | Description |
|---|---|---|
| `BUS` | Busreise | Bus travel |
| `PKW` | Eigenanreise | Self-drive / car |
| `FLUG` | Flugreise | Flight |
| `SCHIFF` | Schiffsreise | Ship / cruise |
| `BAH` | Bahnreise | Train / rail |

> **Note:** The SDK code defines `BAH` (3 characters) as the transport type key for rail. The PIM may send `BAHN` (4 characters) in some configurations. Both values should be handled in frontend code. The `pm-tr` search parameter uses these type values for filtering. See [MongoDB Search API: pm-tr](../search-mongodb-api.md).

---

## State

| Value | Key | Description |
|---|---|---|
| `0` | No status | Default (treated as active) |
| `1` | Blocked | Not available |
| `2` | On request | Available on request |
| `3` | Bookable | Confirmed available |

> **Important:** Transport states have a **different meaning** than Date or Option states. See [CheapestPrice Aggregation: State Machine](../cheapest-price-aggregation.md) for the complete state mapping across all entity types.

---

## Transport Pairs

Transports are always consumed as **pairs** (outbound + return). The pairing logic in `Date::getTransportPairs()` works as follows:

1. All transports for a Date are grouped by `transport_group` (if set)
2. Within each group, transports are paired by `way` (1 = out, 2 = return)
3. If `transport_group` is set, ordering within the group uses `transport_date_from`

```
Date (departure: 2025-06-15, arrival: 2025-06-22)
 ├── Transport (way=1, type=BUS, code="Hamburg")     → outbound
 ├── Transport (way=2, type=BUS, code="Hamburg")     → return
 ├── Transport (way=1, type=FLUG, code="HAMPMICGN")  → outbound (alternative)
 └── Transport (way=2, type=FLUG, code="PMICGNHAM")  → return (alternative)
```

The cheapest valid transport pair is used for the base price calculation.

---

## IATA Flight Code Format

For `type = FLUG`, the `code` field must contain an IATA-compliant route:

| Format | Example | Meaning |
|---|---|---|
| `ABCXYZ` | `CGNPMI` | CGN → PMI (Cologne to Palma) |
| `ABC-XYZ` | `CGN-PMI` | Same, with separator |
| `ABCDEFXYZ` | `CGNHAMPMI` | Multi-leg: CGN → HAM → PMI |

---

## Related Documentation

- [Date](Date.md) – Parent entity for transports
- [Startingpoint](Startingpoint.md) – Boarding points for bus travel
- [EarlyBirdDiscountGroup](EarlybirdDiscountGroup.md) – Early bird discounts for transports
- [CheapestPrice Aggregation](../cheapest-price-aggregation.md) – Transport state mapping and price calculation
- [Configuration: transport_filter](../config-touristic-data.md) – Which transport states are included
