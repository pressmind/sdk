# Booking Package – Conceptual Guide

[← Back to Documentation](documentation.md) | [→ Booking Package Reference](Touristic/Booking/Package.md)

---

## Introduction

A **Booking Package** is the root touristic entity within a MediaObject. While a MediaObject represents a touristic product (e.g. a trip, a hotel, or a cruise), the Booking Package defines the **concrete bookable offer** — including its duration, available departure dates, accommodation options, and additional services.

A single MediaObject can contain **multiple Booking Packages**, each representing a distinct variant of the product.

**Namespace:** `Pressmind\ORM\Object\Touristic\Booking\Package`  
**Table:** `pmt2core_touristic_booking_packages`

---

## Why Multiple Booking Packages?

A product may offer multiple Booking Packages for two reasons — or a combination of both:

### 1. Duration Differentiation

The most common case: the same trip is available in different lengths. Each duration gets its own Booking Package because all prices within a package always refer to the same travel duration.

| id_booking_package | id_media_object | name | duration |
|---|---|---|---|
| pkg-001 | 12345 | 7 Days Mallorca | 7 |
| pkg-002 | 12345 | 14 Days Mallorca | 14 |
| pkg-003 | 12345 | 21 Days Mallorca | 21 |

### 2. Service Bundle Differentiation

Two packages can have the **same duration** but represent entirely different travel experiences. The Booking Package itself defines the overall service bundle — the complete set of what a traveler gets for the advertised price.

**Booking Packages:**

| id_booking_package | id_media_object | name | duration |
|---|---|---|---|
| pkg-010 | 67890 | 5 Days Mallorca Hiking Package | 5 |
| pkg-011 | 67890 | 5 Days Mallorca Wellness Package | 5 |

The differentiation is expressed through the entire composition of the package: different housing options (e.g. mountain lodge vs. spa hotel), different available extras, and different pricing. Each package represents a self-contained product variant with its own set of services, dates, and prices.

**Housing Options (different accommodations per package):**

| id | id_booking_package | name | season | price | occupancy |
|---|---|---|---|---|---|
| opt-h10 | pkg-010 | Mountain Lodge | A | 749.00 | 2 |
| opt-h11 | pkg-011 | Spa Hotel Suite | A | 1099.00 | 2 |

**Available Extras (different offerings per package):**

| id | id_booking_package | name | type | price |
|---|---|---|---|---|
| opt-w1 | pkg-010 | Guided Mountain Hike | extra | 45.00 |
| opt-w2 | pkg-010 | Climbing Course | extra | 89.00 |
| opt-s1 | pkg-011 | Spa Day Pass | extra | 65.00 |
| opt-s2 | pkg-011 | Yoga Retreat | extra | 55.00 |

Each package is a self-contained world: its own housing, its own extras, its own pricing. The package name communicates the overall experience to the traveler.

### Combined Differentiation

Both dimensions can be combined — e.g. a 7-day and 14-day hiking variant, plus a 7-day and 14-day wellness variant, resulting in 4 Booking Packages for one product.

---

## Entity Hierarchy

```
MediaObject
 └── Booking Package (1:n)
      │
      ├── Dates (1:n)
      │    ├── Transports (1:n)
      │    │    └── Startingpoint (n:1)
      │    └── EarlyBirdDiscountGroup (0:1)
      │
      ├── Housing Packages (1:n)
      │    └── Options [type=housing_option] (1:n)
      │         └── Discount (0:1)
      │
      ├── Insurance Group (0:1)
      │    └── Insurances (1:n)
      │
      ├── Extras [type=extra] (1:n)
      ├── Tickets [type=ticket] (1:n)
      └── Sightseeings [type=sightseeing] (1:n)
```

> **Note:** Insurance is NOT an extra/ticket/sightseeing. It is a separate entity referenced from the Booking Package via `id_insurance_group`. Insurance groups contain their own pricing model independent of the season matching mechanism.

**Key relationships:**
- All entities within one Booking Package share the same `id_booking_package`
- All entities also carry `id_media_object` for direct product lookup
- Dates and Services (Options) are linked indirectly via the **season** code (see below)

---

## Dates (Departures)

Each Booking Package contains multiple **Dates** — representing departure/return pairs. Every Date carries a `season` code that links it to the available services.

| id | id_booking_package | departure | arrival | season |
|---|---|---|---|---|
| date-01 | pkg-001 | 2025-04-05 | 2025-04-12 | A |
| date-02 | pkg-001 | 2025-04-12 | 2025-04-19 | A |
| date-03 | pkg-001 | 2025-06-21 | 2025-06-28 | B |
| date-04 | pkg-001 | 2025-07-05 | 2025-07-12 | B |
| date-05 | pkg-001 | 2025-08-16 | 2025-08-23 | C |

Multiple dates can share the same season code — this means they share the same pricing and service availability. The season code is a free alphanumeric value (commonly A–Z or codes like `S24`, `W25`).

See: [Date Reference](Touristic/Date.md)

