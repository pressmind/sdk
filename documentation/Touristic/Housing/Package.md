# Touristic: Housing Package

[← Back to Documentation](../../documentation.md) | [→ Option](../Option.md) | [→ Booking Package](../Booking/Package.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Housing\Package`

---

## Description

A Housing Package groups **bookable accommodation options** (rooms or cabins) within a Booking Package. The customer selects a Housing Package and then chooses one accommodation option from it.

Housing Packages are only displayed separately in the frontend when multiple packages exist (e.g. different hotels on the same trip).

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string(32) | yes | Primary key |
| `id_media_object` | integer | yes | Reference to the parent MediaObject |
| `id_booking_package` | string(32) | yes | Reference to the parent Booking Package |
| `name` | string | no | Display name, e.g. "Hotel Kempinski ****" |
| `code` | string(255) | no | External code |
| `nights` | integer | yes | Number of nights |
| `text` | string | no | Description |
| `code_ibe` | string(255) | no | CRS booking code |
| `room_type` | enum | yes | `room` or `cabin`. Controls frontend labeling: "Zimmer" vs. "Kabinen" |
| `min_age` | integer | no | Minimum age for this accommodation package |

### Relations

| Relation | Type | Description |
|---|---|---|
| `options` | Option[] | Bookable accommodation options (type=housing_option) |
| `description_links` | DescriptionLink[] | Linked content descriptions |

### Deprecated Properties

| Property | Note |
|---|---|
| `anf_code` | Stadis export only |

---

## Room Type

| Value | Frontend Label (DE) | Use Case |
|---|---|---|
| `room` | Zimmer | Hotels, holiday apartments |
| `cabin` | Kabine | Cruise ships, ferries |

---

## Example Structure

```
Booking Package (duration: 7 days)
 ├── Housing Package (name: "Hotel Seaside ***", room_type: room)
 │    ├── Option (name: "Doppelzimmer", occupancy: 2, price: 899.00)
 │    ├── Option (name: "Einzelzimmer", occupancy: 1, price: 1099.00)
 │    └── Option (name: "Suite", occupancy: 2, price: 1499.00)
 └── Housing Package (name: "Hotel Grand ****", room_type: room)
      ├── Option (name: "Doppelzimmer", occupancy: 2, price: 1199.00)
      └── Option (name: "Junior Suite", occupancy: 2, price: 1799.00)
```

---

## Related Documentation

- [Option](../Option.md) – Accommodation options within this package (type=housing_option)
- [Booking Package](../Booking/Package.md) – Parent entity
- [CheapestPrice Aggregation](../../cheapest-price-aggregation.md) – How housing option prices feed into the base price
