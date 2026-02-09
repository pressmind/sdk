# Touristic: Startingpoint Option

[← Back to Startingpoint](../Startingpoint.md) | [← Back to Documentation](../../documentation.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Startingpoint\Option`

---

## Description

An individual boarding point (Zustieg) within a Startingpoint group. Contains the physical location, departure time, pricing, and pickup service configuration.

Boarding points can be marked as **entry** (Einstieg) or **exit** (Ausstieg). In most systems, entry and exit are the same location. Exit-only points are defined but rarely used in current implementations.

**Special rule:** If both `entry = false` and `exit = false`, the point is treated as an entry.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Primary key |
| `id_startingpoint` | string | yes | Reference to the parent Startingpoint group |
| `zip` | string | no | Postal code |
| `code` | string | no | External code |
| `name` | string | no | Display name, e.g. "Hamburg ZOB" |
| `price` | float | no | Price per participant (also see `price_per_day`) |
| `base_price` | float | no | Base price per trip (fixed cost regardless of participants) |
| `text` | string | no | Description |
| `start_time` | time | no | Departure time (local time) |
| `with_start_time` | boolean | no | `true` if `start_time` should be displayed |
| `city` | string | no | City name with district |
| `street` | string | no | Street address |
| `lat` | float | no | Latitude coordinate |
| `lon` | float | no | Longitude coordinate |
| `entry` | boolean | no | `true` = boarding point. If both entry and exit are `false`, treated as entry |
| `exit` | boolean | no | `true` = alighting point |
| `exit_time` | time | no | Alighting time (local time) |
| `exit_time_offset` | integer | no | Day offset if exit is not on the same day as departure |
| `start_time_offset` | integer | no | Day offset if boarding is not on the same day as departure |
| `with_exit_time` | boolean | no | `true` if `exit_time` should be displayed |
| `code_ibe` | string | no | CRS booking code |
| `ibe_clients` | string | no | Comma-separated list of allowed CRS clients |
| `is_pickup_service` | boolean | no | `true` for door-to-door pickup services |
| `zip_ranges` | ZipRange[] | no | For pickup services: prices per postal code area. Pickup is only available for listed codes |
| `rail` | string | no | Platform/track number for train departures |
| `transportation` | string | no | Extended transport description, e.g. "Taxi", "Shuttle bus", "Feeder service" |
| `order` | integer | no | Sort order |
| `price_per_day` | boolean | no | If `true`, the price is charged per day (e.g. for parking at boarding point) |
| `pickup_service_street` | string | no | Pickup address (street) filled during booking |
| `pickup_service_house_number` | string | no | Pickup address (house number) filled during booking |
| `code_pickup_service_destination` | string | no | Destination code for pickup services |
| `use_earlybird` | boolean | no | If `true`, Early Bird discounts may be applied to this startingpoint option |

### Relations

| Relation | Type | Description |
|---|---|---|
| `zip_ranges` | ZipRange[] | Postal code-based pricing for pickup services |

### Deprecated Properties

| Property | Note |
|---|---|
| `zip_validity_area` | Use `zip_ranges` instead |
| `extended_price_scale` | Rarely used, possibly deprecated |

---

## Pickup Service

When `is_pickup_service = true`, the boarding point represents a door-to-door transfer:

1. The `zip_ranges` table defines which postal codes are serviced and their prices
2. During booking, the customer provides their address (`pickup_service_street`, `pickup_service_house_number`)
3. The pickup is only available for postal codes listed in `zip_ranges`

---

## Related Documentation

- [Startingpoint](../Startingpoint.md) – Parent group containing this option
- [Transport](../Transport.md) – Transport entity referencing startingpoint groups