---

## Services

Services are bookable items within a Booking Package. They are all stored as `Option` entities, distinguished by the `type` field.

### Housing Options (Accommodation)

Grouped under a **Housing Package** (which defines room type and number of nights). Each Housing Option represents a specific room/cabin category with its price for a given season.

- Linked via: Housing Package → Options (`type=housing_option`)
- Season matching: **strict** (see below)

### Extras, Tickets, Sightseeings (Additional Services)

Attached directly to the Booking Package. These represent optional or required add-on services:

| Type | Use Case |
|---|---|
| `extra` | Additional services (transfers, meal upgrades, equipment) |
| `ticket` | Entrance tickets, event access |
| `sightseeing` | Excursions, guided tours |

- Season matching: **flexible** with wildcard support (see below)
- Alternative linking via **reservation_date** (see below)

### Required Groups

Options can be organized into **required groups** using the `required_group` field. A required group forces the traveler to choose exactly one option from a set of alternatives. This is used when a service is mandatory but available in different categories or price tiers.

**Example: Ticket category selection (required_group = "A")**

| id | id_booking_package | name | type | required_group | required | price |
|---|---|---|---|---|---|---|
| opt-pk1 | pkg-001 | Entrance PK1 (Front Row) | ticket | A | true | 25.00 |
| opt-pk2 | pkg-001 | Entrance PK2 (Midsection) | ticket | A | true | 25.00 |
| opt-pk3 | pkg-001 | Entrance PK3 (Standard) | ticket | A | true | 0.00 |

**How it works:**
- All three options belong to `required_group = "A"` with `required = true` — this creates a **radio group** where the traveler must pick exactly one
- PK3 at `price = 0.00` represents the base category (already included in the travel price)
- PK1 and PK2 at `price = 25.00` represent upgrades with a surcharge on top of the base price
- The booking form presents this as: "Choose your ticket category" with three mutually exclusive options

This pattern is common for seating categories, cabin classes, meal plans, or any service where a base variant is included and upgraded variants are available at a surcharge.

See: [Option Reference](Touristic/Option.md) | [Housing Package Reference](Touristic/Housing/Package.md)

---

## Season Matching

The **season** field is the primary mechanism that connects Dates to Services. It is sometimes referred to as "Season Key" in external documentation.

### How it Works

```
Date.season = "A"  ←→  Option.season = "A"
```

An Option with `season = "A"` applies to all Dates with `season = "A"`. This allows one set of prices to cover multiple departure dates within the same pricing season.

### Housing Options: Strict Matching

For housing options, the match is **exact** — only options whose `season` value equals the date's `season` are returned.

**Options (type=housing_option):**

| id | id_booking_package | name | season | price | occupancy |
|---|---|---|---|---|---|
| opt-h1 | pkg-001 | Double Room | A | 899.00 | 2 |
| opt-h2 | pkg-001 | Double Room | B | 1199.00 | 2 |
| opt-h3 | pkg-001 | Double Room | C | 1399.00 | 2 |
| opt-h4 | pkg-001 | Single Room | A | 1099.00 | 1 |

**Result (JOIN: `date.season = option.season`):**

| Date (departure) | Date.season | Available Housing Options |
|---|---|---|
| 2025-04-05 | A | opt-h1 (Double 899), opt-h4 (Single 1099) |
| 2025-06-21 | B | opt-h2 (Double 1199) |
| 2025-08-16 | C | opt-h3 (Double 1399) |

**Code reference:** `Date::getHousingOptions()` — uses SQL `season = '{date.season}'` with no wildcard support.

### Extras / Tickets / Sightseeings: Flexible Matching with Wildcards

For non-housing services, the matching is more permissive. The following `season` values act as **wildcards** (meaning the service applies to all dates):

| Option.season | Meaning |
|---|---|
| `'-'` (hyphen) | Wildcard — applies to all dates |
| `''` (empty string) | Wildcard — applies to all dates |
| `NULL` | Wildcard — applies to all dates |
| `'A'` (or any value) | Applies only to dates with matching season |

**Options (type=extra):**

| id | id_booking_package | name | season | reservation_date_from | reservation_date_to | price |
|---|---|---|---|---|---|---|
| opt-e1 | pkg-001 | Airport Transfer | A | NULL | NULL | 49.00 |
| opt-e2 | pkg-001 | Airport Transfer | B | NULL | NULL | 59.00 |
| opt-e3 | pkg-001 | Museum Visit | - | NULL | NULL | 18.00 |
| opt-e4 | pkg-001 | Welcome Dinner | NULL | NULL | NULL | 25.00 |

**Result (including wildcards):**

| Date (departure) | Date.season | Available Extras |
|---|---|---|
| 2025-04-05 | A | opt-e1 (Transfer 49), opt-e3 (Museum 18), opt-e4 (Dinner 25) |
| 2025-06-21 | B | opt-e2 (Transfer 59), opt-e3 (Museum 18), opt-e4 (Dinner 25) |
| 2025-08-16 | C | opt-e3 (Museum 18), opt-e4 (Dinner 25) |

