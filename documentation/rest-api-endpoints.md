# REST API – Endpoint Reference

[← Back to Architecture](architecture.md)

---

## Overview

The SDK provides a built-in REST API server (`Pressmind\REST\Server`) that exposes touristic product data, search functionality, import controls, and cache management. The API serves as the primary interface between the SDK and consumer applications (Travelshop, IBE3, custom frontends).

### Base Configuration

- **Endpoint:** Configured via `rest.server.api_endpoint` (default: `/rest`)
- **Authentication:** HTTP Basic Auth via `rest.server.api_user` / `rest.server.api_password` (disabled when empty)
- **Response Format:** JSON with `Content-Type: application/json`
- **Compression:** Gzip (when `Accept-Encoding: gzip` is sent)
- **CORS:** `Access-Control-Allow-Origin: *`
- **Caching:** Response caching via Redis (when `cache.enabled` and `"REST"` in `cache.types`)

### Routing

The SDK uses two routing mechanisms:

1. **Explicit Routes** – Hardcoded in `Server.php` for specific endpoints
2. **Dynamic Routes** – Any URI path matching a controller class is automatically routed to `listAll()` (GET/POST)

Custom controllers under `\Custom\REST\Controller\*` take priority over SDK controllers.

---

## Authentication

All endpoints require authentication when `rest.server.api_user` and `rest.server.api_password` are configured.

```bash
# Authenticated request
curl -u "api_user:api_password" https://example.com/rest/catalog

# Unauthenticated (when auth is disabled)
curl https://example.com/rest/catalog
```

**Response on authentication failure:** HTTP `403 Forbidden`

### Cache Bypass

```bash
# Disable cache for this request
curl https://example.com/rest/catalog?no_cache=1

# Force cache update
curl https://example.com/rest/catalog?update_cache=1
```

---

## Catalog

The catalog controller provides search and filter functionality for touristic products.

### `GET/POST /catalog`

Returns a structured catalog with filter sections and products.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `pm-c[{field}]` | string | Category filter (see [MongoDB Search](search-mongodb-api.md)) |
| `pm-dr` | string | Departure date range |
| `pm-o` | string | Sort order |
| Other `pm-*` | various | Any MongoDB search parameter |

**Response:**

```json
{
  "error": false,
  "payload": {
    "sections": { ... }
  },
  "msg": null
}
```

### `GET/POST /catalog/search`

Executes a search query and returns matching products.

**Parameters:** Same as `/catalog`

**Response:**

```json
{
  "error": false,
  "payload": [ ... ],
  "msg": null
}
```

### `GET/POST /catalog/convertSearchQueryToPriceQueryString`

Converts a search query string into a price query string.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `search_query` | string | Yes | URL query string to convert |

**Response:**

```json
{
  "error": false,
  "payload": {
    "price_query": "pm-pr=500-2000&pm-du=7-14"
  },
  "msg": null
}
```

---

## Media Object

CRUD operations for media objects (touristic products).

### `GET/POST /mediaObject`

Lists all media objects or returns a single one by ID.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `id` | integer | Return single object by ID |
| `readRelations` | boolean | Include related objects |
| `apiTemplate` | string | Render via API template |
| `start` | integer | Pagination offset |
| `limit` | integer | Pagination limit |
| `properties` | array | Return only specific properties |
| Other params | various | Passed as `WHERE` conditions to `loadAll()` |

**Response (list):**

```json
[
  { "id": 123, "name": "Trip to Mallorca", "code": "ABC123", ... },
  { "id": 456, "name": "Hotel Paradise", "code": "DEF456", ... }
]
```

**Response (single with `id`):**

```json
{
  "id": 123,
  "name": "Trip to Mallorca",
  "code": "ABC123",
  ...
}
```

### `POST /mediaObject/getByRoute`

Finds a media object by its pretty URL route.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `route` | string | Yes | Pretty URL path |
| `id_object_type` | integer | Yes | Object type ID |
| `language` | string | No | Language code (default: `"de"`) |
| `visibility` | integer | Yes | Visibility level |
| `readRelations` | boolean | No | Include related objects |
| `apiTemplate` | string | No | Render via API template |

