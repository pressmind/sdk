# Pressmind Webcore API Endpoints

This documentation describes all external Pressmind Webcore API endpoints used by the SDK.

## Overview

| Property | Value                                                |
|----------|------------------------------------------------------|
| **Base URL** | `https://webcore.pressmind.io/v2-33/rest/`           |
| **URL Format** | `{base_url}{api_key}/{controller}/{action}?{params}` |
| **Authentication** | HTTP Basic Auth with `api_user` and `api_password`   |
| **Content-Type** | `application/json; charset=utf-8`                    |
| **Encoding** | gzip                                                 |

### Example URL

```
https://webcore.pressmind.io/v2-33/rest/abc123xyz/Text/getById?ids[]=123456&cache=0
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/ObjectType/getById?ids[]=169&cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Text/search?id_object_type=169&cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Text/getById?ids[]=123456&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `ids[]` - Array of Media Object IDs

Since v2-33, fields whose ObjectType is `icon` return either `null` or this payload:

```json
{
  "id": "ship",
  "name": "Ship",
  "slug": "ship",
  "url": "https://example.test/icons/ship.svg",
  "mime": "image/svg+xml",
  "style": "regular",
  "variants": [
    {
      "style": "solid",
      "url": "https://example.test/icons/ship-solid.svg",
      "mime": "image/svg+xml"
    }
  ]
}
```

The SDK stores these values through `Pressmind\ORM\Object\MediaObject\DataType\Icon`.

The technical field type is `icon` (one selected icon per field and section).
Standalone fields are exposed as a relation array, for example
`$data->symbol_default[0]->url`; the API `id` is stored as `id_icon`.
`variants` retains every style/URL/MIME entry returned by the API.

Icons inside `repeated_form` fields use the same payload. The SDK exposes these
cells with `datatype = 'icon'`, `value_string = null`, and the structured payload
in `value_icon` (including the original `id` key):

```php
$column = $data->leistungen_default[0]->rows[0]->columns[1];
$url = $column->value_icon['url'] ?? null;
$variants = $column->value_icon['variants'] ?? [];
```

An empty icon has `value_icon = null`. Icon assets remain at their API URLs;
the SDK does not download them or select a different variant. `Repeated_form::asHTML()`
renders the selected URL as an image with the icon name as alternative text,
escapes both attributes, and omits images with missing or non-HTTP(S) URLs.
Existing text, HTML, and IBE teaser cells retain their previous behavior.
Loading and deleting standalone icon and repeated-form relations is scoped to the
media data object's language; field/section separation continues to use `var_name`.
The language correction also applies to repeated forms without icon cells.

**Upgrade for repeated-form icons:**

1. Back up the database and retain the previous SDK revision.
2. Update the SDK, then run `php bin/database-integrity-check --non-interactive --static-only`
   in the bootstrapped application. This adds nullable `value_icon` (`LONGTEXT`,
   ORM type `json`) to `pmt2core_media_object_repeated_form_row_columns`.
3. Regenerate affected ObjectTypes and check their schema using the existing
   application import/integrity commands.
4. Reimport affected media objects using `mediaobject <ID> --force` in the application's
   import CLI, or run a full import. Previously
   discarded icons can only be recovered from the API. Refresh application caches
   through the normal import workflow.
5. Verify standalone icons and repeated-form icons on DEV, including replacement,
   removal, language/section separation, variants, and HTML output.

The schema extension and changed HTML output require a coordinated release.
Rollback restores the previous SDK revision and database backup together;
do not run the old SDK's schema repair against the upgraded database.

---

### Text/getByFilterId

Retrieves Media Objects matching a Powerfilter.

**Used for:** Powerfilter-based import

**SDK Class:** `Pressmind\Import\Powerfilter`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Text/getByFilterId?id_filter=42&cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Category/all?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `ids[]` - (optional) Array of category tree IDs

Since v2-33, both the tree root and every nested item may contain the same optional `icon` payload described under `Text/getById`. The SDK persists it as structured JSON on the corresponding category tree row.

---

### Itinerary/get

Retrieves itinerary data for a Media Object.

**Used for:** Itinerary/route import

**SDK Class:** `Pressmind\Import\Itinerary`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Itinerary/get?id_media_object=123456&cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

**Parameters:**
- `id_media_object` - Media Object ID

Since v2-33, every itinerary port may include `coordinates` as `{ "lat": 53.5439, "lng": 9.9666 }` or `null`. The SDK normalizes the values into nullable `lat` / `lng` properties on `Itinerary\Step\Port` for both dated and dateless itineraries.

---

### StartingPoint/getById

Retrieves starting point options by ID(s).

**Used for:** Starting point/departure location import

**SDK Class:** `Pressmind\Import\StartingPointOptions`

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/StartingPoint/getById?ids[]=1&ids[]=2&cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/EarlyBird/search?cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Filter/search?cache=0" \
  -H "Content-Type: application/json; charset=utf-8" \
  -u "{API_USER}:{API_PASSWORD}"
```

---

### Ports/getAll

Retrieves all port definitions (for cruise itineraries).

**Used for:** Port data import

**SDK Class:** `Pressmind\Import\Port`

Since v2-33, a port may contain `coordinates` as `{ "lat": 53.5439, "lng": 9.9666 }` or `null`. The SDK exposes the normalized values through `Port::$lat`, `Port::$lng`, and `Port::getCoordinates()`.

**Example Request:**

```bash
curl -X GET \
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Ports/getAll?cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Brand/search?cache=0" \
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
  "https://webcore.pressmind.io/v2-33/rest/{API_KEY}/Saison/search?cache=0" \
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
