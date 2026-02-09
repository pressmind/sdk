# Touristic: EarlyBirdDiscountGroup Item

[← Back to EarlyBirdDiscountGroup](../EarlybirdDiscountGroup.md) | [← Back to Documentation](../../documentation.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item`

---

## Description

Defines a single Early Bird discount entry based on **travel date range** and **booking date range**.

The discount is applied when **both** conditions are met:
1. The departure date (`Date.departure`) falls within `travel_date_from` – `travel_date_to`
2. The current booking date falls within `booking_date_from` – `booking_date_to` **or** is within `booking_days_before_departure` days before departure

Multiple items can exist within one group, allowing for tiered discounts (e.g. 15% if booked 6 months early, 10% if booked 3 months early).

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string(32) | yes | Primary key |
| `id_early_bird_discount_group` | string(32) | yes | Reference to the parent EarlyBirdDiscountGroup |
| `travel_date_from` | datetime | no | Travel date range start |
| `travel_date_to` | datetime | no | Travel date range end |
| `booking_date_from` | datetime | no | Booking date range start |
| `booking_date_to` | datetime | no | Booking date range end |
| `booking_days_before_departure` | integer | no | Alternative to fixed booking dates: discount applies if booked N days before departure |
| `min_stay_nights` | integer | no | Minimum number of nights for this discount to apply |
| `discount_value` | float | no | Discount value. Unit depends on `type` |
| `type` | string | yes | `P` = percentage discount, `F` = fixed amount discount (EUR) |
| `round` | boolean | no | If `true`, the calculated discount is rounded to whole values |
| `name` | string | no | Display name for this specific discount tier |
| `origin` | string | no | Restrict discount to a specific touristic origin |
| `agency` | string | no | Restrict discount to a specific agency |
| `room_condition_code_ibe` | string | no | CRS code for room condition linked to this discount |

---

## Discount Calculation

### Percentage Discount (`type = P`)

```
discounted_price = original_price × (1 - discount_value / 100)
```

Example: `discount_value = 15` → 15% off

### Fixed Amount Discount (`type = F`)

```
discounted_price = original_price - discount_value
```

Example: `discount_value = 50` → 50 EUR off

### Rounding

When `round = true`:
```
discounted_price = ceil(discounted_price)  // round up to nearest integer
```

---

## Matching Logic

The discount item matches if **all** applicable conditions are met:

1. **Travel date range** (if set): `Date.departure` is between `travel_date_from` and `travel_date_to`
2. **Booking date range** (if set): current date is between `booking_date_from` and `booking_date_to`
3. **Days before departure** (if set): current date is within `booking_days_before_departure` days before `Date.departure`
4. **Minimum stay** (if set): trip duration ≥ `min_stay_nights`
5. **Origin** (if set): matches the touristic origin
6. **Agency** (if set): matches the booking agency

---

## Example: Tiered Early Bird Schedule

```
EarlyBirdDiscountGroup (name: "Frühbucher 2025")
 ├── Item: travel 2025-04-01 to 2025-10-31, book by 2024-12-31 → 15% off
 ├── Item: travel 2025-04-01 to 2025-10-31, book by 2025-02-28 → 10% off
 └── Item: travel 2025-04-01 to 2025-10-31, book by 2025-03-31 →  5% off
```

The CheapestPrice Aggregation always uses the **best applicable discount** at the time of calculation.

---

## Related Documentation

- [EarlyBirdDiscountGroup](../EarlybirdDiscountGroup.md) – Parent group
- [CheapestPrice Aggregation](../../cheapest-price-aggregation.md) – How early bird discounts are applied in the price pipeline
