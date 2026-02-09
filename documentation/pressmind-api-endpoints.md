# Pressmind Webcore API Endpoints

This documentation describes all external Pressmind Webcore API endpoints used by the SDK.

## Overview

| Property | Value                                                |
|----------|------------------------------------------------------|
| **Base URL** | `https://webcore.pressmind.io/v2-*/rest/`            |
| **URL Format** | `{base_url}{api_key}/{controller}/{action}?{params}` |
| **Authentication** | HTTP Basic Auth with `api_user` and `api_password`   |
| **Content-Type** | `application/json; charset=utf-8`                    |
| **Encoding** | gzip                                                 |

### Example URL

```
https://webcore.pressmind.io/v2-23/rest/abc123xyz/Text/getById?ids[]=123456&cache=0
```

## Configuration

The API credentials are configured in `config.json`:

```json
{
  "rest": {
    "client": {
      "api_key": "YOUR_API_KEY",
      "api_user": "YOUR_API_USER",
      "api_password": "YOUR_API_PASSWORD"
    }
  }
}
```

## Available Endpoints

### ObjectType/getById

Retrieves ObjectType schema definitions by ID(s).

**Used for:** Schema import and database migration

**SDK Class:** `Pressmind\Import\MediaObjectType`, `Pressmind\System\SchemaMigrator`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/ObjectType/getById?ids[]=169&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

### Text/search

Searches for Media Objects by various criteria.

**Used for:** Media Object import (full import)

**SDK Class:** `Pressmind\Import\Import`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Text/search?id_object_type=169&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `id_object_type` - Filter by object type ID

---

### Text/getById

Retrieves detailed Media Object data by ID(s).

**Used for:** Single Media Object import

**SDK Class:** `Pressmind\Import\Import`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Text/getById?ids[]=123456&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `ids[]` - Array of Media Object IDs

---

### Text/getByFilterId

Retrieves Media Objects matching a Powerfilter.

**Used for:** Powerfilter-based import

**SDK Class:** `Pressmind\Import\Powerfilter`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Text/getByFilterId?id_filter=42&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `id_filter` - Powerfilter ID

---

### Category/all

Retrieves all category trees or specific categories by ID.

**Used for:** Category tree import

**SDK Class:** `Pressmind\Import\CategoryTree`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Category/all?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `ids[]` - (optional) Array of category tree IDs

---

### Itinerary/get

Retrieves itinerary data for a Media Object.

**Used for:** Itinerary/route import

**SDK Class:** `Pressmind\Import\Itinerary`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Itinerary/get?id_media_object=123456&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `id_media_object` - Media Object ID

---

### StartingPoint/getById

Retrieves starting point options by ID(s).

**Used for:** Starting point/departure location import

**SDK Class:** `Pressmind\Import\StartingPointOptions`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/StartingPoint/getById?ids[]=1&ids[]=2&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `ids[]` - Array of starting point IDs

---

### EarlyBird/search

Searches for early bird discount configurations.

**Used for:** Early bird discount import

**SDK Class:** `Pressmind\Import\EarlyBird`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/EarlyBird/search?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

### Filter/search

Searches for filter definitions (Powerfilters, DataViews).

**Used for:** Powerfilter and DataView import

**SDK Class:** `Pressmind\Import\Powerfilter`, `Pressmind\Import\DataView`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Filter/search?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

### Ports/getAll

Retrieves all port definitions (for cruise itineraries).

**Used for:** Port data import

**SDK Class:** `Pressmind\Import\Port`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Ports/getAll?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

### Brand/search

Searches for brand definitions.

**Used for:** Brand data import

**SDK Class:** `Pressmind\Import\Brand`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Brand/search?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

### Saison/search

Searches for season definitions.

**Used for:** Season data import

**SDK Class:** `Pressmind\Import\Season`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-23/rest/{API_KEY}/Saison/search?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

## SDK Classes Reference

| Class | Purpose |
|-------|---------|
| `Pressmind\REST\Client` | Central REST client for all API calls |
| `Pressmind\Import\Import` | Media Object import |
| `Pressmind\Import\MediaObjectType` | ObjectType schema import |
| `Pressmind\Import\CategoryTree` | Category tree import |
| `Pressmind\Import\Itinerary` | Itinerary import |
| `Pressmind\Import\StartingPointOptions` | Starting point import |
| `Pressmind\Import\EarlyBird` | Early bird discount import |
| `Pressmind\Import\Powerfilter` | Powerfilter import |
| `Pressmind\Import\DataView` | DataView import |
| `Pressmind\Import\Port` | Port data import |
| `Pressmind\Import\Brand` | Brand import |
| `Pressmind\Import\Season` | Season import |

## Error Handling

The API returns standard HTTP status codes:

- `200` - Success
- `401` - Unauthorized (invalid credentials)
- `404` - Not found
- `500` - Server error

The SDK throws exceptions for non-200 responses. Check the response body for error details.

## Notes

- All requests include `cache=0` parameter to bypass server-side caching
- The SDK uses gzip encoding for responses
- SSL verification is disabled in the SDK client (for development environments)
