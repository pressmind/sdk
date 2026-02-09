# Touristic: Option Discount

[← Back to Option](../Option.md) | [→ Discount Scale](Discount/Scale.md) | [← Back to Documentation](../../documentation.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Option\Discount`

---

## Description

Groups discount scales for an Option. Discounts are typically used for **age-based pricing** (child discounts, senior discounts) and **occupancy-based pricing** (3rd/4th person in room).

Each Discount contains one or more Scales that define the specific discount rules.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Primary key |
| `name` | string | yes | Display name, e.g. "Kinderermäßigung" (child discount) |
| `active` | boolean | yes | Whether this discount is currently active |

### Relations

| Relation | Type | Description |
|---|---|---|
| `scales` | Scale[] | Individual discount rules |

---

## Related Documentation

- [Discount Scale](Discount/Scale.md) – Individual discount rules with age, occupancy, and value
- [Option](../Option.md) – Options referencing this discount
- [CheapestPrice Aggregation](../../cheapest-price-aggregation.md) – How discounts affect the price pipeline
