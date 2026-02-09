# Touristic: Option

[← Back to Documentation](../documentation.md) | [→ Transport](Transport.md) | [→ Discount](Option/Discount.md)

**Namespace:** `Pressmind\ORM\Object\Touristic\Option`

---

## Description

An Option represents a **bookable service** within a touristic product. Options cover all service types — from hotel rooms and cabin categories to excursions, tickets, and extras. The `type` field determines how the option is treated in price calculations, booking flows, and frontend display.

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | string(32) | yes | Primary key |
| `id_media_object` | integer | yes | Reference to the parent MediaObject |
| `id_booking_package` | string(32) | yes | Reference to the parent Booking Package |
| `id_housing_package` | string(32) | no | Reference to the Housing Package (only for type=housing_option) |
| `type` | enum | yes | Service type. See [Type Enum](#type) |
| `season` | string(100) | no | Season key linking this option to Date(s). See [Date.season](Date.md#season-matching) |
| `code` | string(45) | no | External code |
| `name` | string(255) | no | Display name, e.g. "Double Room Sea View" |
| `board_type` | string(45) | no | Meal plan label (only for type=housing_option), e.g. "Half Board", "All Inclusive" |
| `board_code` | string(10) | no | Meal plan code |
| `event` | string(45) | no | Event name (only for type=ticket) |
| `price` | float | yes | Price value. Interpretation depends on `currency` and `price_due` |
| `price_pseudo` | float | yes | Pseudo price for strike-through display. Conflicts with Early Bird discounts (Early Bird takes priority) |
| `occupancy` | integer | yes | Occupancy (number of persons this price applies to) |
| `order` | integer | yes | Sort order |
| `booking_type` | integer | yes | `0` = bookable, `1` = display only (must not be booked or included in base price) |
| `state` | integer | yes | Status. See [State Enum](#state) — **state values differ by option type!** |
| `code_ibe` | string(255) | no | CRS booking code |
| `price_due` | enum | no | Price billing mode. See [Price Due](#price-due) — **allowed values differ by option type!** |
| `code_ibe_board_type` | string(255) | no | CRS code for the meal plan |
| `auto_book` | boolean | no | If `true`, this option is pre-selected in the booking flow |
| `required` | boolean | no | If `true`, this option must be booked and is included in the base price (ab-Preis) |
| `required_group` | string(255) | no | Group key for forming option groups. See [Required Groups](#required-groups) |
| `description_long` | string | no | Long description |
| `reservation_date_from` | datetime | no | Alternative date mapping (overrides season matching). Must match the Date exactly |
| `reservation_date_to` | datetime | no | See `reservation_date_from` |
| `age_from` | integer | yes | Age restriction (minimum) |
| `age_to` | integer | yes | Age restriction (maximum) |
| `use_earlybird` | boolean | no | If `true`, Early Bird discounts may be applied to this option |
| `currency` | string(11) | no | Currency code, default: EUR |
| `occupancy_min` | integer | no | Minimum occupancy |
| `occupancy_max` | integer | no | Maximum occupancy |
| `dont_use_for_offers` | boolean | no | If `true`, this option is excluded from base price (ab-Preis) calculation, e.g. child-only prices |
| `agencies` | string | no | Comma-separated list of allowed agencies |
| `ibe_clients` | string | no | Comma-separated list of allowed CRS clients |
| `crs_meta_data` | longtext | no | Free-form CRS metadata (JSON) |
| `id_media_object_option` | integer | no | Reference to a linked MediaObject for this option |
| `request_code` | string(255) | no | CRS request code |
| `price_group` | string(255) | no | CRS price group |
| `product_group` | string(255) | no | CRS product group |

### Relations

| Relation | Type | Description |
|---|---|---|
| `discount` | Discount | Age/occupancy-based discount schedule |

### Deprecated Properties

| Property | Note |
|---|---|
| `price_child` | Use discount scales instead |
| `occupancy_child` | Use discount scales instead |
| `occupancy_max_age` | Use age_from/age_to instead |
| `quota` | Use per-date options instead |
| `renewal_duration` | No longer used |
| `renewal_price` | No longer used |
| `min_pax` | No longer used |
| `max_pax` | No longer used |
| `selection_type` | No longer used |
| `id_transport` | Rarely used (transport_extra only) |
| `id_touristic_option_discount` | Use `discount` relation instead |

---

## Type

| Value | Description | Part of base price? |
|---|---|---|
| `housing_option` | Accommodation (room, cabin) | Yes (with `date_housing` price_mix) |
| `extra` | Additional service | Yes (with `date_extra` price_mix), otherwise optional |
| `sightseeing` | Excursion | Yes (with `date_sightseeing` price_mix), otherwise optional |
| `ticket` | Entrance ticket / event | Yes (with `date_ticket` price_mix), otherwise optional |
| `transport_extra` | Transport-related extra | Optional (rarely used) |
| `dummy` | Placeholder | Not bookable |

---

## State

**Important:** State values have **different meanings** depending on the option type.

### Housing Option States

| Value | Key | Description |
|---|---|---|
| `0` | Sold out | No availability |
| `1` | On request | Available on request |
| `2` | Few remaining | Limited availability (bookable) |
| `3` | Active | Available (bookable) |
| `4` | Booking stop | Temporarily blocked |
| `5` | Hidden | Not displayed |

### Extra / Ticket / Sightseeing States

| Value | Key | Description |
|---|---|---|
| `0` | Sold out | No availability |
| `1` | On request | Available on request |
| `2` | Few remaining | Limited availability (bookable) |
| `3` | Active | Available (bookable) |
| `4` | Booking stop | Temporarily blocked |
| `5` | Hidden | Not displayed |

> **Note:** While the enum values are identical between housing_option and extra/ticket/sightseeing, state filters in the configuration distinguish between them. See [Configuration: housing_option_filter / transport_filter](../config-touristic-data.md).

---

## Price Due

The `price_due` field defines the billing frequency of the price. **Allowed values depend on the option type.**

### For `type = housing_option`

| Value | Description | Calculation |
|---|---|---|
| `person_stay` | Per person, per stay | Used as-is (default) |
| `stay` | Per stay (total) | Used as-is |
| `nights_person` | Per night, per person | `price × nights` |

> Housing option prices are **never converted** — they are stored exactly as the PIM defines them.

### For `type = extra / ticket / sightseeing`

| Value | Description | Calculation |
|---|---|---|
| `once` | One-time per person | Used as-is (default) |
| `once_stay` | One-time per stay | Used as-is |
| `nightly` | Per night | `price × nights` → converted to `once` |
| `daily` | Per day | `price × duration_days` → converted to `once` |
| `weekly` | Per week | `price × ceil(duration_days / 7)` → converted to `once` |

> For extras/tickets/sightseeings, periodic prices (`nightly`, `daily`, `weekly`) are **converted to `once`** during CheapestPrice aggregation. See [CheapestPrice Aggregation: Option Price Due Modes](../cheapest-price-aggregation.md).

---

## Required Groups

The combination of `auto_book`, `required`, and `required_group` controls how options are presented in booking forms:

| Case | auto_book | required | required_group | Behavior |
|---|---|---|---|---|
| **1** | — | ✓ | — | Mandatory, must be actively selected by user |
| **2** | ✓ | — | — | Pre-selected, can be deselected |
| **3** | ✓ | ✓ | — | Pre-selected, cannot be deselected |
| **4** | — | — | A | Checkbox group: none, one, or all can be selected |
| **5** | ✓ | — | A | Radio group: one pre-selected, user can switch |
| **6** | ✓ | ✓ | A | One locked + rest as checkboxes |
| **7** | — | ✓ | A | Radio group: user must pick one |
| **8** | ✓ | ✓ | A | All pre-selected and locked |

> `required_group` is not intended for `type = housing_option`. It is designed for extras, tickets, and sightseeings.

---

## Related Documentation

- [Booking Package](Booking/Package.md) – Parent entity and price_mix definition
- [Date](Date.md) – Season matching between dates and options
- [Housing Package](Housing/Package.md) – Accommodation groups containing housing_options
- [Discount](Option/Discount.md) – Age/occupancy-based discount schedules
- [CheapestPrice Aggregation](../cheapest-price-aggregation.md) – How option prices are calculated
- [Configuration: Touristic](../config-touristic-data.md) – State filters, offer generation settings
