# Touristic: Insurance

[← Back to Documentation](../../documentation.md) | [→ Booking Package](Booking/Package.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Insurance` (and sub-namespaces)

---

## Description

Insurance data is imported from the pressmind PIM and stored per **Insurance Group**. A Media Object or Booking Package can reference one insurance group (`id_insurance_group`). Each group contains one or more **Insurances** (main products), which can have **price tables**, **attributes** (coverage items, see [Insurance Attribute](#insurance-attribute)), **additional insurances**, and **alternate insurances** (alternative products to choose from).

---

## Entity Relationship

```
Insurance Group (pmt2core_touristic_insurance_groups)
 └── insurances (n:n via pmt2core_touristic_insurance_to_group)
      └── Insurance (pmt2core_touristic_insurances)
           ├── price_tables (n:n via pmt2core_touristic_insurance_to_price_table)
           ├── attributes (n:n via pmt2core_touristic_insurance_to_attributes) → Insurance Attribute
           ├── additional_insurances (n:n via pmt2core_touristic_insurance_to_insurance) — additional insurances
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
| `mode`    | string\|null | Selection mode: `single_selection` or `multi_selection`; `null` or empty for legacy data |
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
| `is_recommendation` | boolean | Recommendation flag from API |
| `priority` | integer | Display/sort priority from API |

### Relations

| Relation | Type | Table | Description |
|----------|------|-------|-------------|
| `price_tables` | PriceTable[] | pmt2core_touristic_insurance_to_price_table | Price tables (age/date/price ranges) |
| `attributes` | Attribute[] | pmt2core_touristic_insurance_to_attributes | Insurance attributes (coverage details) |
| `additional_insurances` | Insurance[] | pmt2core_touristic_insurance_to_insurance | Additional insurances (e.g. top-up) |
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

### InsuranceToInsurance (additional insurances)

**Table:** `pmt2core_touristic_insurance_to_insurance`  
Links a main insurance to its additional insurances.  
Fields: `id`, `id_insurance`, `id_additional_insurance`, `order`.

### InsuranceToAttribute

**Table:** `pmt2core_touristic_insurance_to_attributes`  
**Class:** `Pressmind\ORM\Object\Touristic\Insurance\InsuranceToAttribute`

Links insurances to attributes (coverage items). Composite primary key.

| Property | Type | Description |
|----------|------|-------------|
| `id_insurance` | string(32) | Insurance ID |
| `id_attribute` | string(32) | Attribute ID |
| `order` | integer | Sort order for this attribute within the insurance (imported from API). |

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
   - `insurance_groups`, `insurance_to_group`, `insurances`, `insurances_to_price_table`, `insurances_price_tables`, `insurance_to_attribute`, `insurance_attributes`, **`alternate_insurance_to_insurance`** (→ `touristic_insurance_to_alternate`), and **`additional_insurance_to_insurance`** (→ `touristic_insurance_to_insurance`).

2. **From `touristic` on the media object**: The same `TouristicData::import()` is used for `response[0]->touristic`, which can contain the same insurance-related arrays (e.g. `touristic_insurances`, `touristic_insurance_to_alternate`, `touristic_insurance_to_insurance`, and other touristic_* keys).

Orphaned entries in mapping tables (including `pmt2core_touristic_insurance_to_alternate` and `pmt2core_touristic_insurance_to_insurance`) are removed during import by `_removeInsuranceOrphans()`.

---

## Detailed model description (insurance model)

### Overview

The insurance model represents pressmind PIM data for travel insurances. The central entity is **Insurance**: it can be a main insurance, an **additional insurance** (`additional_insurances` / `is_additional_insurance`), or an **alternate insurance** (`alternate_insurances` / `is_alternate_insurance`). A Media Object or Booking Package references one group via `id_insurance_group`; that group contains the selectable insurances plus price tables and relations.

---

### Insurance (core entity)

**Class:** `Pressmind\ORM\Object\Touristic\Insurance`  
**Table:** `pmt2core_touristic_insurances`

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | string(32) | yes | Primary key (from API/PIM) |
| `active` | boolean | no | Whether the insurance is offered as active |
| `name` | string(255) | no | Display name |
| `description` | string | no | Short description (often HTML) |
| `description_long` | string | no | Long description |
| `code` | string(255) | no | Internal code |
| `code_ibe` | string(255) | no | CRS/IBE code for booking systems |
| `request_code` | string(255) | no | Request code for CRS |
| `worldwide` | boolean | no | Worldwide validity |
| `is_additional_insurance` | boolean | no | `true` = additional (sub-) insurance (only offered in context of a main insurance) |
| `is_alternate_insurance` | boolean | no | `true` = alternate product (alternative to main insurance) |
| `urlinfo` | string | no | URL to info page |
| `urlproduktinfo` | string | no | URL to product info |
| `urlagb` | string | no | URL to terms and conditions |
| `own_contribution` | string | no | Own contribution / deductible text |
| `pax_min` / `pax_max` | integer | no | Passenger count (min/max) |
| `duration_max_days` | integer | no | Deprecated |
| `price_group` / `product_group` | string | no | Grouping for pricing/CRS |
| `is_recommendation` | boolean | no | Recommendation flag (from API) |
| `priority` | integer | no | Display/sort priority (from API) |

**Relations:**

- **`price_tables`** – Price tables (ManyToMany via `pmt2core_touristic_insurance_to_price_table`). Determine the valid price by age, travel date, duration, price, booking date, and pax.
- **`attributes`** – Insurance attributes (ManyToMany via `pmt2core_touristic_insurance_to_attributes`), e.g. coverage (included/excluded).
- **`additional_insurances`** – Additional insurances (ManyToMany via `pmt2core_touristic_insurance_to_insurance`). Populated only for main insurances; order is defined by `order` in the mapping table.
- **`alternate_insurances`** – Alternate insurances (ManyToMany via `pmt2core_touristic_insurance_to_alternate`). Alternative products to choose from; order via `order`.

**Important methods:**

- **`isAvailableForTravelDateAndPriceAndPersonAge(DateTime $dateStart, DateTime $dateEnd, float $travelPrice, int $duration, int $personAge = 18, int $total_number_of_persons = 0, bool $check_additional = false): Calculated|false`**  
  Checks whether the insurance is available for the given travel dates, price, duration, person age, and passenger count. Searches the related price tables and returns a **Calculated** object with the (lowest) price on match. Additional insurances are only considered when `$check_additional === true` (set internally when called via a main insurance). Contained `additional_insurances` are calculated recursively and attached to the returned Calculated object.
- **`Insurance::resetInsurances(): void`** (static)  
  Deletes all rows in all insurance-related tables (Groups, Insurances, mapping tables, Price Tables, Attributes). Uses DELETE (not TRUNCATE) for transaction safety.

---

### Insurance Group

**Class:** `Pressmind\ORM\Object\Touristic\Insurance\Group`  
**Table:** `pmt2core_touristic_insurance_groups`

A group bundles the insurances offered for a product (Media Object / Booking Package). **`mode`** controls how many insurances can be chosen: `single_selection` (one) or `multi_selection` (multiple). The **`insurances`** relation (ManyToMany via `pmt2core_touristic_insurance_to_group`) returns the related Insurance objects. A Media Object or Booking Package references a group via `id_insurance_group`.

---

### Price Table

**Class:** `Pressmind\ORM\Object\Touristic\Insurance\PriceTable`  
**Table:** `pmt2core_touristic_insurances_price_tables`

Defines price and validity conditions. Multiple price tables per insurance are allowed; the logic in `isAvailableForTravelDateAndPriceAndPersonAge()` selects the matching one (e.g. lowest price). Required fields in the model: `id`, `price`, `unit`; `travel_price_max` is also required.

| Property | Type | Description |
|----------|------|-------------|
| `id` | string(32) | Primary key |
| `code` | string(255) | Internal label for the price row |
| `code_ibe` | string(255) | CRS/IBE code |
| `price` | float | Price (absolute or basis for percent) |
| `unit` | string | `per_person` or `per_unit` (per booking) |
| `price_type` | string | e.g. `price`, `percent` (percent is not evaluated in the SDK currently) |
| `family_insurance` | boolean | Family insurance |
| `pair_insurance` | boolean | Pair/couple insurance |
| `age_from` / `age_to` | integer | Participant age range |
| `child_age_from` / `child_age_to` | integer | Child age range (e.g. for family insurance) |
| `travel_date_from` / `travel_date_to` | datetime | Travel date validity |
| `booking_date_from` / `booking_date_to` | datetime | Booking date validity |
| `travel_price_min` / `travel_price_max` | float | Travel price range (per person or total, depending on `unit`) |
| `travel_duration_from` / `travel_duration_to` | integer | Travel duration (nights) |
| `pax_min` / `pax_max` | integer | Passenger count |
| `adult_pax_min` / `adult_pax_max` | integer | Adult count (e.g. pair/family insurance) |
| `child_pax_min` / `child_pax_max` | integer | Child count (e.g. family insurance) |

The check in `Insurance::isAvailableForTravelDateAndPriceAndPersonAge()` evaluates all of the above (age, travel/booking date, duration, travel price, pax/adults/children) and picks the lowest matching price when multiple rows match.

---

### InsuranceToInsurance (additional insurances)

**Class:** `Pressmind\ORM\Object\Touristic\Insurance\InsuranceToInsurance`  
**Table:** `pmt2core_touristic_insurance_to_insurance`

Links a **main insurance** (`id_insurance`) to an **additional insurance** (`id_additional_insurance`). The **`order`** field defines the sort order of additional insurances.

| Property | Type | Description |
|----------|------|-------------|
| `id` | integer | Primary key (auto-increment) |
| `id_insurance` | string(32) | Main insurance ID |
| `id_additional_insurance` | string(32) | Additional insurance ID |
| `order` | integer | Display/sort order |

---

### InsuranceToAlternate (alternate insurances)

**Class:** `Pressmind\ORM\Object\Touristic\Insurance\InsuranceToAlternate`  
**Table:** `pmt2core_touristic_insurance_to_alternate`

Links a main insurance to alternate insurance products (e.g. with/without deductible). **`order`** controls the display order.

| Property | Type | Description |
|----------|------|-------------|
| `id` | integer | Primary key (auto-increment) |
| `id_insurance` | string(32) | Main insurance ID |
| `id_alternate_insurance` | string(32) | Alternate insurance ID |
| `order` | integer | Sort order |

---

### Calculated (price calculation result)

**Class:** `Pressmind\ORM\Object\Touristic\Insurance\Calculated` (not an ORM entity; plain DTO)

Returned by `Insurance::isAvailableForTravelDateAndPriceAndPersonAge()` and holds the data relevant for booking, including the calculated price.

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | Insurance ID |
| `name`, `description`, `description_long` | string | Insurance texts |
| `active` | boolean | Active flag |
| `code`, `code_price`, `code_ibe` | string | Codes (insurance and price table) |
| `price` | float | Calculated price |
| `family_insurance`, `pax_min`, `pax_max` | mixed | From the matching price table |
| `is_additional_insurance` | boolean | Additional insurance flag |
| `is_recommendation`, `priority` | boolean/int | Recommendation and priority |
| `urlinfo`, `urlproduktinfo`, `urlagb` | string | URLs |
| `additional_insurances` | Calculated[] | Recursively calculated additional insurances (each with its own price) |

---

### REST API: calculate prices

**Controller:** `Pressmind\REST\Controller\Touristic\Insurance`  
**Method:** `calculatePrices($params)`

Calculates the available insurance prices for a Media Object and given travel/booking data.

**Required parameters:**

- `id_media_object` – Media object ID  
- `price_person` – Travel price (per person)  
- `duration_nights` – Travel duration (nights)  
- `date_start` – Trip start (format `Y-m-d`)  
- `date_end` – Trip end (format `Y-m-d`)

**Optional parameters:**

- `age_person` – Age (default 18)  
- `total_number_of_participants` – Participant count (default 0)

Only active insurances from the Media Object’s insurance group are iterated; for each, `isAvailableForTravelDateAndPriceAndPersonAge()` is called. The returned **Calculated** objects (including any `additional_insurances`) form the API response.

---

### Import: API fields and mapping

Insurance data is imported from two sources:

1. **`response[0]->insurance_group`** (alternative booking tab in PIM):  
   - `insurance_groups` → `touristic_insurance_groups`  
   - `insurance_to_group` → `touristic_insurance_to_group`  
   - `insurances` → `touristic_insurances`  
   - `insurances_to_price_table` → `touristic_insurances_to_price_table`  
   - `insurances_price_tables` → `touristic_insurances_price_tables`  
   - `insurance_to_attribute` → `touristic_insurance_to_attribute`  
   - `insurance_attributes` → `touristic_insurance_attributes`  
   - `alternate_insurance_to_insurance` → `touristic_insurance_to_alternate`  
   - `additional_insurance_to_insurance` → `touristic_insurance_to_insurance`

2. **`response[0]->touristic`**:  
   Contains the same touristic-* arrays (e.g. `touristic_insurances`, `touristic_insurance_to_insurance`, `touristic_insurance_to_alternate`).

Legacy: If the API still sends **`touristic_additional_insurances`** (single items with `id_insurance` and insurance `id`), the importer still creates rows in `pmt2core_touristic_insurance_to_insurance` (with `id_additional_insurance`). Orphaned rows in all mapping tables are removed in `_removeInsuranceOrphans()`.

---

### Example: load insurance and read additional/alternate insurances

See [examples/insurance-additional-and-alternate.php](../examples/insurance-additional-and-alternate.php): loads an insurance by ID, outputs `is_recommendation` and `priority`, and iterates over `additional_insurances` and `alternate_insurances` including their attributes.

```php
$insurance = new \Pressmind\ORM\Object\Touristic\Insurance(15127);
echo "Insurance: " . $insurance->name . "\n";
echo "is_recommendation: " . ($insurance->is_recommendation ? 'yes' : 'no') . "\n";
echo "priority: " . $insurance->priority . "\n\n";

foreach ($insurance->additional_insurances as $additional_insurance) {
    $data = $additional_insurance->toStdClass();
    print_r($data);
    foreach ($additional_insurance->attributes as $attribute) {
        print_r($attribute->toStdClass());
    }
}

foreach ($insurance->alternate_insurances as $alternate_insurance) {
    $data = $alternate_insurance->toStdClass();
    print_r($data);
    // ...
}
```

---

## Related Documentation

- [Booking Package](Booking/Package.md) – `id_insurance_group` reference
- [Import Process](../../import-process.md) – Phase 3.2 touristic data import
- [Example: Insurance additional and alternate](../examples/insurance-additional-and-alternate.php)