**Example:**

```bash
curl -X POST https://example.com/rest/mediaObject/getByRoute \
  -d '{"route":"/trip/beautiful-mallorca","id_object_type":123,"visibility":30}'
```

### `POST /mediaObject/getByCode`

Finds media objects by product code.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `code` | string | Yes | Product code |

**Response:**

```json
[
  { "id": 123, "code": "ABC123", "name": "Trip to Mallorca", ... }
]
```

---

## IBE (Internet Booking Engine)

Endpoints for the IBE3 booking system integration.

### `GET/POST /ibe/pressmind_ib3_v2_get_touristic_object`

Returns the complete touristic object data for a specific booking configuration.

**Parameters (in `data.params`):**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `imo` | integer | Yes | Media object ID |
| `idbp` | integer | Yes | Booking package ID |
| `idd` | integer | Yes | Date ID |
| `ida` | integer | No | Agency ID |
| `iic` | string | No | IBE client identifier |
| `iho` | array | No | Housing option IDs |
| `ido` | integer | No | Option ID |

**Response:**

```json
{
  "success": true,
  "data": {
    "dates": [ ... ],
    "housing_packages": [ ... ],
    "options": [ ... ],
    "transports": [ ... ],
    "insurances": [ ... ],
    "startingpoints": [ ... ]
  },
  "msg": null,
  "code": null
}
```

### `GET/POST /ibe/pressmind_ib3_v2_get_starting_point_options`

Returns available starting point (departure) options.

**Parameters (in `data`):**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_starting_point` | integer | Yes | Starting point ID |
| `limit` | integer | No | Results per page (default: 10) |
| `start` | integer | No | Offset (default: 0) |
| `zip` | string | No | ZIP code filter |
| `radius` | integer | No | Radius in km (with `zip`) |
| `iic` | string | No | IBE client identifier |
| `order_by_code_list` | array | No | Custom sort order by codes |

**Response:**

```json
{
  "success": true,
  "data": {
    "total": 42,
    "starting_point_options": [
      { "id": 1, "city": "Berlin", "code": "BER", ... },
      { "id": 2, "city": "Munich", "code": "MUC", ... }
    ]
  }
}
```

### `GET/POST /ibe/pressmind_ib3_v2_get_starting_point_option_by_id`

Returns a single starting point option by ID.

**Parameters (in `data`):**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_starting_point_option` | integer | Yes | Starting point option ID |
| `ibe_client` | string | No | IBE client identifier |

### `GET/POST /ibe/pressmind_ib3_v2_find_pickup_service`

Finds pickup services for a starting point, optionally filtered by ZIP code.

**Parameters (in `data`):**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_starting_point` | integer | Yes | Starting point ID |
| `zip` | string | No | ZIP code filter |
| `iic` | string | No | IBE client identifier |

### `GET/POST /ibe/getCheapestPrice`

Returns a specific cheapest price entry.

**Parameters (in `data`):**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_cheapest_price` | integer | Yes | CheapestPriceSpeed ID |

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 12345,
    "id_media_object": 100,
    "price_total": 899.00,
    "duration": 7,
    "date_departure": "2026-06-15",
    "occupancy": 2,
    ...
  },
  "msg": null
}
```

### `GET/POST /ibe/getRequestableOffer`

Returns a requestable offer with all available options.

**Parameters (in `data`):**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_cheapest_price` | integer | Yes | CheapestPriceSpeed ID |

**Response:**

```json
{
  "success": true,
  "CheapestPriceSpeed": { ... },
  "Options": [ ... ],
  "alternativeOptions": [ ... ]
}
```

### `GET/POST /ibe/pressmind_ib3_v2_test`

Health check endpoint for IBE connectivity.

**Response:**

```json
{
  "success": true,
  "msg": "OK",
  "debug": { ... }
}
```

---

## Entrypoint

Provides calendar, pricing, and booking link functionality.

### `GET/POST /entrypoint/getBookingLink`

