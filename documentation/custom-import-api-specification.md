# Custom Import API Specification

This document specifies the REST API contract that an external reservation system (CRS) must implement to deliver touristic data to the pressmind® SDK via a Custom Import Hook.

---

## About This Data Model

The touristic data model behind the pressmind® SDK has been developed and refined over approximately **15 years**. It was designed for the group tourism industry and has been successfully mapped to a wide range of reservation systems across this sector. Many common CRS platforms have already been integrated at least once — meaning there is a good chance that an integration with your system (or a similar one) already exists.

**Before starting a new integration, we strongly recommend contacting pressmind to check whether an existing integration or adapter can be reused.** This can save significant development effort on both sides.

The data model is intentionally broad: it supports a wide variety of travel products, pricing models, and business rules — from simple hotel bookings to complex multi-leg round trips with multiple transport modes, starting points, and seasonal pricing. Not every field or entity is relevant for every integration. **You should only provide the data that is actually needed for your specific use case.** Many fields are optional and can be safely omitted.

That said, the model has grown organically over many years and many integrations. Some fields serve edge cases that may not be immediately obvious; some are marked as deprecated but remain for backward compatibility. Think of it as a well-tested, high-mileage machine — it has seen a lot of road and carries a few traces of that history. We are transparent about this and happy to guide you through the parts that matter for your integration.

**We strongly recommend an initial technical consultation with a pressmind engineer before starting any integration.** This helps to:

- Identify the correct mapping strategy for your data
- Clarify which entities and fields are relevant
- Address potential edge cases early
- Discuss the pricing model that fits your products

pressmind offers support contracts for integration projects. If the existing data model does not fully cover your requirements, pressmind is open to extending it. For highly individual projects, a fork of the pressmind® SDK is also possible — but the mechanics and implications should always be discussed with pressmind beforehand.

---

## Table of Contents

