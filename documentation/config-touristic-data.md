# Configuration: Touristic Data & Import

[← Back to Overview](configuration.md) | [→ Configuration Examples & Best Practices](config-examples.md)

---

## Overview

The `data.touristic` section controls the import and processing of touristic data – from travel dates and accommodations to price calculations. Together with import hooks and media type configurations, it forms the core of data management.

> **See also:** [Configuration Examples & Best Practices](config-examples.md) for real-world values from ~40 production installations.

---

## Basic Touristic Configuration (`data.touristic`)

```json
"data": {
  "touristic": {
    "origins": ["0"],
    "my_content_class_map": {},
    "disable_touristic_data_import": [],
    "disable_virtual_price_calculation": [],
    "disable_manual_cheapest_price_import": [],
    "generate_single_room_index": false,
    "max_offers_per_product": 5000,
    "generate_offer_for_each_startingpoint_option": false,
    "generate_offer_for_each_transport_type": false,
    "generate_offer_for_each_option_board_type": false,
    "ibe_client": null,
    "include_negative_option_in_cheapest_price": true,
    "label_price_mix_date_transport": "Teilnahmegebühr"
  }
}
```

---

### `data.touristic.origins`

| Property | Value |
|---|---|
| **Type** | `array` of strings/integers |
| **Default** | `["0"]` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

Defines the touristic origins (source markets) whose data is fetched during import. Origins are source markets defined in Pressmind (e.g., Germany = 0, Austria = 1).

#### Usage in Code

```php
// src/Pressmind/Import.php:504
$touristicOrigins = isset($config['data']['touristic']['origins']) 
    && !empty($config['data']['touristic']['origins']) 
    ? $config['data']['touristic']['origins'] 
    : [0];

$response = $this->_client->sendRequest('Text', 'getById', [
    'ids' => $id_media_object,
    'withTouristicData' => 1,
    'withDynamicData' => 1,
    'byTouristicOrigin' => implode(',', $touristicOrigins)
]);
```

#### Fallback

If not set or empty, `[0]` (default market) is used.

#### Examples

```json
// Default market only (Germany)
"origins": ["0"]

// Multiple markets
"origins": ["0", "1", "2"]

// Specific market only
"origins": ["3"]
```

---

### `data.touristic.my_content_class_map`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

Maps MyContent IDs to custom importer classes. Enables processing of MyContent data with specialized logic.

#### Usage in Code

```php
// src/Pressmind/Import.php:731-734
if (isset($config['data']['touristic']['my_content_class_map'][$my_content->id_my_content])) {
    $touristic_class_name = $config['data']['touristic']['my_content_class_map'][$my_content->id_my_content];
    $custom_importer = new $touristic_class_name($my_content, $id_media_object);
    $custom_importer->import();
}
```

#### Example

```json
"my_content_class_map": {
  "42": "\\Custom\\Import\\HotelAmenities",
  "99": "\\Custom\\Import\\TransferService"
}
```

The referenced class must accept the MyContent record and the media object ID in its constructor and provide an `import()` method.

---

### `data.touristic.disable_touristic_data_import`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[]` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

List of object type IDs for which the **full touristic import** is disabled. For these types, only `touristic_base` is imported (base data), but no dates, prices, accommodations, etc.

#### Usage in Code

```php
// src/Pressmind/Import.php:519
$disable_touristic_data_import = (
    isset($config['data']['touristic']['disable_touristic_data_import']) 
    && in_array($response[0]->id_media_objects_data_type, $config['data']['touristic']['disable_touristic_data_import'])
);

if (is_a($response[0]->touristic, 'stdClass') && false == $disable_touristic_data_import) {
    // Full touristic import
}

if (is_a($response[0]->touristic->touristic_base, 'stdClass') && true == $disable_touristic_data_import) {
    // Only import touristic_base
}
```

#### Use Cases

- Object types without touristic data (e.g., pure content pages)
- Object types whose touristic data is managed externally
- Performance optimization for non-touristic content types