Generates a booking link for a specific offer.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_offer` | integer | Yes | Offer ID |
| `pax` | integer | Yes | Number of passengers |
| `ida` | string | No | Agency identifier |

**Response:**

```json
{
  "error": false,
  "payload": {
    "url": "https://ibe.example.com/booking?imo=123&...",
    "available": true
  },
  "msg": null
}
```

### `GET/POST /entrypoint/calendar`

Returns the availability calendar for a media object.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_media_object` | integer | Yes | Media object ID |
| `filter_transport_type` | string | No | Filter by transport type |
| `agency` | integer | No | Agency ID |

**Response:**

```json
{
  "error": false,
  "payload": {
    "months": { ... },
    "dates": [ ... ]
  },
  "msg": null
}
```

### `GET/POST /entrypoint/calendarMap`

Returns the filter options available for a product's calendar (departure cities, housing packages, airports, durations).

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_media_object` | integer | Yes | Media object ID |

**Response:**

```json
{
  "error": false,
  "payload": {
    "startingpoint_id_cities": [123, 456],
    "housing_package_id_names": ["hotel_abc", "hotel_xyz"],
    "id_housing_packages": [10, 20],
    "airports": ["FRA", "MUC", "BER"],
    "durations": [7, 10, 14]
  },
  "msg": null
}
```

### `GET/POST /entrypoint/price`

Returns the cheapest price for a media object with optional filters.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_media_object` | integer | Yes | Media object ID |
| Additional `pm-*` | various | No | CheapestPrice filter parameters |

**Response:**

```json
{
  "error": false,
  "payload": {
    "price_total": 899.00,
    "duration": 7,
    "date_departure": "2026-06-15",
    ...
  },
  "msg": null
}
```

---

## Insurance

### `GET/POST /touristic/insurance/calculatePrices`

Calculates insurance prices for a specific trip configuration.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_media_object` | integer | Yes | Media object ID |
| `price_person` | float | Yes | Price per person |
| `duration_nights` | integer | Yes | Trip duration in nights |
| `date_start` | string | Yes | Start date (`YYYY-MM-DD`) |
| `date_end` | string | Yes | End date (`YYYY-MM-DD`) |
| `age_person` | integer | No | Age of person (default: 18) |
| `total_number_of_participants` | integer | No | Total participants (default: 0) |

**Response:**

```json
[
  {
    "id": 1,
    "name": "Travel Cancellation Insurance",
    "price": 49.00,
    "price_per_person": 24.50,
    ...
  }
]
```

---

## Import

Endpoints for triggering and managing data imports.

### `GET/POST /import/addToQueue`

Adds a media object to the import queue.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_media_object` | integer | Yes* | Media object ID (*or `code`) |
| `code` | string | Yes* | Product code (*or `id_media_object`) |
| `queue_action` | string | No | `"mediaobject"` (default) or `"touristic"` |

**Response:**

```json
{
  "success": true,
  "msg": "Added to queue",
  "data": {
    "added": 1,
    "skipped": 0,
    "code": "ABC123"
  }
}
```

### `GET/POST /import/fullimport`

Triggers a full import of all media objects.

**Response:**

```json
{
  "success": true,
  "msg": "Full import started",
  "data": null
}
```

### `GET/POST /import/fullimportTouristic`

Triggers a full import of touristic data only (prices, dates, availability).

**Response:**

```json
{
  "success": true,
  "msg": "Full touristic import started",
  "data": null
}
```

### `POST /import/touristicByCode`

Imports touristic data for a specific product code with custom booking package data.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `code` | string | Yes | Product code |
| `booking_packages` | array | Yes | Array of booking package data |

**Response:**

```json
{
  "success": true,
  "msg": "Import completed",
  "data": {
    "code": "ABC123",
    "processed": 3,
    "results": [
      { "id_media_object": 100, "success": true, "errors": [] },
      { "id_media_object": 200, "success": true, "errors": [] }
    ]
  }
}
```

---

## Redis Cache

Endpoints for inspecting and managing the Redis cache.

### `GET /redis/getKeys`

Lists all cache keys.

**Response:**

```json
{
  "total_key_count": 156,
  "keys": [
    "pm:mydb:REST_abc123...",
    "pm:mydb:SEARCH_def456...",
    ...
  ]
}
```

