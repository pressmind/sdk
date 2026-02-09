# Touristic: Date

[← Back to Documentation](../documentation.md) | [→ Transport](Transport.md) | [→ Booking Package](Booking/Package.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Date`

---

## Description

Defines the departure and return dates for a touristic product. Each Date belongs to a Booking Package and connects to Transports (for outbound/return travel) and Early Bird Discount Groups.

The `state` field indicates a **display status** — it does **not** define logistical availability. True availability would be derived from transport quotas, accommodation capacity, and mandatory service availability. However, in smaller setups the state is commonly used as a booking availability indicator.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string(32) | yes | Primary key |
| `id_media_object` | integer | yes | Reference to the parent MediaObject |
| `id_booking_package` | string(32) | yes | Reference to the parent Booking Package |
| `code` | string(255) | no | External code |
| `departure` | date | yes | Departure date |
| `arrival` | date | yes | Return date |
| `text` | string | no | Info text for this date, e.g. "Easter" or "Whitsun" |
| `season` | string(100) | yes | Season code / price group. Links services (Options) to this date. Free alphanumeric value, often A-Z |
| `url` | string | no | Booking engine URL initialized at date level |
| `state` | integer | yes | Display status. See [State Enum](#state) |
| `code_ibe` | string(255) | no | CRS booking code |
| `id_early_bird_discount_group` | string(32) | no | Reference to the Early Bird Discount Group |
| `guaranteed` | boolean | no | Marks this departure as guaranteed to operate |
| `saved` | boolean | no | Marks this departure as secured |
| `flex` | boolean | no | Marks this departure as flexibly cancellable |
| `touroperator` | string(255) | no | Operating tour operator (for cooperation products) |
| `agencies` | string | no | Comma-separated list of allowed agencies |
| `id_early_payment_discount_group` | string(32) | no | Reference to the Early Payment Discount Group |
| `pax_min` | integer | no | Minimum participants (informational only, rarely used) |
| `pax_max` | integer | no | Maximum participants (informational only, rarely used) |

### Relations

| Relation | Type | Description |
|---|---|---|
| `transports` | Transport[] | Outbound and return transports for this date |
| `early_bird_discount_group` | EarlyBirdDiscountGroup | Early bird discount schedule |
| `early_payment_discount_group` | EarlyPaymentDiscountGroup | Early payment discount schedule |
| `attributes` | Attribute[] | Key/value attributes |

### Deprecated Properties

| Property | Note |
|---|---|
| `id_starting_point` | Startingpoints are now assigned via Transport |
| `startingpoint` | Use Transport.starting_points instead |
| `link_pib` | No longer used |

---

## State

The Date state controls visibility and booking behavior in the frontend and affects the [CheapestPrice Aggregation](../cheapest-price-aggregation.md).

| Value | Key | Description |
|---|---|---|
| `0` | No status | Treated as bookable in most implementations |
| `1` | Bookable | Confirmed available |
| `2` | On request | Available on request only |
| `3` | Blocked | Not bookable, hidden from search |
| `4` | Few remaining | Limited availability |
| `5` | Sold out | Fully booked |

> **Production Insight:** The `date_filter.allowed_states` setting in `config.json` controls which states are included in the CheapestPrice calculation. **All ~40 production installations** use `[0, 1, 2, 4, 5]` — state `3` (Blocked) is always excluded. See [Configuration: Touristic Data](../config-touristic-data.md).

---

## Season Matching

The `season` field is the key mechanism that connects Dates to Options (services):

```
Date.season = "A"  ←→  Option.season = "A"
```

An Option with `season = "A"` applies to all Dates with `season = "A"`. This allows one set of room prices to cover multiple departure dates within the same season.

If an Option has `reservation_date_from` and `reservation_date_to` set, these date ranges take **priority** over season matching.

---

## Duration Calculation

The travel duration is derived from the Date's departure and arrival:

```
duration_nights = arrival - departure  (in days)
```

This duration is used in price calculations for `price_due` modes like `nights_person` and `nightly`.

---

## Related Documentation

- [Booking Package](Booking/Package.md) – Parent entity containing this Date
- [Transport](Transport.md) – Transport pairs assigned to this Date
- [EarlyBirdDiscountGroup](EarlybirdDiscountGroup.md) – Discount schedules
- [CheapestPrice Aggregation](../cheapest-price-aggregation.md) – How Date states affect price calculation
- [Configuration: date_filter](../config-touristic-data.md) – Which Date states are included in imports
