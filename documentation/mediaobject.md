# MediaObject

[← Back to Documentation](documentation.md) | [→ Booking Package](Touristic/Booking/Package.md)

**Namespace:** `Pressmind\ORM\Object\MediaObject`

---

## Description

The MediaObject is the **central entity** of the pressmind SDK. It represents a touristic product (trip, hotel, cruise, etc.) or any content object managed in the pressmind PIM. Each MediaObject has a type (`id_object_type`) that defines its data structure and behavior.

MediaObjects carry:
- **Static content** — name, code, visibility, validity dates
- **Dynamic content** — type-specific fields (descriptions, images, categories) via `data`
- **Touristic data** — booking packages, dates, options, transports via `booking_packages`
- **Relations** — agencies, brands, seasons, routes, insurance groups

---

## Properties

| Property | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes | Primary MediaObject ID |
| `id_pool` | integer | yes | Pool assignment. Pools classify objects by market or product group |
| `id_brand` | integer | yes | Brand assignment. Used in larger setups for distribution brands |
| `id_object_type` | integer | yes | Object type ID, defines the data structure |
| `id_client` | integer | yes | Pressmind client ID. Usually constant, except for cross-client sharing |
| `id_season` | integer | yes | Season assignment, typically used in hotel distribution brands |
| `id_insurance_group` | integer | yes | Insurance group assignment (for simplified booking module without touristic data) |
| `name` | string | yes | Working title. **Should not be displayed publicly** — use type-specific headline fields instead |
| `code` | string | yes | External code for CRS/third-party system mapping |
| `tags` | string | no | Free-form tags |
| `visibility` | integer | yes | Visibility domain. See [Visibility Enum](#visibility) |
| `state` | integer | yes | Internal editorial status |
| `valid_from` | DateTime | no | Validity start date |
| `valid_to` | DateTime | no | Validity end date |
| `is_reference` | boolean | no | `true` if this is a reference to a parent object |
| `reference_media_object` | integer | no | Parent MediaObject ID (when `is_reference = true`) |
| `different_season_from` | DateTime | no | Override season start date |
| `different_season_to` | DateTime | no | Override season end date |
| `recommendation_rate` | float | no | Recommendation rate (e.g. from review systems) |
| `booking_type` | string | no | Booking type (simplified booking module only) |
| `booking_link` | string | no | Direct IBE booking link |
| `sales_priority` | string | no | Sales priority: `A` (highest), `B`, `C` |
| `sales_position` | integer | no | Position within priority level (1 = highest) |

### Relations

| Relation | Type | Description |
|---|---|---|
| `data` | AbstractMediaType[] | Dynamic type-specific content fields |
| `booking_packages` | Booking\Package[] | Touristic booking packages |
| `my_contents` | MyContent[] | Links from MyContent module |
| `touristic_base` | Base | Basic touristic module info |
| `insurance_group` | Group | Insurance group |
| `routes` | Route[] | URL routes (pretty URLs) |
| `season` | Season | Season assignment |
| `brand` | Brand | Brand assignment |
| `agencies` | Agency[] | Agency assignments |
| `manual_cheapest_prices` | ManualCheapestPrice[] | Manual base prices (simplified booking module) |

### Deprecated Properties

| Property | Note |
|---|---|
| `hidden` | Use `visibility` instead |
| `cheapest_price_total` | Use CheapestPriceSpeed table instead |

---

## Visibility

| Value | Domain | Description |
|---|---|---|
| `10` | Nobody | Not visible |
| `30` | Public | Visible to all |
| `60` | Extranet | Visible in extranet only |

> **Production Insight:** ~95% of production installations use visibility `30` (Public). Some projects additionally include `60` (Extranet). The `media_types_allowed_visibilities` config controls which visibilities are imported. See [Configuration: Touristic Data](config-touristic-data.md).

---

## Related Documentation

- [Booking Package](Touristic/Booking/Package.md) – Touristic packages within this MediaObject
- [Template Interface](template-interface.md) – How to render MediaObject data in templates via `render()`
- [Import Process](import-process.md) – How MediaObjects are imported from the PIM
- [CheapestPrice Aggregation](cheapest-price-aggregation.md) – How base prices are calculated for MediaObjects
- [MongoDB Index Configuration](search-mongodb-index-configuration.md) – How MediaObject data is indexed for search
