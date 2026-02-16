# Touristic: Insurance

[← Back to Documentation](../../documentation.md) | [→ Booking Package](Booking/Package.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Insurance` (and sub-namespaces)

---

## Description

Insurance data is imported from the pressmind PIM and stored per **Insurance Group**. A Media Object or Booking Package can reference one insurance group (`id_insurance_group`). Each group contains one or more **Insurances** (main products), which can have **price tables**, **attributes** (coverage items, see [Insurance Attribute](#insurance-attribute)), **sub-insurances** (additional insurances), and **alternate insurances** (alternative products to choose from).

---

## Entity Relationship

```
Insurance Group (pmt2core_touristic_insurance_groups)
 └── insurances (n:n via pmt2core_touristic_insurance_to_group)
      └── Insurance (pmt2core_touristic_insurances)
           ├── price_tables (n:n via pmt2core_touristic_insurance_to_price_table)
           ├── attributes (n:n via pmt2core_touristic_insurance_to_attributes) → Insurance Attribute
           ├── sub_insurances (n:n via pmt2core_touristic_insurance_to_insurance) — additional insurances
           └── alternate_insurances (n:n via pmt2core_touristic_insurance_to_alternate) — alternate products (with order)

Insurance Attribute (pmt2core_touristic_insurance_attributes)
 └── Referenced by many insurances via pmt2core_touristic_insurance_to_attributes (id_attribute)
```

---

## Insurance Group

**Table:** `pmt2core_touristic_insurance_groups`  
**Class:** `Pressmind\ORM\Object\Touristic\Insurance\Group`

| Property   | Type    | Description     |
|-----------|---------|-----------------|
| `id`      | string(32) | Primary key  |
| `name`    | string(255) | Group name   |
| `description` | string | Optional description |
| `active`  | boolean | Whether the group is active |
| `insurances` | Insurance[] | Related insurances (ManyToMany) |

---

## Insurance

**Table:** `pmt2core_touristic_insurances`  
**Class:** `Pressmind\ORM\Object\Touristic\Insurance`

| Property | Type | Description |
|----------|------|-------------|
| `id` | string(32) | Primary key |
| `active` | boolean | Active flag |
| `name` | string(255) | Display name |
| `description` | string | Short description |
| `description_long` | string | Long description |
| `code` | string(255) | Internal code |
| `code_ibe` | string(255) | CRS/IBE code |
| `request_code` | string(255) | Request code for CRS |
| `worldwide` | boolean | Worldwide coverage |
| `is_additional_insurance` | boolean | True if this is an additional (sub-) insurance |
| `is_alternate_insurance` | boolean | True if this is an alternate option to a main insurance |
| `urlinfo` | string | URL to info page |
| `urlproduktinfo` | string | URL to product info |
| `urlagb` | string | URL to terms and conditions |
| `own_contribution` | string | Own contribution text |
| `pax_min` / `pax_max` | integer | Passenger count limits |
| `duration_max_days` | integer | Deprecated |
| `price_group` / `product_group` | string | Grouping for pricing/CRS |

### Relations

| Relation | Type | Table | Description |
|----------|------|-------|-------------|
| `price_tables` | PriceTable[] | pmt2core_touristic_insurance_to_price_table | Price tables (age/date/price ranges) |
| `attributes` | Attribute[] | pmt2core_touristic_insurance_to_attributes | Insurance attributes (coverage details) |
| `sub_insurances` | Insurance[] | pmt2core_touristic_insurance_to_insurance | Additional insurances (e.g. top-up) |
| `alternate_insurances` | Insurance[] | pmt2core_touristic_insurance_to_alternate | Alternate products (with order) |

---

## Mapping Tables

### InsuranceToGroup

**Table:** `pmt2core_touristic_insurance_to_group`  
Links insurance groups to insurances (ManyToMany).  
Fields: `id_insurance_group`, `id_insurance`.

### InsuranceToPriceTable

**Table:** `pmt2core_touristic_insurance_to_price_table`  
Links insurances to price tables.  
Fields: `id_insurance`, `id_price_table`.

### InsuranceToInsurance (sub-insurances)

**Table:** `pmt2core_touristic_insurance_to_insurance`  
Links a main insurance to its additional (sub-) insurances.  
Fields: `id`, `id_insurance`, `id_sub_insurance`.

### InsuranceToAttribute

**Table:** `pmt2core_touristic_insurance_to_attributes`  
**Class:** `Pressmind\ORM\Object\Touristic\Insurance\InsuranceToAttribute`

Links insurances to attributes (coverage items). Composite primary key.

| Property | Type | Description |
|----------|------|-------------|
| `id_insurance` | string(32) | Insurance ID |
| `id_attribute` | string(32) | Attribute ID |

The API may send an `order` field per assignment; it is not stored in this table (it is stripped during import). Sort order for displaying attributes is determined by the Attribute’s own `order` field.

---

## Insurance Attribute

**Table:** `pmt2core_touristic_insurance_attributes`  
**Class:** `Pressmind\ORM\Object\Touristic\Insurance\Attribute`

Attributes are reusable coverage items (e.g. “Reiseabbruch”, “Gepäck”, “Reisekranken”) that can be assigned to multiple insurances. They are used to build coverage lists (included/excluded items) in the frontend.

| Property | Type | Description |
|----------|------|-------------|
| `id` | string(32) | Primary key |
| `name` | string | Display name (e.g. “Reiseabbruchversicherung”) |
| `description` | string | Optional description text |
| `code` | string | Internal code (e.g. “ABBRUCH”, “GEPAECK”) |
| `code_ibe` | string | CRS/IBE code for mapping |
| `order` | integer | Sort order when displaying the attribute list |

An insurance is linked to attributes via the mapping table `pmt2core_touristic_insurance_to_attributes`. Only attributes that are linked to an insurance are shown for that product; the Attribute’s `order` is typically used to sort the list (e.g. in checklists or comparison views). Orphaned attributes (not referenced by any insurance) are removed during import by `_removeInsuranceOrphans()`.

---

### InsuranceToAlternate

**Table:** `pmt2core_touristic_insurance_to_alternate`  
Links a main insurance to its alternate insurances (alternative products the customer can choose from). The `order` field defines the display order.

| Property | Type | Description |
|----------|------|-------------|
| `id` | integer | Primary key (auto-increment) |
| `id_insurance` | string(32) | Main insurance ID |
| `id_alternate_insurance` | string(32) | Alternate insurance ID |
| `order` | integer | Sort order for alternates |

---

## Price Table

**Table:** `pmt2core_touristic_insurances_price_tables`  
**Class:** `Pressmind\ORM\Object\Touristic\Insurance\PriceTable`

Defines price and validity rules (age range, travel duration, travel price range, booking date range, etc.). Used by the SDK to determine the applicable price for a given booking context.

---

## Import

Insurance data is imported in two ways:

1. **From `insurance_group` on the media object** (alternative booking tab in PIM): When the API response contains `response[0]->insurance_group`, the SDK maps it to touristic fields and calls `TouristicData::import()`. This includes:
   - `insurance_groups`, `insurance_to_group`, `insurances`, `insurances_to_price_table`, `insurances_price_tables`, `insurance_to_attribute`, `insurance_attributes`, and **`alternate_insurance_to_insurance`** (→ `touristic_insurance_to_alternate`).

2. **From `touristic` on the media object**: The same `TouristicData::import()` is used for `response[0]->touristic`, which can also contain `touristic_insurance_to_alternate` (and other insurance-related arrays).

Orphaned entries in mapping tables (including `pmt2core_touristic_insurance_to_alternate`) are removed during import by `_removeInsuranceOrphans()`.

---

## Related Documentation

- [Booking Package](Booking/Package.md) – `id_insurance_group` reference
- [Import Process](../../import-process.md) – Phase 3.2 touristic data import