#### Example

```json
// Content pages (ID 200) and blog posts (ID 201) without touristic data
"disable_touristic_data_import": [200, 201]
```

---

### `data.touristic.disable_virtual_price_calculation`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[]` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

List of object type IDs for which the **virtual cheapest price calculation** is disabled. No CheapestPrice entries are automatically calculated for these types.

#### Usage in Code

```php
// src/Pressmind/Import.php:577-580
$disable_virtual_price_calculation = (
    isset($config['data']['touristic']['disable_virtual_price_calculation']) 
    && in_array($response[0]->id_media_objects_data_type, $config['data']['touristic']['disable_virtual_price_calculation'])
);

if (!empty($response[0]->cheapest_prices) && !$disable_virtual_price_calculation) {
    $touristic_data_importer = new MediaObjectCheapestPrice();
    $touristic_data_importer->import($response[0]->cheapest_prices, $id_media_object, $this->_import_type);
}
```

#### Example

```json
// No virtual price calculation for car rentals (ID 300)
"disable_virtual_price_calculation": [300]
```

---

### `data.touristic.disable_manual_cheapest_price_import`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[]` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

List of object type IDs for which the **manual cheapest price import** is disabled.

#### Example

```json
"disable_manual_cheapest_price_import": [300, 301]
```

---

### `data.touristic.generate_single_room_index`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `ORM\Object\MediaObject.php` |

#### Description

Enables the generation of a single room index after the CheapestPrice calculation. Useful for hotels and accommodations that need to display single room surcharges.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject.php:1780-1782
if (!empty($config['data']['touristic']['generate_single_room_index'])) {
    $cheapestPriceSpeed = new CheapestPriceSpeed();
    $cheapestPriceSpeed->generateSingleRoomIndex($this->getId());
}
```

#### Example

```json
"generate_single_room_index": true
```

---

### `data.touristic.max_offers_per_product`

| Property | Value |
|---|---|
| **Type** | `integer` |
| **Default** | `5000` |
| **Required** | No |
| **Used in** | `ORM\Object\MediaObject.php` |

#### Description

Maximum number of offers generated per product during the CheapestPrice calculation. Serves as a safety limit to prevent memory and performance issues.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject.php:1391
$max_rows = empty(Registry::getInstance()->get('config')['data']['touristic']['max_offers_per_product']) 
    ? 5000 
    : Registry::getInstance()->get('config')['data']['touristic']['max_offers_per_product'];
```

#### Fallback

If not set or `0`, `5000` is used as default.

#### Recommendations

| Scenario | Recommended Value |
|---|---|
| Standard | `5000` |
| Cruises (many cabins) | `10000` - `20000` |
| Complex products (many date/transport combos) | `100000` - `150000` |

> **Production Insight:** ~80% of production installations use `5000`. Only projects with very complex touristic products increase this to `100000`+. Setting it too high can cause import timeouts.

#### Example

```json
"max_offers_per_product": 5000
```

---

### `data.touristic.generate_offer_for_each_startingpoint_option`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `ORM\Object\MediaObject.php`, `Search\MongoDB\Indexer.php`, `Search\MongoDB.php` |

#### Description

When enabled, a separate offer is generated for each departure point (starting point). This enables searching and filtering by departure location with individual prices.

#### Effects

- **SQL GROUP BY** is extended with `startingpoint_id_city`
- **MongoDB indexing** creates separate documents per departure point
- **More data volume** in the database and search index

#### Usage in Code

```php
// SQL query in Indexer.php
GROUP BY option_occupancy, {$durationBucketCase}, price_total
    .(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) 
        ? "" 
        : ", startingpoint_id_city");
```

#### Example

```json
"generate_offer_for_each_startingpoint_option": true
```

---

### `data.touristic.generate_offer_for_each_transport_type`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `Search\MongoDB\Indexer.php` |

#### Description

When enabled, a separate offer is generated for each transport type (flight, bus, self-drive, etc.).