- [Integration Flow](#integration-flow)
- [Authentication](#authentication)
- [General Response Format](#general-response-format)
- [Required Endpoints](#required-endpoints)
- [Entity Relationships](#entity-relationships)
- [ID & Code Conventions](#id--code-conventions)
- [Price Calculation](#price-calculation)
- [Entity Field Reference](#entity-field-reference)
  - [Booking Package](#booking-package)
  - [Date](#date)
  - [Transport](#transport)
  - [Housing Package](#housing-package)
  - [Option (Housing, Extra, Ticket, Sightseeing)](#option)
  - [Starting Point](#starting-point)
  - [Starting Point Option](#starting-point-option)
  - [Insurance Group](#insurance-group)
  - [Insurance](#insurance)
  - [Insurance Price Table](#insurance-price-table)
  - [Insurance Surcharge](#insurance-surcharge)
  - [Early Bird Discount Group](#early-bird-discount-group)
  - [Early Bird Discount Item](#early-bird-discount-item)
  - [Discount](#discount)
  - [Discount Scale](#discount-scale)
- [Season Matching](#season-matching)
- [Availability States](#availability-states)
- [Error Handling](#error-handling)
- [Complete Request/Response Examples](#complete-requestresponse-examples)
- [Flexibility Through Custom Import Hooks](#flexibility-through-custom-import-hooks)

---

## Integration Flow

The pressmind® SDK periodically imports product data. During import, it calls the external CRS API to fetch touristic data (prices, dates, accommodations, etc.) for each product.

```
┌────────────────────┐                      ┌────────────────────────────┐
│  pressmind® SDK    │                      │  External CRS API          │
│  (Import Hook)     │                      │  (your system)             │
│                    │                      │                            │
│  For each product: │                      │                            │
│                    │  GET /bookingpackages│                            │
│  1. Request ───────┼──?code=PROD-001 ────►│  Return booking packages   │
│     booking data   │                      │  with dates, prices,       │
│                    │◄──── JSON response ──┼  housing, extras           │
│                    │                      │                            │
│  2. Request ───────┼──GET /startingpoints/│                            │
│     starting       │  {id} ──────────────►│  Return departure points   │
│     points         │◄──── JSON response ──┼  with options, zip ranges  │
│     (if applicable)│                      │                            │
│                    │                      │                            │
│  3. Request ───────┼──GET /insurances/    │                            │
│     insurances     │  {id} ──────────────►│  Return insurance groups   │
│     (if applicable)│◄──── JSON response ──┼  with price tables         │
│                    │                      │                            │
│  4. Request ───────┼──GET /earlybird/     │                            │
│     early bird     │  {id} ──────────────►│  Return discount rules     │
│     (if applicable)│◄──── JSON response ──┼  with booking windows      │
│                    │                      │                            │
│  5. Store data     │                      │                            │
│     in database    │                      │                            │
└────────────────────┘                      └────────────────────────────┘
```

**Key points:**

- The pressmind® SDK initiates all requests. Your API is passive — it only responds.
- Booking packages are requested **per product** (identified by a product code).
- Starting points, insurances, and early bird discounts may be requested **per ID** (only those referenced in booking packages) or **globally** (all at once). This depends on the hook implementation — both patterns should be supported.
- The import runs periodically (typically every 15-60 minutes via cron) or on-demand. Individual products can also be triggered via the pressmind® SDK's REST API (`addToQueue`) using the product code or media object ID.
- The pressmind® SDK fetches your data on every import cycle, compares checksums, and only overwrites the dataset if changes are detected. Your API must always return the **complete current state** — the pressmind® SDK does not support partial/delta updates.
- **Endpoint names are examples only.** The actual URL paths (e.g. `/bookingpackages`, `/earlybird`) are entirely up to your API design — the Custom Import Hook defines which URLs are called. The same applies to **authentication** — the examples below show common methods, but any authentication scheme can be used as long as the Custom Import Hook implements it accordingly.

---

## Authentication

The pressmind® SDK supports multiple authentication methods. The method is agreed upon per integration:

### HTTP Basic Authentication (recommended)

The pressmind® SDK sends credentials via the standard `Authorization: Basic` header.

```
GET /api/bookingpackages?code=PROD-001 HTTP/1.1
Host: api.your-system.com
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=
Accept: application/json
```

### API Key Header

```
GET /api/bookingpackages?code=PROD-001 HTTP/1.1
Host: api.your-system.com
X-API-Key: your-api-key-here
Accept: application/json
```

### Bearer Token

```
GET /api/bookingpackages?code=PROD-001 HTTP/1.1
Host: api.your-system.com
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Accept: application/json
```

---

## General Response Format

All endpoints must return JSON responses with `Content-Type: application/json`.

### Success Response

```json
{
  "status": "OK",
  "data": { ... }
}
```

### Error Response

```json
{
  "status": "error",
  "msg": "Product code not found"
}
```

### Response Envelope

| Field | Type | Required | Description |
|---|---|---|---|
| `status` | string | **yes** | `"OK"` on success. `"error"`, `"warning"`, or `"info"` otherwise. |
| `data` | object/array | **yes** (on success) | The payload. Structure varies by endpoint. |
| `msg` | string | no | Human-readable message (especially for errors/warnings). |

### HTTP Status Codes

| Code | Meaning |
|---|---|
| `200` | Success — data returned |
| `400` | Bad request — invalid parameters |
| `401` | Unauthorized — authentication failed |
| `404` | Not found — product code or ID unknown |
| `500` | Server error |

The pressmind® SDK evaluates both the HTTP status code and the `status` field in the JSON body. On HTTP 4xx/5xx, the import for the affected product is skipped and the error is logged.

---

## Required Endpoints

### 1. Booking Packages

Returns all booking packages (with nested dates, housing, transports, extras) for a given product.

**Request:**

```
GET /bookingpackages?code={product_code}
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `code` | string | **yes** | The product code that identifies the product in your system. |

**Response:**

```json
{
  "status": "OK",
  "data": {
    "bookingPackages": [ ... ],
    "discounts": [ ... ]
  }
}
```

The `bookingPackages` array contains [Booking Package](#booking-package) objects with nested [Dates](#date), [Housing Packages](#housing-package), [Options](#option), and [Transports](#transport).

The optional `discounts` array contains [Discount](#discount) objects.

### 2. Starting Points (if applicable)

Returns departure locations with options and zip code ranges. Only required if your products use departure points.

**Request (per ID):**

```
GET /startingpoints/{id}
```

**Request (global):**

```
GET /startingpoints
```

> **Important:** When providing a global endpoint, only return starting points that are **currently active and in use** by marketed products. Do not return historical or deactivated records — the pressmind® SDK will import everything returned and make it available for display.

**Response:**

```json
{
  "status": "OK",
  "data": [ ... ]
}
```

The `data` array contains [Starting Point](#starting-point) objects with nested [Starting Point Options](#starting-point-option).

### 3. Insurance Groups (if applicable)

Returns insurance groups with individual insurances and price tables. Only required if your products offer travel insurances.

**Request (per ID):**

```
GET /insurances/{id}
```

**Request (global):**

```
GET /insurances
```

> **Important:** When providing a global endpoint, only return insurance groups that are **currently active and offered** for marketed products. Do not return discontinued or inactive insurance products.

**Response:**

```json
{
  "status": "OK",
  "data": [ ... ]
}
```

The `data` array contains [Insurance Group](#insurance-group) objects with nested [Insurances](#insurance) and [Price Tables](#insurance-price-table).

### 4. Early Bird Discount Groups (if applicable)

Returns early bird discount groups with items defining booking windows and discount values. Only required if your products offer early booking discounts.

**Request (per ID):**

```
GET /earlybird/{id}
```

> **Important:** Only return discount groups with **currently valid or upcoming** booking windows. Expired early bird discounts should not be included.

**Response:**

```json
{
  "status": "OK",
  "data": [ ... ]
}
```

The `data` array contains [Early Bird Discount Group](#early-bird-discount-group) objects with nested [Items](#early-bird-discount-item).

---

## Entity Relationships

The following diagram shows how entities relate to each other. Nested entities are delivered **inside** their parent object in the JSON response. Referenced entities (starting points, insurances, early birds) are delivered via **separate endpoints**.

```
Booking Package
├── dates[]                         ← nested array
│   ├── transports[]                ← nested array
│   │   └── id_starting_point       → references Starting Point (separate endpoint)
│   ├── id_starting_point            → deprecated, use Transport.id_starting_point instead
│   └── id_early_bird_discount_group → references Early Bird Group (separate endpoint)
├── housing_packages[]              ← nested array
│   └── options[]                   ← nested array (type: housing_option)
├── extras[]                        ← nested array (type: extra)
├── tickets[]                       ← nested array (type: ticket)
├── sightseeings[]                  ← nested array (type: sightseeing)
└── id_insurance_group               → references Insurance Group (separate endpoint)
```

**Nested** = delivered as part of the booking package JSON response.
**Referenced** = delivered via a separate API endpoint, linked by ID.

---

## ID & Code Conventions

### Primary Keys (`id`)

All `id` fields are strings (max 32 characters). To avoid conflicts with IDs from other sources (e.g. the pressmind® content management system or other CRS integrations), the following design recommendations apply:

- **Use a prefix** that identifies your system (e.g. `"mycrs-"`, `"tp-"`, `"acme-"`).
- **Use UUIDs** or UUID-like identifiers to guarantee uniqueness. The string type allows full UUIDs (32 hex chars without dashes fit the max length).
- IDs from your own system are acceptable, but prefixing them is strongly recommended to prevent collisions.

**Examples:**

```
"id": "mycrs-550e8400-e29b-41d4-a716"     (prefixed UUID)
"id": "mycrs-12345"                         (prefixed numeric ID)
"id": "550e8400e29b41d4a716446655440000"   (UUID without dashes, 32 chars)
```

> **Important:** The `id` must be unique per entity type and stable across import cycles. The pressmind® SDK uses it as the primary key — changing an ID will result in the old record being orphaned and a new one being created.

### Booking Code (`code_ibe`)

The `code_ibe` field serves as the **foreign key back to the CRS** for booking operations. It appears on most entities (Booking Package, Date, Housing Package, Option, Transport, Starting Point Option).

- `code_ibe` is used to construct **booking URLs** and to call **booking APIs** on the CRS side.
- It must uniquely identify the entity in the CRS so that a booking engine can reference the correct product, date, room, transport, etc.
- The value is CRS-specific and does not need to follow a pressmind® SDK convention.

### Product Code (`code`)

The `code` field represents the **published product code** — a human-readable identifier typically used in catalogs, operator systems, or marketing materials (e.g. tour operator code, catalog number).

- Unlike `code_ibe`, the `code` field is not necessarily unique across all entities.
- It is primarily used for display purposes and search/filter operations.

### Summary

| Field | Purpose | Uniqueness | Example |
|---|---|---|---|
| `id` | Internal primary key | Must be unique per entity type | `"mycrs-bp-1001"` |
| `code_ibe` | Foreign key to CRS (for booking operations) | Unique in CRS context | `"CRS-PKG-2025-MED7"` |
| `code` | Published product/catalog code | Not necessarily unique | `"MED-7D"` |

---

## Price Calculation

Understanding how prices are assembled is essential for correct data delivery. The pressmind® SDK calculates display prices by combining data from multiple entity levels.

### Price Mix (`price_mix`)

The `price_mix` field on the Booking Package determines which entity carries the **primary price** — the price used as the base for display and search:

| `price_mix` | Primary price from | Typical use case |
|---|---|---|
| `date_housing` | Housing Option (`price`) | Hotels, apartments, cruise cabins — most common |
| `date_transport` | Transport (`price`) | Flight-only, bus trips with transport pricing |
| `date_extra` | Extra Option (`price`) | Activity-based products |
| `date_ticket` | Ticket Option (`price`) | Event-based products |
| `date_sightseeing` | Sightseeing Option (`price`) | Excursion-based products |
| `date_startingpoint` | Starting Point Option (`price`) | Products priced by departure location |

### How the Cheapest Price Is Built

The pressmind® SDK runs a **CheapestPrice aggregation** after import. For each Date, it:

1. **Resolves the primary option** based on `price_mix` and season matching
2. **Pairs transports** (way 1 + way 2) if transports exist
3. **Adds starting point surcharges** if starting points are used
4. **Calculates the total price** = primary option price + transport price + starting point price
5. **Applies early bird discounts** if applicable
6. **Stores the result** as a pre-calculated `CheapestPriceSpeed` record for fast search and display

### Price Levels Overview

```
Booking Package
├── Date (departure, season, state)
│   ├── Housing Option price    ← primary price (if price_mix = date_housing)
│   │   └── price_due           ← determines how price is calculated (per person/stay/night)
│   ├── Transport price         ← added to base price (if transports exist)
│   └── Starting Point price    ← added to base price (if starting points exist)
├── Extra Options               ← secondary / add-on prices
├── Ticket Options              ← secondary / add-on prices
└── Sightseeing Options         ← secondary / add-on prices
```

> **For details on the aggregation algorithm, price due modes, and state machine:** See [CheapestPrice Aggregation](cheapest-price-aggregation.md) and [Price Selection Logic](price-selection-logic.md).

---

## Entity Field Reference

### Auto-Set Fields

The pressmind® SDK's FeedNormalizer automatically sets certain technical fields based on the nesting structure. These fields can be **omitted** from your API response:

| Field | Auto-set on |
|---|---|
| `id_media_object` | All entities |
| `id_booking_package` | Date, Housing Package, Option, Transport |
| `id_housing_package` | Option (housing_option) |
| `id_date` | Transport |
| `type` | Option (derived from array name: `extras` → `extra`, etc.) |

Fields marked as **auto** in the tables below can be omitted.

### Enum Fields and Data Integrity

Several fields accept only a defined set of values (documented as enum tables below each entity). These fields are strictly validated — providing an invalid or empty value will lead to data integrity issues, broken price calculations, or display errors in the frontend.

**All enum fields must be filled with one of the documented values.** Fields with a default value (noted in the "Default" column) can be omitted — the default will be used automatically. Fields without a default must always be provided when the entity is used.

> **When in doubt about which value to use for an enum field, consult pressmind before delivering data.** Incorrect enum values are a common source of integration issues.

---

### Booking Package

The root touristic entity. Defines a bookable offer variant with a specific duration.

> **ORM reference:** [Booking Package](Touristic/Booking/Package.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier in your system | | `"bp-1001"` |
| `id_media_object` | integer | **auto** | Set by FeedNormalizer | | |
| `name` | string | no | Display name | `null` | `"7 Days Mediterranean Cruise"` |
| `duration` | float | **yes** | Trip duration in days | | `7` |
| `order` | integer | no | Sort order | `0` | `1` |
| `url` | string | no | Booking URL (optional, see note below) | `null` | `"https://booking.example.com/med7"` |
| `text` | string | no | Description text | `null` | |
| `price_mix` | string | **yes** | Price calculation model (see below) | | `"date_housing"` |
| `ibe_type` | integer | no | IBE type identifier | `0` | `1` |
| `id_origin` | integer | no | Market origin | `0` | `0` |
| `code` | string (max 255) | no | Product code in your system | `null` | `"MED-7D-2025"` |
| `id_insurance_group` | string (max 32) | no | Reference to insurance group | `null` | `"ig-5001"` |
| `id_pickupservice` | string (max 32) | no | Reference to pickup service | `null` | |
| `product_type_ibe` | string (max 255) | no | IBE product type | `null` | `"package"` |
| `type_of_travel` | string (max 255) | no | Travel type | `null` | `"cruise"` |
| `variant_code` | string (max 255) | no | Variant identifier | `null` | `"A"` |
| `request_code` | string (max 255) | no | Booking request code | `null` | `"REQ-MED"` |
| `price_group` | string (max 255) | no | Price group identifier | `null` | |
| `product_group` | string (max 255) | no | Product group identifier | `null` | |
| `destination_airport` | string (max 255) | no | Destination airport code (for flight packages) | `null` | `"PMI"` |
| `dates` | array | **yes** | Array of [Date](#date) objects | | |
| `housing_packages` | array | no | Array of [Housing Package](#housing-package) objects | | |
| `extras` | array | no | Array of [Option](#option) objects (type `extra`) | | |
| `tickets` | array | no | Array of [Option](#option) objects (type `ticket`) | | |
| `sightseeings` | array | no | Array of [Option](#option) objects (type `sightseeing`) | | |

> **Note on `url`:** The booking URL on the Booking Package level is optional. A `url` field also exists on the [Date](#date) level, which is usually the better entry point for booking links because it can reference a specific departure date. It is also possible to work entirely without pre-defined URLs — the frontend template can construct booking URLs dynamically at runtime using `code_ibe` or other fields. Whether to use stored URLs, dynamically built URLs, or no URLs at all depends on the integration requirements and whether the integrator wants to link to an external IBE (Internet Booking Engine). Discuss this with pressmind during the integration planning.

**`price_mix` values:**

| Value | Description |
|---|---|
| `date_housing` | Price = date base price + housing option price (most common) |
| `date_extra` | Price = date base price + extra option price |
| `date_transport` | Price = date base price + transport price |
| `date_startingpoint` | Price = date base price + starting point price |
| `date_ticket` | Price = date base price + ticket price |
| `date_sightseeing` | Price = date base price + sightseeing price |

---

### Date

A departure/arrival pair within a Booking Package. Carries a `season` code for price matching.

> **ORM reference:** [Date](Touristic/Date.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"d-2001"` |
| `id_media_object` | integer | **auto** | Set by FeedNormalizer | | |
| `id_booking_package` | string (max 32) | **auto** | Set by FeedNormalizer | | |
| `departure` | date (YYYY-MM-DD) | **yes** | Departure date | | `"2025-06-14"` |
| `arrival` | date (YYYY-MM-DD) | **yes** | Return date | | `"2025-06-21"` |
| `season` | string (max 100) | **yes** | Season code for price matching | | `"A"` |
| `state` | integer | **yes** | Availability state (see [States](#availability-states)) | | `0` |
| `pax_min` | integer | no | Minimum participants | `0` | `1` |
| `pax_max` | integer | no | Maximum participants | `0` | `4` |
| `code` | string (max 255) | no | Date code in your system | `null` | `"MED-0614"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `null` | `"MED-0614"` |
| `guaranteed` | boolean | no | Guaranteed departure | `false` | `false` |
| `saved` | boolean | no | Saved/favorited | `false` | `false` |
| `flex` | boolean | no | Flexible date flag | `false` | `false` |
| `id_starting_point` | string (max 32) | **deprecated** | Do not use. Set `id_starting_point` on [Transport](#transport) instead. | `null` | |
| `id_early_bird_discount_group` | string (max 32) | no | Reference to early bird group | `null` | `"eb-9001"` |
| `id_early_payment_discount_group` | string (max 32) | no | Reference to early payment group | `null` | |
| `link_pib` | string | no | URL to product information sheet | `null` | |
| `text` | string | no | Additional text | `null` | |
| `url` | string | no | Booking URL | `null` | |
| `touroperator` | string (max 255) | no | Tour operator code | `null` | `"OP1"` |
| `agencies` | string | no | Comma-separated agency IDs | `null` | `"0,1,2"` |
| `transports` | array | no | Array of [Transport](#transport) objects | | |

---

### Transport

Transport option attached to a Date (flight, bus, train, ferry, self-drive).

> **ORM reference:** [Transport](Touristic/Transport.md)

Transports are optional — dates without transports are valid (e.g. for self-organized travel). However, if your products use **Starting Points** (departure locations), transports are required because starting points are linked via transports, not directly via dates.

Each date typically has two transports: **outbound** (`way: 1`) and **return** (`way: 2`). If no actual transport details are available but starting points need to be assigned, create **dummy transports** with minimal data:

```json
"transports": [
  {"id": "tr-dummy-out-d2001", "way": 1, "id_starting_point": "sp-7001", "price": 0, "state": 0},
  {"id": "tr-dummy-ret-d2001", "way": 2, "id_starting_point": "sp-7001", "price": 0, "state": 0}
]
```

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"tr-8001"` |
| `id_media_object` | integer | **auto** | Set by FeedNormalizer | | |
| `id_booking_package` | string (max 32) | **auto** | Set by FeedNormalizer | | |
| `id_date` | string (max 32) | **auto** | Set by FeedNormalizer | | |
| `id_starting_point` | string (max 32) | no | Reference to starting point | `null` | `"sp-7001"` |
| `id_early_bird_discount_group` | string (max 32) | no | Reference to early bird group (deprecated, prefer Date level) | `null` | |
| `id_touristic_option_discount` | string (max 32) | no | Reference to discount | `null` | |
| `type` | string (max 5) | **yes** | Transport type (see below) | | `"FLUG"` |
| `way` | integer | **yes** | Direction: 1 = outbound, 2 = return | | `1` |
| `description` | string | no | Short description | `null` | `"Flight Frankfurt - Palma"` |
| `description_long` | string | no | Detailed description | `null` | |
| `code` | string (max 255) | no | Transport/route code (IATA for flights) | `null` | `"FRAPMI"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `null` | `"FRAPMI"` |
| `price` | float | **yes** | Transport surcharge | `0` | `0` |
| `order` | integer | no | Sort order | `0` | `1` |
| `state` | integer | no | Availability state (see [States](#availability-states)) | `0` | `0` |
| `quota` | integer | no | Available seats/capacity | `null` | `50` |
| `airline` | string (max 255) | no | Airline code (for flights) | `null` | `"LH"` |
| `flight` | string | no | Flight number | `null` | `"LH1234"` |
| `transport_date_from` | datetime | no | Exact departure date+time (see note below) | `null` | `"2025-06-14T08:00:00"` |
| `transport_date_to` | datetime | no | Exact arrival date+time (see note below) | `null` | `"2025-06-14T11:30:00"` |
| `time_departure` | time (HH:MM) | no | Generic departure time without date (see note below) | `null` | `"08:00"` |
| `time_arrival` | time (HH:MM) | no | Generic arrival time without date (see note below) | `null` | `"11:30"` |
| `age_from` | integer | no | Minimum passenger age | `0` | `0` |
| `age_to` | integer | no | Maximum passenger age | `0` | `99` |
| `auto_book` | integer | no | Auto-book flag | `0` | `0` |
| `required` | integer | no | Required flag | `0` | `0` |
| `required_group` | string (max 255) | no | Required group name | `null` | |
| `transport_group` | string (max 255) | no | Group name for paired transports | `null` | `"Bus1"` |
| `request_code` | string (max 25) | no | Booking request code | `null` | |
| `seatplan_required` | boolean | no | Seat selection required | `false` | `false` |
| `dont_use_for_offers` | boolean | no | Exclude from offer generation | `false` | `false` |
| `use_earlybird` | boolean | no | Apply early bird discounts | `false` | `false` |
| `agencies` | string | no | Comma-separated agency IDs | `null` | `"0,1,2"` |
| `crs_meta_data` | string (longtext) | no | Arbitrary CRS metadata (JSON or text) | `null` | |

**`type` values:**

| Value | Description |
|---|---|
| `BUS` | Bus transport |
| `PKW` | Self-drive / car |
| `FLUG` | Flight |
| `SCHIFF` | Ship / ferry |
| `BAH` | Train / rail |

**Date/time fields — `transport_date_from/to` vs. `time_departure/arrival`:**

These two pairs of fields serve different purposes and are **not** redundant:

| Fields | Type | Use case |
|---|---|---|
| `transport_date_from` / `transport_date_to` | datetime (date + time) | Use when the **exact date and time** of the transport is known. Typical for flights and scheduled connections. Used by the pressmind® SDK for chronological sorting (e.g. stopover flights) and stored in the CheapestPrice aggregation. |
| `time_departure` / `time_arrival` | time (HH:MM only) | Use when only a **generic time** is known, independent of the travel date. Typical for bus schedules with fixed departure times (e.g. "always departs at 08:00"). |

Both can be set simultaneously, but only `transport_date_from/to` carries date context. For flights, prefer `transport_date_from/to`. For bus transports with fixed schedules, `time_departure/arrival` is usually sufficient.

---

### Housing Package

Represents an accommodation (hotel, holiday home, ship, etc.) within a Booking Package. Typically a booking package has one housing package, but multiple housing packages can be used to offer alternative accommodations to choose from (e.g. different hotels on a round trip).

> **ORM reference:** [Housing Package](Touristic/Housing/Package.md)

When there is only one accommodation, the `name` is usually the hotel or property name. It can also be left empty if the frontend does not display it.

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | `"hp-3001"` |
| `id_media_object` | integer | **auto** | Set by FeedNormalizer | |
| `id_booking_package` | string (max 32) | **auto** | Set by FeedNormalizer | |
| `name` | string | no | Accommodation name (hotel, property). Can be empty. | `"Hotel Miramar"` |
| `code` | string (max 255) | no | Housing code in your system | `"HTL-MIR"` |
| `anf_code` | string (max 255) | no | Inquiry code | `"BPK"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `"HTL-MIR"` |
| `nights` | integer | **yes** | Number of nights | `6` |
| `room_type` | string | **yes** | Type of accommodation (see below) | `"room"` |
| `min_age` | integer | no | Minimum guest age (default: 0) | `0` |
| `text` | string | no | Description text | |
| `options` | array | **yes** | Array of [Option](#option) objects (type `housing_option`) | |

**`room_type` values:**

| Value | Description |
|---|---|
| `room` | Hotel room, apartment, holiday home |
| `cabin` | Ship cabin, houseboat cabin |

---

### Option

A service or accommodation option. Used for housing options, extras, tickets, and sightseeings. The `type` field determines the category.

> **ORM reference:** [Option](Touristic/Option.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"opt-4001"` |
| `id_media_object` | integer | **auto** | Set by FeedNormalizer | | |
| `id_booking_package` | string (max 32) | **auto** | Set by FeedNormalizer | | |
| `id_housing_package` | string (max 32) | **auto** | Set by FeedNormalizer (housing options only) | `null` | |
| `id_transport` | string (max 32) | no | Reference to transport (for transport extras) | `null` | |
| `id_touristic_option_discount` | string (max 32) | no | Reference to discount | `null` | |
| `type` | string | **auto** | Option type (see below). Auto-set from array name. | | `"housing_option"` |
| `name` | string (max 255) | no | Display name | `null` | `"Double Room Sea View"` |
| `season` | string (max 100) | no | Season code for price matching | `null` | `"A"` |
| `code` | string (max 45) | no | Option code in your system | `null` | `"DBL-SEA"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `null` | `"DBL-SEA-A"` |
| `price` | float | **yes** | Price per unit (see `price_due`) | | `1299.00` |
| `price_pseudo` | float | no | Strikethrough/original price | `0` | `1499.00` |
| `price_child` | float | no | Child price | `0` | `599.00` |
| `occupancy` | integer | **yes** | Standard occupancy (number of adult persons) | | `2` |
| `occupancy_min` | integer | no | Minimum occupancy | `0` | `1` |
| `occupancy_max` | integer | no | Maximum occupancy | `0` | `3` |
| `occupancy_child` | integer | no | Number of child beds | `0` | `1` |
| `occupancy_max_age` | integer | no | Max age for child pricing | `0` | `12` |
| `quota` | integer | no | Available units/rooms | `null` | `5` |
| `available_units` | string | no | CSV list of available unit numbers | `null` | `"101,102,103"` |
| `state` | integer | no | Availability state (see [States](#availability-states)) | `0` | `0` |
| `price_due` | string | no | Price calculation basis (see below) | `"person_stay"` | `"person_stay"` |
| `board_type` | string (max 45) | no | Board type | `null` | `"half_board"` |
| `board_code` | string (max 10) | no | Board type code | `null` | `"HB"` |
| `code_ibe_board_type` | string (max 255) | no | IBE board type code | `null` | |
| `code_ibe_board_type_category` | string (max 255) | no | IBE board type category code | `null` | |
| `code_ibe_category` | string (max 255) | no | IBE category code | `null` | |
| `required` | boolean | no | Mandatory selection | `false` | `false` |
| `required_group` | string (max 255) | no | Required group name | `null` | `"transfer"` |
| `required_group_min` | integer | no | Min selections in group | `0` | `1` |
| `required_group_max` | integer | no | Max selections in group | `0` | `1` |
| `auto_book` | boolean | no | Auto-book flag | `false` | `false` |
| `booking_type` | integer | no | Booking type identifier | `0` | `0` |
| `order` | integer | no | Sort order | `0` | `1` |
| `min_pax` | integer | no | Minimum PAX for this option | `0` | `1` |
| `max_pax` | integer | no | Maximum PAX for this option | `0` | `99` |
| `age_from` | integer | no | Minimum age | `0` | `0` |
| `age_to` | integer | no | Maximum age | `0` | `99` |
| `reservation_date_from` | datetime | no | Link to specific date (alternative to season) | `null` | `"2025-06-14"` |
| `reservation_date_to` | datetime | no | Link to specific date (alternative to season) | `null` | `"2025-06-21"` |
| `description_long` | string | no | Detailed description | `null` | |
| `event` | string (max 45) | no | Event name | `null` | |
| `renewal_duration` | integer | no | Renewal duration in days | `0` | `0` |
| `renewal_price` | float | no | Renewal price | `0` | `0` |
| `use_earlybird` | boolean | no | Apply early bird discounts to this option | `false` | `false` |
| `request_code` | string (max 255) | no | Booking request code | `null` | |
| `price_group` | string (max 255) | no | Price group identifier | `null` | |
| `product_group` | string (max 255) | no | Product group identifier | `null` | |
| `currency` | string (max 11) | no | Currency code | `null` | `"EUR"` |
| `dont_use_for_offers` | boolean | no | Exclude from offer generation | `false` | `false` |
| `agencies` | string | no | Comma-separated agency IDs | `null` | `"0,1,2"` |
| `ibe_clients` | string | no | Comma-separated IBE client IDs | `null` | |
| `selection_type` | string | **deprecated** | Use `required_group_min`/`required_group_max` instead | `null` | |
| `id_media_object_option` | integer | no | Reference to a linked media object | `null` | |
| `crs_meta_data` | string (longtext) | no | Arbitrary CRS metadata (JSON or text) | `null` | |
| `deck_name` | string | no | Deck/floor name (for cruise ships) | `null` | `"Deck 5"` |
| `code_ibe_deck` | string | no | IBE deck code | `null` | `"D5"` |

**`type` values:**

| Value | Description |
|---|---|
| `housing_option` | Accommodation option (room, cabin, apartment) |
| `extra` | Optional add-on service (transfer, meal upgrade, equipment) |
| `ticket` | Entrance ticket, event access |
| `sightseeing` | Excursion, guided tour |
| `transport_extra` | Transport-related extra (seat upgrade, luggage) |
| `dummy` | Placeholder option (used in `date_transport` price mix context) |

**`price_due` values:**

Not all values are valid for all option types. Housing options and extras/tickets/sightseeings use **separate sets** of `price_due` values:

**Housing Options only** (`type: housing_option`):

| Value | Description |
|---|---|
| `person_stay` | Per person for the entire stay **(default)** |
| `stay` | Per unit/room for the entire stay (e.g. apartment rental) |
| `nights_person` | Per person per night |

**Extras, Tickets, Sightseeings only** (`type: extra`, `ticket`, `sightseeing`):

| Value | Description |
|---|---|
| `once` | One-time per person **(default for extras)** |
| `once_stay` | One-time per stay (not per person) |
| `nightly` | Per person per night |
| `daily` | Per person per day |
| `weekly` | Per person per week |

---

### Starting Point

A departure location group (region) with individual options (stations, airports).

> **ORM reference:** [Startingpoint](Touristic/Startingpoint.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"sp-7001"` |
| `name` | string (max 255) | **yes** | Region name | | `"Departure Region North"` |
| `code` | string (max 45) | no | Region code | `null` | `"REGION-NORTH"` |
| `text` | string | no | Description text | `null` | |
| `options` | array | no | Array of [Starting Point Option](#starting-point-option) objects | | |

### Starting Point Option

An individual departure point within a Starting Point group.

> **ORM reference:** [Startingpoint Option](Touristic/Startingpoint/Option.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 45) | **yes** | Unique identifier | | `"spo-001"` |
| `id_startingpoint` | string (max 32) | **auto** | Set by FeedNormalizer | | |
| `name` | string | no | Station/airport name | `null` | `"Hamburg Central Station"` |
| `code` | string (max 255) | no | Station code | `null` | `"HAM-HBF"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `null` | `"HAM-HBF"` |
| `zip` | string (max 5) | no | Zip code of the station | `null` | `"20099"` |
| `city` | string (max 255) | no | City name | `null` | `"Hamburg"` |
| `street` | string (max 255) | no | Street address | `null` | `"Hachmannplatz 16"` |
| `price` | float | no | Surcharge for this departure point | `0` | `0` |
| `base_price` | float | no | Base price (before surcharges) | `0` | `0` |
| `text` | string | no | Description text | `null` | |
| `lat` | float | no | Latitude | `null` | `53.5531` |
| `lon` | float | no | Longitude | `null` | `10.0065` |
| `start_time` | time (HH:MM) | no | Departure time | `null` | `"08:00"` |
| `with_start_time` | boolean | no | Display start time | `false` | `true` |
| `exit_time` | time (HH:MM) | no | Return arrival time | `null` | `"18:00"` |
| `with_exit_time` | boolean | no | Display exit time | `false` | `false` |
| `start_time_offset` | integer | no | Start time offset in minutes | `0` | `0` |
| `exit_time_offset` | integer | no | Exit time offset in minutes | `0` | `0` |
| `entry` | boolean | no | Available as entry/boarding point | `false` | `true` |
| `exit` | boolean | no | Available as exit/alighting point | `false` | `false` |
| `order` | integer | no | Sort order | `0` | `1` |
| `is_pickup_service` | boolean | no | Door-to-door pickup service | `false` | `false` |
| `pickup_service_street` | string | no | Pickup service street | `null` | |
| `pickup_service_house_number` | string | no | Pickup service house number | `null` | |
| `code_pickup_service_destination` | string | no | Pickup destination code | `null` | |
| `price_per_day` | boolean | no | Price is per day (not per stay) | `false` | `false` |
| `extended_price_scale` | boolean | no | Extended price scale enabled | `false` | `false` |
| `use_earlybird` | boolean | no | Apply early bird discounts | `false` | `false` |
| `rail` | string | no | Rail connection info | `null` | |
| `transportation` | string | no | Transportation type info | `null` | |
| `ibe_clients` | string (max 255) | no | Comma-separated IBE client IDs | `null` | |
| `zip_validity_area` | string | no | Zip validity area definition | `null` | |
| `zip_ranges` | array | no | Array of zip code range objects | | |

**Zip range object:**

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string | **yes** | Unique identifier | | `"zr-001"` |
| `id_option` | string | **auto** | Set by FeedNormalizer | | |
| `from` | string | **yes** | Start of zip code range | | `"20000"` |
| `to` | string | **yes** | End of zip code range | | `"22999"` |

---

### Insurance Group

A group of insurance products offered for a booking package. Referenced via `id_insurance_group` on the Booking Package.

> **ORM reference:** [Insurance Group](Touristic/Insurance.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"ig-5001"` |
| `name` | string (max 255) | no | Group name | `null` | `"Travel Protection Package"` |
| `description` | string | no | Group description | `null` | |
| `active` | boolean | no | Whether the group is active | `false` | `true` |
| `mode` | string | no | Selection mode (see below) | `null` | `"singleselection"` |
| `insurances` | array | no | Array of [Insurance](#insurance) objects | | |

**`mode` values:**

| Value | Description |
|---|---|
| `singleselection` | Customer can select only one insurance from the group |
| `multiselection` | Customer can select multiple insurances from the group |

### Insurance

An individual insurance product within an Insurance Group.

> **ORM reference:** [Insurance](Touristic/Insurance.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"ins-001"` |
| `active` | boolean | no | Whether the insurance is active | `false` | `true` |
| `name` | string (max 255) | no | Insurance name | `null` | `"Travel Cancellation Insurance"` |
| `description` | string | no | Short description | `null` | |
| `description_long` | string | no | Detailed description | `null` | |
| `code` | string (max 255) | no | Insurance product code | `null` | `"RRV"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `null` | `"RRV-2025"` |
| `worldwide` | boolean | no | Worldwide coverage | `false` | `false` |
| `is_additional_insurance` | boolean | no | Add-on insurance (selected together with a main insurance) | `false` | `false` |
| `is_alternate_insurance` | boolean | no | Alternative insurance option | `false` | `false` |
| `is_recommendation` | boolean | no | Recommended by operator | `false` | `true` |
| `priority` | integer | no | Display priority (higher = more prominent) | `0` | `10` |
| `urlinfo` | string | no | URL to general info page | `null` | |
| `urlproduktinfo` | string | no | URL to product info sheet | `null` | |
| `urlagb` | string | no | URL to terms & conditions | `null` | |
| `pax_min` | integer | no | Minimum number of travelers | `0` | `1` |
| `pax_max` | integer | no | Maximum number of travelers | `0` | `99` |
| `duration_max_days` | integer | **deprecated** | Max travel duration in days | `0` | |
| `own_contribution` | string (max 255) | no | Own contribution / deductible info | `null` | `"20%"` |
| `request_code` | string (max 255) | no | Booking request code | `null` | |
| `price_group` | string (max 255) | no | Price group identifier | `null` | |
| `product_group` | string (max 255) | no | Product group identifier | `null` | |
| `price_tables` | array | no | Array of [Insurance Price Table](#insurance-price-table) objects | | |
| `surcharges` | array | no | Array of [Insurance Surcharge](#insurance-surcharge) objects | | |
| `additional_insurances` | array | no | Array of add-on Insurance objects | | |
| `alternate_insurances` | array | no | Array of alternative Insurance objects | | |

### Insurance Price Table

Price tiers for an insurance. The pressmind® SDK matches the applicable price table entry based on travel dates, traveler age, travel price, duration, and PAX count.

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"pt-001"` |
| `code` | string (max 255) | no | Price table code | `null` | `"RRV-STD"` |
| `code_ibe` | string (max 255) | no | IBE booking code | `null` | `"RRV-STD-001"` |
| `price` | float | **yes** | Insurance price (per person or per unit, see `unit`) | | `29.00` |
| `unit` | string | **yes** | Price unit (see below) | | `"per_person"` |
| `price_type` | string (max 255) | no | Price type (e.g. `"fix"`, `"percent"`) | `null` | `"fix"` |
| `travel_price_min` | float | no | Minimum travel price for this tier | `0` | `0` |
| `travel_price_max` | float | **yes** | Maximum travel price for this tier | | `500` |
| `age_from` | integer | no | Minimum traveler age | `0` | `18` |
| `age_to` | integer | no | Maximum traveler age | `0` | `99` |
| `child_age_from` | integer | no | Minimum child age | `0` | `0` |
| `child_age_to` | integer | no | Maximum child age | `0` | `17` |
| `travel_date_from` | datetime | no | Earliest travel date | `null` | `"2025-01-01"` |
| `travel_date_to` | datetime | no | Latest travel date | `null` | `"2025-12-31"` |
| `booking_date_from` | datetime | no | Start of booking window | `null` | |
| `booking_date_to` | datetime | no | End of booking window | `null` | |
| `travel_duration_from` | integer | no | Minimum travel duration (days) | `0` | `1` |
| `travel_duration_to` | integer | no | Maximum travel duration (days) | `0` | `14` |
| `pax_min` | integer | no | Minimum travelers | `0` | `1` |
| `pax_max` | integer | no | Maximum travelers | `0` | `99` |
| `adult_pax_min` | integer | no | Minimum adult travelers | `0` | `1` |
| `adult_pax_max` | integer | no | Maximum adult travelers | `0` | `10` |
| `child_pax_min` | integer | no | Minimum child travelers | `0` | `0` |
| `child_pax_max` | integer | no | Maximum child travelers | `0` | `5` |
| `family_insurance` | boolean | no | Family insurance tariff | `false` | `false` |
| `pair_insurance` | boolean | no | Couple/pair insurance tariff | `false` | `false` |

**`unit` values:**

| Value | Description |
|---|---|
| `per_person` | Price per person |
| `per_unit` | Price per booking unit (e.g. family tariff) |

### Insurance Surcharge

Duration-based surcharges for an insurance product.

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 36) | **yes** | Unique identifier | | `"sur-001"` |
| `id_insurance` | string (max 36) | **yes** | Reference to parent insurance | | `"ins-001"` |
| `code` | string (max 255) | no | Surcharge code | `null` | |
| `duration_min` | integer | no | Minimum duration (days) | `0` | `15` |
| `duration_max` | integer | no | Maximum duration (days) | `0` | `21` |
| `unit` | string (max 255) | no | Surcharge unit | `null` | `"per_person"` |
| `value` | float | no | Surcharge amount | `0` | `15.00` |

---

### Early Bird Discount Group

A group of early booking discount rules.

> **ORM reference:** [EarlyBirdDiscountGroup](Touristic/EarlybirdDiscountGroup.md)

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | `"eb-9001"` |
| `name` | string | **yes** | Group name | `"Early Booking Discount 2025"` |
| `items` | array | no | Array of [Discount Item](#early-bird-discount-item) objects | |

### Early Bird Discount Item

An individual discount rule with booking window and travel date range.

> **ORM reference:** [EarlyBirdDiscountGroup Item](Touristic/EarlybirdDiscountGroup/Item.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"ebi-001"` |
| `id_early_bird_discount_group` | string (max 32) | **auto** | Set by FeedNormalizer | | |
| `name` | string | no | Discount name | `null` | `"10% Early Booking"` |
| `type` | string | **yes** | Discount type (see below) | | `"P"` |
| `discount_value` | float | no | Discount amount | `0` | `10.0` |
| `booking_date_from` | datetime | no | Start of booking window | `null` | `"2024-11-01"` |
| `booking_date_to` | datetime | no | End of booking window | `null` | `"2025-01-31"` |
| `travel_date_from` | datetime | no | Earliest travel date | `null` | `"2025-06-01"` |
| `travel_date_to` | datetime | no | Latest travel date | `null` | `"2025-09-30"` |
| `booking_days_before_departure` | integer | no | Min days before departure for booking | `null` | `30` |
| `min_stay_nights` | integer | no | Minimum stay nights required | `null` | `3` |
| `round` | boolean | no | Round discount result | `false` | `false` |
| `origin` | string | no | Market origin filter | `null` | |
| `agency` | string | no | Agency filter | `null` | `"AG1"` |
| `room_condition_code_ibe` | string | no | Room condition IBE code filter | `null` | |

**`type` values:**

| Value | Description |
|---|---|
| `P` | Percentage discount |
| `F` | Fixed/absolute amount |

---

### Discount

Option-level discounts (e.g. child discount, group discount) with optional scales. Referenced via `id_touristic_option_discount` on Option or Transport.

> **ORM reference:** [Discount](Touristic/Option/Discount.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"disc-001"` |
| `name` | string (max 255) | **yes** | Discount name | | `"Child Discount"` |
| `active` | boolean | **yes** | Whether the discount is active | `true` | `true` |
| `scales` | array | no | Array of [Discount Scale](#discount-scale) objects | | |

### Discount Scale

An individual scale entry within a Discount, defining conditions and discount values.

> **ORM reference:** [Discount Scale](Touristic/Option/Discount/Scale.md)

| Field | Type | Required | Description | Default | Example |
|---|---|---|---|---|---|
| `id` | string (max 32) | **yes** | Unique identifier | | `"ds-001"` |
| `id_touristic_option_discount` | string (max 32) | **yes** | Reference to parent discount | | `"disc-001"` |
| `name` | string (max 255) | **yes** | Scale name | | `"2nd child"` |
| `type` | string | **yes** | Discount type (see below) | | `"P"` |
| `value` | float | **yes** | Discount value | | `25.0` |
| `occupancy` | integer | **yes** | Occupancy position (e.g. 3 = 3rd person) | `0` | `3` |
| `pax` | integer | no | PAX count condition | `0` | `0` |
| `discounted_person` | integer | no | Which person gets the discount (position) | `0` | `3` |
| `age_from` | integer | no | Minimum age | `0` | `2` |
| `age_to` | integer | no | Maximum age | `0` | `11` |
| `valid_from` | datetime | no | Validity start date | `null` | `"2025-01-01"` |
| `valid_to` | datetime | no | Validity end date | `null` | `"2025-12-31"` |
| `frequency` | string | no | Frequency | `null` | `"E"` |

**`type` values:**

| Value | Description |
|---|---|
| `P` | Percentage discount |
| `F` | Fixed/absolute amount |
| `E` | Replacement price (sets an absolute price instead of discounting) |

---

## Season Matching

The `season` field is the primary mechanism that connects **Dates** to **Services** (Housing Options, Extras, Tickets, Sightseeings). When displaying prices for a specific travel date, the system matches the date's season code against the service's season code.

### How It Works

1. Each Date has a `season` code (e.g. `"A"`, `"B"`, `"C"`, `"Summer"`, `"Winter"`).
2. Each Option (housing, extra, etc.) also has a `season` code.
3. The system matches them to determine which prices apply to which dates.

### Matching Rules

| Service Type | Matching Rule |
|---|---|
| **Housing Options** | **Strict match** — only options whose `season` exactly equals the date's `season` are returned. |
| **Extras, Tickets, Sightseeings** | **Flexible match** — `season` values of `"-"`, `""` (empty), or `null` act as wildcards and match all dates. |

### Example

```
Date: departure 2025-06-14, season "A"
Date: departure 2025-08-16, season "B"

Housing Option: "Double Room", season "A", price 899  → matches June date only
Housing Option: "Double Room", season "B", price 1199 → matches August date only
Extra: "Spa Package", season "-", price 89            → matches both dates
Extra: "Beach Excursion", season "A", price 65         → matches June date only
```

### Alternative: Reservation Date Linking

Instead of season matching, extras/tickets/sightseeings can be linked to a specific date by setting `reservation_date_from` and `reservation_date_to` to match the date's `departure` and `arrival` values.

### Season Strategy

You are free to define your season codes. Common patterns:

| Pattern | Example | Use case |
|---|---|---|
| Letter codes | `"A"`, `"B"`, `"C"` | Simple seasonal pricing (low/mid/high season) |
| Named seasons | `"Summer"`, `"Winter"` | Readable season names |
| Date-based | `"2025-06"`, `"2025-07"` | Monthly pricing |
| Single season | `"A"` for all | No seasonal pricing variation (use with Strategy B) |

---

## Availability States

Dates, Housing Options, and Transports carry a `state` field that indicates availability. The pressmind® SDK uses this to filter which offers are displayed to end users.

| Value | Meaning | Description |
|---|---|---|
| `0` | **Available** | Open for booking |
| `1` | **On request** | Available on request only |
| `2` | **Few available** | Limited availability |
| `3` | **Fully booked** | No availability (typically excluded from search) |
| `4` | **Cancelled** | Date/option has been cancelled |
| `5` | **Closed** | Booking closed |

**Recommendation:** If your system does not track availability states, set `state` to `0` (available) as default. Set to `3` (fully booked) to exclude a date or option from display.

---

## Error Handling

> **Transaction safety:** The pressmind® SDK wraps each product's touristic import in a database transaction. If the Custom Import Hook throws an exception (e.g. due to an API error), all changes are rolled back and the existing data is preserved. Clear HTTP error codes and error responses from your API help the hook detect failures reliably. See [Transaction Safety](custom-import-hooks.md#transaction-safety-and-data-loss-prevention) for details.

### Product Not Found

When the requested product code is unknown in your system, return:

```json
{
  "status": "error",
  "msg": "Product code 'XYZ-999' not found"
}
```

HTTP status: `404`

### Product Has No Touristic Data

When the product exists but has no booking packages (e.g. content-only product), return an empty result:

```json
{
  "status": "OK",
  "data": {
    "bookingPackages": []
  }
}
```

HTTP status: `200`

### Partial Data Available

When some data is available but with warnings (e.g. some dates could not be loaded), use status `"warning"`:

```json
{
  "status": "warning",
  "msg": "3 dates could not be loaded due to upstream timeout",
  "data": {
    "bookingPackages": [ ... ]
  }
}
```

HTTP status: `200`

### Server Error

```json
{
  "status": "error",
  "msg": "Internal server error: database connection failed"
}
```

HTTP status: `500`

---

## Complete Request/Response Examples

### Example 1: Booking Packages Request

**Request:**

```
GET /api/bookingpackages?code=MED-7D-2025 HTTP/1.1
Host: api.your-system.com
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=
Accept: application/json
```

**Response:**

```json
{
  "status": "OK",
  "data": {
    "bookingPackages": [
      {
        "id": "bp-1001",
        "name": "7 Days Mediterranean Cruise",
        "duration": 7,
        "price_mix": "date_housing",
        "code": "MED-7D-2025",
        "id_insurance_group": "ig-5001",
        "dates": [
          {
            "id": "d-2001",
            "departure": "2025-06-14",
            "arrival": "2025-06-21",
            "season": "A",
            "state": 0,
            "pax_min": 1,
            "pax_max": 4,
            "code_ibe": "MED-0614",
            "id_early_bird_discount_group": "eb-9001",
            "transports": [
              {
                "id": "tr-8001",
                "type": "flight",
                "way": 1,
                "description": "Flight Frankfurt - Palma",
                "code_ibe": "FRA-PMI",
                "id_starting_point": "sp-7001",
                "price": 0,
                "state": 0
              },
              {
                "id": "tr-8002",
                "type": "flight",
                "way": 2,
                "description": "Flight Palma - Frankfurt",
                "code_ibe": "PMI-FRA",
                "id_starting_point": "sp-7001",
                "price": 0,
                "state": 0
              }
            ]
          },
          {
            "id": "d-2002",
            "departure": "2025-07-12",
            "arrival": "2025-07-19",
            "season": "B",
            "state": 0,
            "pax_min": 1,
            "pax_max": 4,
            "code_ibe": "MED-0712",
            "id_early_bird_discount_group": "eb-9001",
            "transports": [
              {
                "id": "tr-8003",
                "type": "flight",
                "way": 1,
                "description": "Flight Frankfurt - Palma",
                "code_ibe": "FRA-PMI",
                "id_starting_point": "sp-7001",
                "price": 0,
                "state": 0
              },
              {
                "id": "tr-8004",
                "type": "flight",
                "way": 2,
                "description": "Flight Palma - Frankfurt",
                "code_ibe": "PMI-FRA",
                "id_starting_point": "sp-7001",
                "price": 0,
                "state": 0
              }
            ]
          }
        ],
        "housing_packages": [
          {
            "id": "hp-3001",
            "name": "Hotel Miramar",
            "nights": 6,
            "room_type": "room",
            "code_ibe": "HTL-MIR",
            "options": [
              {
                "id": "opt-4001",
                "name": "Double Room Sea View",
                "season": "A",
                "code_ibe": "HTL-MIR-DBL-A",
                "occupancy": 2,
                "occupancy_min": 1,
                "price": 1299.00,
                "price_due": "person_stay",
                "state": 0,
                "board_type": "half_board"
              },
              {
                "id": "opt-4002",
                "name": "Double Room Sea View",
                "season": "B",
                "code_ibe": "HTL-MIR-DBL-B",
                "occupancy": 2,
                "occupancy_min": 1,
                "price": 1599.00,
                "price_due": "person_stay",
                "state": 0,
                "board_type": "half_board"
              }
            ]
          },
          {
            "id": "hp-3002",
            "name": "Hotel Bellavista",
            "nights": 6,
            "room_type": "room",
            "code_ibe": "HTL-BEL",
            "options": [
              {
                "id": "opt-4003",
                "name": "Double Room Garden View",
                "season": "A",
                "code_ibe": "HTL-BEL-DBL-A",
                "occupancy": 2,
                "occupancy_min": 1,
                "price": 1899.00,
                "price_due": "person_stay",
                "state": 0,
                "board_type": "half_board"
              },
              {
                "id": "opt-4004",
                "name": "Double Room Garden View",
                "season": "B",
                "code_ibe": "HTL-BEL-DBL-B",
                "occupancy": 2,
                "occupancy_min": 1,
                "price": 2199.00,
                "price_due": "person_stay",
                "state": 0,
                "board_type": "half_board"
              }
            ]
          }
        ],
        "extras": [
          {
            "id": "opt-6001",
            "name": "Spa Package",
            "season": "-",
            "price": 89.00,
            "price_due": "once",
            "occupancy": 1,
            "required": 0,
            "state": 0,
            "code_ibe": "SPA-PKG"
          },
          {
            "id": "opt-6002",
            "name": "Shore Excursion Athens",
            "season": "A",
            "price": 65.00,
            "price_due": "once",
            "occupancy": 1,
            "required": 0,
            "state": 0,
            "code_ibe": "EXC-ATH"
          }
        ],
        "tickets": [],
        "sightseeings": []
      }
    ],
    "discounts": [
      {
        "id": "disc-001",
        "id_booking_package": "bp-1001",
        "name": "Child Discount",
        "type": "P",
        "value": 25.0,
        "age_from": 2,
        "age_to": 11,
        "scales": []
      }
    ]
  }
}
```

### Example 2: Starting Points Request

**Request:**

```
GET /api/startingpoints/sp-7001 HTTP/1.1
Host: api.your-system.com
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=
Accept: application/json
```

**Response:**

```json
{
  "status": "OK",
  "data": [
    {
      "id": "sp-7001",
      "name": "Departure Region North",
      "code": "REGION-NORTH",
      "options": [
        {
          "id": "spo-001",
          "name": "Hamburg Central Station",
          "code": "HAM-HBF",
          "code_ibe": "HAM-HBF",
          "price": 0,
          "is_pickup_service": false,
          "zip_ranges": [
            {"id": "zr-001", "from": "20000", "to": "22999"},
            {"id": "zr-002", "from": "24000", "to": "25999"}
          ]
        },
        {
          "id": "spo-002",
          "name": "Bremen Airport",
          "code": "BRE",
          "code_ibe": "BRE",
          "price": 25.00,
          "is_pickup_service": false,
          "zip_ranges": [
            {"id": "zr-003", "from": "28000", "to": "28999"}
          ]
        }
      ]
    }
  ]
}
```

### Example 3: Insurance Group Request

**Request:**

```
GET /api/insurances/ig-5001 HTTP/1.1
Host: api.your-system.com
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=
Accept: application/json
```

**Response:**

```json
{
  "status": "OK",
  "data": [
    {
      "id": "ig-5001",
      "name": "Travel Protection Package",
      "insurances": [
        {
          "id": "ins-001",
          "name": "Travel Cancellation Insurance",
          "code": "RRV",
          "description": "Covers cancellation costs up to the full travel price.",
          "price_tables": [
            {"id": "pt-001", "price_from": 0, "price_to": 500, "price": 29.00},
            {"id": "pt-002", "price_from": 501, "price_to": 1000, "price": 49.00},
            {"id": "pt-003", "price_from": 1001, "price_to": 2000, "price": 79.00}
          ]
        },
        {
          "id": "ins-002",
          "name": "Travel Health Insurance",
          "code": "AKV",
          "description": "Medical coverage abroad.",
          "price_tables": [
            {"id": "pt-004", "price_from": 0, "price_to": 99999, "price": 12.50}
          ]
        }
      ]
    }
  ]
}
```

### Example 4: Early Bird Discount Request

**Request:**

```
GET /api/earlybird/eb-9001 HTTP/1.1
Host: api.your-system.com
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=
Accept: application/json
```

**Response:**

```json
{
  "status": "OK",
  "data": [
    {
      "id": "eb-9001",
      "name": "Early Booking Discount 2025",
      "items": [
        {
          "id": "ebi-001",
          "booking_date_from": "2024-11-01",
          "booking_date_to": "2025-01-31",
          "travel_date_from": "2025-06-01",
          "travel_date_to": "2025-09-30",
          "discount_value": 10.0,
          "type": "P",
          "name": "10% Early Booking"
        },
        {
          "id": "ebi-002",
          "booking_date_from": "2025-02-01",
          "booking_date_to": "2025-03-31",
          "travel_date_from": "2025-06-01",
          "travel_date_to": "2025-09-30",
          "discount_value": 5.0,
          "type": "P",
          "name": "5% Early Booking"
        }
      ]
    }
  ]
}
```

---

## Flexibility Through Custom Import Hooks

The API specification in this document describes the **recommended best practice** for delivering data to the pressmind® SDK. We strongly recommend staying as close to this specification as possible.

However, between your API and the pressmind® SDK's data model sits the **Custom Import Hook** — a PHP layer that processes and transforms your API responses before they enter the database. This layer is system-specific and can be customized per integration, which provides significant flexibility:

- **Data transformation:** If your API response structure differs from the expected format, the hook can reshape, merge, or split data before import.
- **Custom retrieval logic:** The order in which endpoints are called, whether data is fetched in bulk or per-product, and how responses are consolidated — all of this is controlled by the hook.
- **Error handling:** Additional validation, logging, or custom error reporting can be implemented within the hook.
- **Data enrichment:** Data from multiple API calls or sources can be combined before being passed to the pressmind® SDK.

The Booking Package structure with its nested entities (Dates, Housing Packages, Options, Transports) must follow the schema defined in this document — this is the contract with the pressmind® SDK's ORM. But everything around it — how data is fetched, validated, transformed, and assembled — is flexible and can be tailored to the specific integration scenario.

> **For hook developers:** See [Custom Import Hooks](custom-import-hooks.md) for the pressmind® SDK-side implementation guide.