### `GET /redis/getKeyValue`

Returns the cached value for a specific key.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `key` | string | Yes | Cache key |

**Response:** The decoded JSON value stored in the cache.

### `GET /redis/getInfo`

Returns metadata for a specific cache key.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `key` | string | Yes | Cache key |

**Response:**

```json
{
  "key": "pm:mydb:REST_abc123...",
  "info": { "type": "REST", "classname": "...", "method": "..." },
  "date": "2026-02-08T10:00:00+0100",
  "idle": 3600
}
```

---

## System

### `GET /system/updateTags`

Updates tags/fulltext index for a specific object type.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id_object_type` | integer | Yes | Object type ID |

**Response:**

```json
{
  "success": true,
  "msg": "Tags updated for object type 123"
}
```

---

## ORM Resource Endpoints (Dynamic Routing)

The following controllers inherit from `AbstractController` and automatically provide `listAll()` and `read()` operations via dynamic routing. They expose ORM objects as REST resources.

### Common Parameters (all resource endpoints)

| Parameter | Type | Description |
|---|---|---|
| `id` | integer | Return single object by ID |
| `readRelations` | boolean | Include related objects in response |
| `apiTemplate` | string | Render output via a named API template |
| `start` | integer | Pagination offset |
| `limit` | integer | Number of results |
| `properties` | array | Return only specific properties |

Any additional parameters are passed as `WHERE` conditions to the ORM `loadAll()` method.

### Available Resource Endpoints

| Endpoint | HTTP | ORM Object | Description |
|---|---|---|---|
| `/categoryTree` | GET/POST | `ORM\Object\CategoryTree` | Category trees |
| `/cheapestPriceSpeed` | GET/POST | `ORM\Object\CheapestPriceSpeed` | Cheapest price index |
| `/dataView` | GET/POST | `ORM\Object\DataView` | Data views |
| `/image` | GET/POST | `ORM\Object\MediaObject\DataType\Picture` | Image metadata |
| `/route` | GET/POST | `ORM\Object\Route` | URL routes |
| `/touristic/booking/package` | GET/POST | `ORM\Object\Touristic\Booking\Package` | Booking packages |
| `/touristic/date` | GET/POST | `ORM\Object\Touristic\Date` | Travel dates |
| `/touristic/insurance` | GET/POST | `ORM\Object\Touristic\Insurance` | Insurance products |
| `/touristic/insurance/group` | GET/POST | `ORM\Object\Touristic\Insurance\Group` | Insurance groups |
| `/touristic/option` | GET/POST | `ORM\Object\Touristic\Option` | Touristic options |
| `/touristic/pickupservice` | GET/POST | `ORM\Object\Touristic\Pickupservice` | Pickup services |
| `/touristic/seasonalPeriod` | GET/POST | `ORM\Object\Touristic\SeasonalPeriod` | Seasonal periods |
| `/touristic/startingpoint` | GET/POST | `ORM\Object\Touristic\Startingpoint` | Departure points |
| `/touristic/transport` | GET/POST | `ORM\Object\Touristic\Transport` | Transport options |
| `/itinerary/variant` | GET/POST | `ORM\Object\Itinerary\Variant` | Itinerary variants |

### Example Requests

```bash
# List all category trees
curl https://example.com/rest/categoryTree

# Get a specific category tree by ID
curl https://example.com/rest/categoryTree?id=5

# List travel dates with pagination
curl https://example.com/rest/touristic/date?start=0&limit=20

# Get a booking package with relations
curl https://example.com/rest/touristic/booking/package?id=100&readRelations=true

# Filter transports by media object
curl https://example.com/rest/touristic/transport?id_media_object=123
```

---

## Custom Controllers

The SDK supports custom REST controllers under the `\Custom\REST\Controller\` namespace. Custom controllers take priority over SDK controllers for the same URI.

### Registration

Custom controllers are automatically registered when the class exists:

```php
// If \Custom\REST\Controller\MyEndpoint exists:
// GET/POST /myEndpoint → MyEndpoint::index()
```

### Example Custom Controller

```php
namespace Custom\REST\Controller;