#### Usage in Code

```php
// PARTITION BY in SQL query
PARTITION BY date_departure, option_occupancy, {$durationBucketCase}
    .(empty($this->_config_touristic['generate_offer_for_each_transport_type']) 
        ? "" 
        : ", transport_type")
```

#### Example

```json
"generate_offer_for_each_transport_type": true
```

---

### `data.touristic.generate_offer_for_each_option_board_type`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `Search\MongoDB\Indexer.php` |

#### Description

When enabled, a separate offer is generated for each board type (half board, full board, all-inclusive, etc.).

#### Example

```json
"generate_offer_for_each_option_board_type": true
```

---

### `data.touristic.ibe_client`

| Property | Value |
|---|---|
| **Type** | `null` or `string`/`integer` |
| **Default** | `null` |
| **Required** | No |
| **Used in** | `ORM\Object\MediaObject.php` |

#### Description

IBE client identifier for the CheapestPrice calculation. Allows filtering prices for a specific IBE client.

#### Usage in Code

```php
// src/Pressmind/ORM/Object/MediaObject.php:1392
$ibe_client = empty(Registry::getInstance()->get('config')['data']['touristic']['ibe_client']) 
    ? null 
    : Registry::getInstance()->get('config')['data']['touristic']['ibe_client'];
```

#### Example

```json
// No specific client
"ibe_client": null

// Specific IBE client
"ibe_client": "web_standard"
```

---

### `data.touristic.include_negative_option_in_cheapest_price`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |
| **Required** | No |
| **Used in** | `ORM\Object\MediaObject.php` |

#### Description

Controls whether options with negative prices (discounts, deductions) are included in the CheapestPrice calculation.

#### Behavior

- `true` (default): Negative options are subtracted → cheapest price can be lower
- `false`: Negative options are ignored → more conservative price display

#### Example

```json
// Default: Include negative options
"include_negative_option_in_cheapest_price": true

// Conservative: Ignore negative options
"include_negative_option_in_cheapest_price": false
```

---

### `data.touristic.label_price_mix_date_transport`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"Teilnahmegebühr"` |
| **Required** | No |
| **Used in** | `REST\Controller\Ibe.php` |

#### Description

Display name for the `date_transport` price mix option. This is the label text shown in the IBE for this price category.

#### Usage in Code

```php
// src/Pressmind/REST/Controller/Ibe.php:210
$option->name = !empty($config['data']['touristic']['label_price_mix_date_transport']) 
    ? $config['data']['touristic']['label_price_mix_date_transport'] 
    : 'Teilnahmegebühr';
```

#### Examples

```json
// Default (German)
"label_price_mix_date_transport": "Teilnahmegebühr"

// Alternative text
"label_price_mix_date_transport": "Base Price"

// English
"label_price_mix_date_transport": "Participation Fee"
```

---

## Filters (`data.touristic.date_filter`, `housing_option_filter`, `transport_filter`)

Filters control which touristic data is included in the CheapestPrice calculation.

---

### `data.touristic.date_filter`

```json
"date_filter": {
  "active": true,
  "orientation": "arrival",
  "offset": 0,
  "allowed_states": [0, 1, 2, 4, 5],
  "max_date_offset": 730
}
```

#### `date_filter.active`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |

Enables/disables the date filter. When `false`, all dates are considered regardless of timeframe and state.

#### `date_filter.orientation`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"arrival"` |
| **Valid values** | `"departure"`, `"arrival"` |

Determines whether the **departure** or **arrival date** is used for filtering.

```php
// Validation in code
if (!in_array($travel_date_orientation, ['departure', 'arrival'])) {
    throw new Exception('Error: data.touristic.date_filter.orientation must be either "departure" or "arrival"');
}
```

#### `date_filter.offset`

| Property | Value |
|---|---|
| **Type** | `integer` (days) |
| **Default** | `0` |

Offset in days from today. Dates before `today + offset` are excluded.

**Example:** With `offset: 3`, only dates from the day after tomorrow onward are considered.

