# Touristic: EarlyBirdDiscountGroup

[← Back to Documentation](../documentation.md) | [→ Discount Items](EarlybirdDiscountGroup/Item.md) | [→ Date](Date.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup`

---

## Description

Groups Early Bird discount items into a named schedule. Typically named "Frühbucher" (Early Bird), but also used for other time-based discounts like Black Friday promotions or last-minute offers.

An EarlyBirdDiscountGroup is referenced by Dates and Transports. When the discount conditions match (booking date + travel date within the defined ranges), the discount is applied during the [CheapestPrice Aggregation](../cheapest-price-aggregation.md).

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string(32) | yes | Primary key |
| `name` | string(255) | yes | Display name, e.g. "Frühbucher", "Black Friday", "Last Minute" |
| `import_code` | string(255) | no | External import code for matching during data imports |

### Relations

| Relation | Type | Description |
|---|---|---|
| `items` | Item[] | Individual discount schedule entries |

---

## Related Documentation

- [Discount Items](EarlybirdDiscountGroup/Item.md) – Individual discount entries with date ranges and values
- [Date](Date.md) – Dates referencing this discount group
- [Transport](Transport.md) – Transports referencing this discount group
- [CheapestPrice Aggregation](../cheapest-price-aggregation.md) – How early bird discounts affect price calculation