class Availability
{
    public function index($parameters)
    {
        $id = $parameters['id_media_object'] ?? null;
        
        // Custom availability logic
        return [
            'available' => true,
            'rooms' => 5,
            'last_updated' => date('c')
        ];
    }
}
```

Accessible at: `GET/POST /availability?id_media_object=123`

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|---|---|
| `200` | Success |
| `204` | No content (OPTIONS/HEAD) |
| `302` | Redirect (with `Location` header) |
| `403` | Authentication failed |
| `404` | Route not found |
| `405` | Method not allowed |
| `500` | Internal server error |

### Error Response Format

```json
{
  "error": true,
  "msg": "Description of the error"
}
```

---

## Endpoint Summary

| Method | Endpoint | Controller | Description |
|---|---|---|---|
| GET/POST | `/catalog` | Catalog | Product catalog with filters |
| GET/POST | `/catalog/search` | Catalog | Search products |
| POST | `/mediaObject/getByRoute` | MediaObject | Find by pretty URL |
| POST | `/mediaObject/getByCode` | MediaObject | Find by product code |
| GET/POST | `/mediaObject` | MediaObject | List/read media objects |
| GET/POST | `/entrypoint/getBookingLink` | Entrypoint | Generate booking link |
| GET/POST | `/entrypoint/calendar` | Entrypoint | Availability calendar |
| GET/POST | `/entrypoint/calendarMap` | Entrypoint | Calendar filter options |
| GET/POST | `/entrypoint/price` | Entrypoint | Cheapest price lookup |
| GET/POST | `/ibe/pressmind_ib3_v2_get_touristic_object` | Ibe | Full touristic object |
| GET/POST | `/ibe/pressmind_ib3_v2_get_starting_point_options` | Ibe | Departure options |
| GET/POST | `/ibe/pressmind_ib3_v2_get_starting_point_option_by_id` | Ibe | Single departure option |
| GET/POST | `/ibe/pressmind_ib3_v2_find_pickup_service` | Ibe | Pickup services |
| GET/POST | `/ibe/getCheapestPrice` | Ibe | Specific cheapest price |
| GET/POST | `/ibe/getRequestableOffer` | Ibe | Requestable offer |
| GET/POST | `/ibe/pressmind_ib3_v2_test` | Ibe | IBE health check |
| GET/POST | `/touristic/insurance/calculatePrices` | Insurance | Calculate insurance |
| GET/POST | `/import/addToQueue` | Import | Queue import |
| GET/POST | `/import/fullimport` | Import | Full import |
| GET/POST | `/import/fullimportTouristic` | Import | Touristic import |
| POST | `/import/touristicByCode` | Import | Import by code |
| GET | `/redis/getKeys` | Redis | List cache keys |
| GET | `/redis/getKeyValue` | Redis | Get cached value |
| GET | `/redis/getInfo` | Redis | Cache key metadata |
| GET | `/system/updateTags` | System | Update fulltext index |
| GET/POST | `/categoryTree` | CategoryTree | Category trees |
| GET/POST | `/touristic/date` | Date | Travel dates |
| GET/POST | `/touristic/option` | Option | Touristic options |
| GET/POST | `/touristic/transport` | Transport | Transport options |
| GET/POST | `/touristic/startingpoint` | Startingpoint | Departure points |
| GET/POST | `/touristic/booking/package` | Package | Booking packages |
| GET/POST | `/touristic/insurance` | Insurance | Insurance products |
| GET/POST | `/touristic/insurance/group` | Group | Insurance groups |
| GET/POST | `/touristic/pickupservice` | Pickupservice | Pickup services |
| GET/POST | `/touristic/seasonalPeriod` | SeasonalPeriod | Seasonal periods |
| GET/POST | `/itinerary/variant` | Variant | Itinerary variants |
| GET/POST | `/route` | Route | URL routes |
| GET/POST | `/image` | Image | Image metadata |
| GET/POST | `/dataView` | DataView | Data views |

---

[← Back to Architecture](architecture.md) | [MongoDB Search API →](search-mongodb-api.md)