opt-e3 (`season = '-'`) and opt-e4 (`season = NULL`) match all dates regardless of season. opt-e1/e2 only match their respective season code.

**Code reference:** `Date::getOptions()` — uses SQL `season IN ('{date.season}', '-', '') OR season IS NULL`.

---

## Reservation Date (Alternative Linking)

For extras, tickets, and sightseeings, there is an alternative to season matching: the **reservation date** mechanism.

### How it Works

Instead of linking via the season code, an option can be linked to a **specific date** by setting:

- `reservation_date_from` = the date's `departure` (exact match)
- `reservation_date_to` = the date's `arrival` (exact match)

> **Important:** This is NOT a validity range. It does not mean "this option is available from May to September". It is an explicit 1:1 mapping to a concrete travel date. The values must match departure and arrival exactly.

### Priority

When both `reservation_date_from` and `reservation_date_to` are set on an option, the reservation date match takes priority — the `season` field is ignored for that option. The two linking mechanisms are evaluated as OR alternatives:

1. **Path A (Reservation Date):** Both fields are NOT NULL and match `date.departure` / `date.arrival` exactly
2. **Path B (Season):** Both fields are NULL and season matches (including wildcards)

If only one of the two fields is set, the option will **never match** any date.

### Applies Only to Non-Housing Services

Housing options do **not** support reservation date matching. They always use strict season matching.

### Example

**Options (type=ticket):**

| id | id_booking_package | name | season | reservation_date_from | reservation_date_to | price |
|---|---|---|---|---|---|---|
| opt-t1 | pkg-001 | City Tour Palma | NULL | 2025-06-21 | 2025-06-28 | 35.00 |
| opt-t2 | pkg-001 | Wine Tasting | NULL | 2025-08-16 | 2025-08-23 | 45.00 |
| opt-t3 | pkg-001 | Boat Trip | - | NULL | NULL | 60.00 |

**Result:**

| Date (departure / arrival) | Matching Path | Available Tickets |
|---|---|---|
| 2025-04-05 / 2025-04-12 | Season (wildcard) | opt-t3 (Boat Trip 60) |
| 2025-06-21 / 2025-06-28 | Reservation Date + Season | opt-t1 (City Tour 35), opt-t3 (Boat Trip 60) |
| 2025-08-16 / 2025-08-23 | Reservation Date + Season | opt-t2 (Wine Tasting 45), opt-t3 (Boat Trip 60) |

opt-t1 only matches the date 2025-06-21 because `reservation_date_from = departure` AND `reservation_date_to = arrival`. opt-t3 with `season = '-'` matches all dates via season wildcard.

**Code references:** `Date::getOptions()`, `Booking::getAllAvailableExtras()`

---

## Matching Rules Summary

| Option.season | reservation_date_from | reservation_date_to | Matching Logic | Applies to |
|---|---|---|---|---|
| `'A'` | NULL | NULL | Exact season match | Only dates with season=A |
| `'-'` | NULL | NULL | Wildcard | All dates |
| `''` | NULL | NULL | Wildcard | All dates |
| NULL | NULL | NULL | Wildcard | All dates |
| (any) | 2025-06-21 | 2025-06-28 | Reservation date match | Only date with departure=2025-06-21 AND arrival=2025-06-28 |
| `'A'` | 2025-06-21 | NULL | Invalid (incomplete) | Never matches |

> **Housing options use ONLY strict season matching — no wildcards, no reservation date support.**

---

## Price Mix

The `price_mix` field on the Booking Package defines which service type constitutes the **base price** (advertised minimum price). This determines how the cheapest price is calculated for search results and teaser displays.

| price_mix | Base Price Element | Typical Use |
|---|---|---|
| `date_housing` | Housing Option price | Multi-day tours with accommodation (default, ~70%) |
| `date_transport` | Transport price only | Day trips without accommodation |
| `date_extra` | Extra price | Day trips where an extra is the main offer |
| `date_ticket` | Ticket price | Event-based day trips |
| `date_sightseeing` | Sightseeing price | Excursion-based day trips |

See: [CheapestPrice Aggregation](cheapest-price-aggregation.md) for the complete price calculation algorithm.

---

## Related Documentation

- [Booking Package Reference](Touristic/Booking/Package.md) — Complete property reference
- [Date Reference](Touristic/Date.md) — Departure dates, state enum, season field
- [Option Reference](Touristic/Option.md) — Service types, price_due modes, required groups
- [Housing Package Reference](Touristic/Housing/Package.md) — Accommodation groups
- [CheapestPrice Aggregation](cheapest-price-aggregation.md) — Price calculation pipeline
- [Price Selection Logic](price-selection-logic.md) — How the displayed price is chosen
- [Booking Offer Table](booking-offer-table.md) — Rendering dates & prices in templates
- [Configuration: Touristic Data](config-touristic-data.md) — Import filters and offer generation