#### `date_filter.allowed_states`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[0, 1, 2, 3, 4, 5]` |

State values of travel dates that should be considered.

| Value | Meaning |
|---|---|
| `0` | Available |
| `1` | On request |
| `2` | Few available |
| `3` | Fully booked |
| `4` | Cancelled |
| `5` | Closed |

#### `date_filter.max_date_offset`

| Property | Value |
|---|---|
| **Type** | `integer` (days) |
| **Default** | `730` (approx. 2 years) |

Maximum timeframe into the future. Dates further than `max_date_offset` days in the future are excluded.

#### Complete Example

```json
"date_filter": {
  "active": true,
  "orientation": "departure",
  "offset": 2,
  "allowed_states": [0, 1, 2],
  "max_date_offset": 365
}
```

This filters: Only departure dates from the day after tomorrow, up to max 1 year in the future, with status available/on request/few available.

> **Production Insight:** Across ~40 production installations:
> - `allowed_states` is **universally** `[0, 1, 2, 4, 5]` — state `3` (Fully booked) is always excluded
> - `orientation`: ~60% use `arrival`, ~35% use `departure`
> - `offset`: values range from `0` (most common) to `14` (youth travel, charter flights)
> - `max_date_offset`: `730` is standard, cruise operators use `910`–`1460`
> - See [Configuration Examples](config-examples.md#date-filter) for detailed patterns

---

### `data.touristic.housing_option_filter`

```json
"housing_option_filter": {
  "active": true,
  "allowed_states": [0, 1, 2, 3]
}
```

#### `housing_option_filter.active`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |

Enables/disables the accommodation filter.

#### `housing_option_filter.allowed_states`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[0, 1, 2, 3]` |

Allowed state values for accommodation options.

---

### `data.touristic.transport_filter`

```json
"transport_filter": {
  "active": true,
  "allowed_states": [0, 2, 3]
}
```

#### `transport_filter.active`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |

Enables/disables the transport filter.

#### `transport_filter.allowed_states`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[0, 2, 3]` |

Allowed state values for transport options.

**Used in:** `ORM\Object\MediaObject.php`, `ORM\Object\Touristic\Date.php`, `REST\Controller\Ibe.php`

---

### `data.touristic.agency_based_option_and_prices`

```json
"agency_based_option_and_prices": {
  "enabled": false,
  "allowed_agencies": [0, 1, 2, 3]
}
```

#### `agency_based_option_and_prices.enabled`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |

Enables agency-based processing of options and prices. When enabled, options and prices are calculated **per agency**.

**Used in:** `ORM\Object\MediaObject.php`, `Search\MongoDB\AbstractIndex.php`, `ORM\Object\Touristic\Date.php`

#### `agency_based_option_and_prices.allowed_agencies`

| Property | Value |
|---|---|
| **Type** | `array` of integers |
| **Default** | `[0, 1, 2, 3]` |

List of agency IDs for which options and prices are calculated. Only relevant when `enabled: true`.

#### Example

```json
"agency_based_option_and_prices": {
  "enabled": true,
  "allowed_agencies": [0, 1, 5, 12]
}
```

---

## Import Hooks (`data.media_type_custom_import_hooks`, `media_type_custom_post_import_hooks`)

---

### `data.media_type_custom_import_hooks`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

Defines custom classes that are executed **during** the import for specific object types. The hooks are called for each imported media object of the respective type.

#### Usage in Code

```php
// src/Pressmind/Import.php:399-401
if (isset($config['data']['media_type_custom_import_hooks'][$id_object_type]) 
    && is_array($config['data']['media_type_custom_import_hooks'][$id_object_type])) {
    foreach ($config['data']['media_type_custom_import_hooks'][$id_object_type] as $custom_import_class_name) {
        $custom_import_class = new $custom_import_class_name($id_media_object);
        $custom_import_class->import();
    }
}
```

#### Structure

```json
"media_type_custom_import_hooks": {
  "{object_type_id}": [
    "\\Fully\\Qualified\\ClassName1",
    "\\Fully\\Qualified\\ClassName2"
  ]
}
```

#### Example

```json
"media_type_custom_import_hooks": {
  "123": [
    "\\Custom\\Import\\HotelRatingSync",
    "\\Custom\\Import\\GeoCoordinateEnricher"
  ],
  "456": [
    "\\Custom\\Import\\FlightDataProcessor"
  ]
}
```

The classes must implement `ImportInterface` and provide an `import()` method.

---

### `data.media_type_custom_post_import_hooks`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

Defines custom classes that are executed **after** the complete import for specific object types.

#### Usage in Code

```php
// src/Pressmind/Import.php:1039-1041
if (isset($config['data']['media_type_custom_post_import_hooks']) 
    && is_array($config['data']['media_type_custom_post_import_hooks'])) {
    foreach ($config['data']['media_type_custom_post_import_hooks'] as $id_object_type => $hooks) {
        if (!isset($config['data']['media_types'][$id_object_type])) {
            continue;  // Skip if the object type doesn't exist
        }
        foreach ($hooks as $custom_import_class_name) {
            $custom_import_class = new $custom_import_class_name($id_media_object);
            $custom_import_class->import($id_media_object);
        }
    }
}
```

> **Note:** Post-import hooks check whether the `id_object_type` exists in `data.media_types`. Unregistered types are skipped.

#### Example

```json
"media_type_custom_post_import_hooks": {
  "123": [
    "\\Custom\\PostImport\\SearchIndexUpdater",
    "\\Custom\\PostImport\\CacheWarmer"
  ]
}
```

---

## Media Type Configuration

---

### `data.primary_media_type_ids`

| Property | Value |
|---|---|
| **Type** | `null` or `array` of integers |
| **Default** | `null` |
| **Required** | No |
| **Used in** | `Import.php`, `ORM\Object\MediaObject.php`, `System\TouristicOrphans.php` |

#### Description

Defines the primary object type IDs. When set, imports and many operations are restricted to these types.

#### Usage in Code

```php
// src/Pressmind/Import.php:198-199
$allowed_object_types = array_keys($conf['data']['media_types']);
if (!empty($config['data']['primary_media_type_ids'])) {
    $allowed_object_types = $config['data']['primary_media_type_ids'];
}

// In MediaObject:
public function isAPrimaryType() {
    return in_array($this->id_object_type, $config['data']['primary_media_type_ids']);
}
```

#### Behavior

- `null`: All types from `media_types` are considered allowed
- Array: Only the listed type IDs are imported/processed

#### Example

```json
// Only import trips and hotels
"primary_media_type_ids": [123, 456]

// Allow all types (default)
"primary_media_type_ids": null
```

---

### `data.media_types`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Yes (automatically populated during setup) |
| **Used in** | `Import.php` |

#### Description

Registration of all available media types with their IDs as keys. Automatically generated during SDK setup and serves as a fallback for `primary_media_type_ids`.

#### Example

```json
"media_types": {
  "123": {"name": "Trip"},
  "456": {"name": "Hotel"},
  "789": {"name": "Attraction"}
}
```

---

### `data.media_types_allowed_visibilities`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | Recommended |
| **Used in** | `Import.php`, `ORM\Object\MediaObject.php`, `Search\MongoDB\AbstractIndex.php`, `Search\OpenSearch\AbstractIndex.php` |

#### Description

Defines per object type which visibility levels are allowed to be imported and indexed.

#### Usage in Code

```php
// Import filtering
$allowed_visibilities = $conf['data']['media_types_allowed_visibilities'][$allowed_object_type];

// Validation
if (in_array($this->visibility, $config['data']['media_types_allowed_visibilities'][$this->id_object_type])) {
    $result[] = '    ✅  allowed visibility';
}
```

#### Example

```json
"media_types_allowed_visibilities": {
  "123": [30, 60],
  "456": [30, 60, 90]
}
```

| Value | Typical Meaning |
|---|---|
| `10` | Draft |
| `30` | Published |
| `60` | Website-visible |
| `90` | Archived |

---

### `data.disable_recursive_import`

| Property | Value |
|---|---|
| **Type** | `object` |
| **Default** | `{}` |
| **Required** | No |
| **Used in** | `Import\MediaObjectData.php` |

#### Description

Disables the recursive import of linked objects for specific fields of specific object types.

#### Usage in Code

```php
// src/Pressmind/Import/MediaObjectData.php:144-145
if (!empty($conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type]) 
    && is_array($conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type]) 
    && in_array($column_name, $conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type])) {
    Writer::write('object_link import is disabled for field "' . $column_name . '"', ...);
    $import_linked_objects = false;
}
```

#### Use Case

Prevents linked objects from being automatically imported when importing an object. Useful for circular links or when linked objects are imported separately.

#### Example

```json
"disable_recursive_import": {
  "123": ["unterkuenfte_default", "transfers_default"],
  "456": ["verwandte_reisen_default"]
}
```

> **Field name format:** `{varname}_{section}` – e.g., `unterkuenfte_default` for the field `unterkuenfte` in the `Default` section.

---

## Schema Migration (`data.schema_migration`)

```json
"schema_migration": {
  "mode": "log_only",
  "log_changes": true
}
```

---

### `data.schema_migration.mode`

| Property | Value |
|---|---|
| **Type** | `string` |
| **Default** | `"log_only"` |
| **Required** | No |
| **Used in** | `System\SchemaMigrator.php` |

#### Valid Values

| Value | Description |
|---|---|
| `"auto"` | Automatic migration: Missing DB columns are added, PHP classes are updated |
| `"log_only"` | Logging only: Differences are logged but not migrated (default) |
| `"abort"` | Abort: Exception is thrown when schema differences are found |

### `data.schema_migration.log_changes`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `true` |

Enables logging of schema changes.

#### Example

```json
// Development: Automatic migration
"schema_migration": {
  "mode": "auto",
  "log_changes": true
}

// Production: Logging only
"schema_migration": {
  "mode": "log_only",
  "log_changes": true
}
```

---

## Import Safety (`data.import`)

```json
"import": {
  "max_orphan_delete_ratio": 0.5,
  "force_orphan_removal": false
}
```

---

### `data.import.max_orphan_delete_ratio`

| Property | Value |
|---|---|
| **Type** | `float` (0.0 - 1.0) |
| **Default** | `0.5` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

Maximum ratio of orphaned objects that can be deleted in a single import run. Serves as a safety mechanism against accidental mass deletion.

#### Usage in Code

```php
// src/Pressmind/Import.php:947
$max_ratio = isset($config['data']['import']['max_orphan_delete_ratio']) 
    ? floatval($config['data']['import']['max_orphan_delete_ratio']) 
    : 0.5;

$ratio = $orphan_count / $total_in_db;
if ($ratio > $max_ratio) {
    // Deletion is aborted – too many orphans
}
```

#### Example

```json
// Default: Maximum 50% can be deleted
"max_orphan_delete_ratio": 0.5

// Conservative: Maximum 20%
"max_orphan_delete_ratio": 0.2

// Very permissive: Up to 80%
"max_orphan_delete_ratio": 0.8
```

> **See also:** [Import Safety Documentation](import-safety.md)

---

### `data.import.force_orphan_removal`

| Property | Value |
|---|---|
| **Type** | `boolean` |
| **Default** | `false` |
| **Required** | No |
| **Used in** | `Import.php` |

#### Description

Forces orphan removal even if the ratio exceeds `max_orphan_delete_ratio`.

> **Warning:** Only enable if you are certain that orphan detection is working correctly!

#### Example

```json
// Default: Ratio check active
"force_orphan_removal": false

// Delete all orphans without limit
"force_orphan_removal": true
```

---

[← Back to Overview](configuration.md) | [Next: Search →](config-search.md)
