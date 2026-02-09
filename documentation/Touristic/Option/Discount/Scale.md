# Touristic: Discount Scale

[← Back to Discount](../Discount.md) | [← Back to Documentation](../../../documentation.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Option\Discount\Scale`

---

## Description

Defines a single discount rule for controlling age-based and occupancy-based pricing. Commonly used for child discounts, youth discounts, and additional person pricing.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Primary key |
| `id_touristic_option_discount` | string | yes | Reference to the parent Discount |
| `name` | string | yes | Display name, e.g. "Kind 2-12 Jahre" |
| `type` | string | yes | Discount type. See [Type Enum](#type) |
| `value` | float | yes | Discount value (interpretation depends on `type`) |
| `occupancy` | integer | yes | Room occupancy, e.g. `3` (3 persons in room) |
| `pax` | integer | no | Number of full-paying guests, e.g. `2` |
| `discounted_person` | integer | no | Position of discounted person, e.g. `1` = first discounted person after full payers |
| `age_from` | integer | no | Minimum age (age at departure applies) |
| `age_to` | integer | no | Maximum age |
| `valid_from` | DateTime | no | Validity period start |
| `valid_to` | DateTime | no | Validity period end |
| `frequency` | string | no | Application frequency. See [Frequency Enum](#frequency) |

---

## Type

| Value | Description | Calculation |
|---|---|---|
| `P` | Percentage discount | `price × (1 - value/100)` |
| `E` | Fixed amount discount (EUR) | `price - value` |
| `F` | Fixed price (replaces original) | `value` (original price is ignored) |

---

## Frequency

| Value | Description |
|---|---|
| `E` | One-time (einmalig) |

> **Note:** The ORM validator currently only accepts `E` (one-time). The values `M` (per night) and `T` (per day) may be used by the PIM but are not yet validated in the SDK code.

---

## Example: Child Discount Scale

```
Discount (name: "Kinderermäßigung")
 ├── Scale: occupancy=3, pax=2, discounted_person=1
 │          age 2-5, type=F, value=0      → Child free
 ├── Scale: occupancy=3, pax=2, discounted_person=1
 │          age 6-11, type=P, value=30    → 30% off adult price
 └── Scale: occupancy=3, pax=2, discounted_person=1
            age 12-17, type=P, value=15   → 15% off adult price
```

Reading this: In a room with 3 persons where 2 are full payers, the 1st discounted person (child) gets:
- Free if aged 2-5
- 30% off if aged 6-11
- 15% off if aged 12-17

---

## Related Documentation

- [Discount](../Discount.md) – Parent discount group
- [Option](../../Option.md) – Options referencing discounts
- [CheapestPrice Aggregation](../../../cheapest-price-aggregation.md) – How discount scales affect price calculation
