# URL Strategies (`data.media_types_pretty_url`)

This page describes how to configure SEO-friendly URLs (routes) for media objects. Routes are stored in `pmt2core_routes` and used for routing and lookups. The configuration key is **`data.media_types_pretty_url`**.

| Property | Value |
|---|---|
| **Config path** | `data.media_types_pretty_url` |
| **Type** | `object` (legacy) or `array` (new format) |
| **Default** | `[]` (array format, see `config.default.json`) |
| **Used in** | `ORM\Object\MediaObject.php`, `Import.php`, `CLI\RebuildRoutesCommand` |

---

## Recommendation for new setups

**Use the `channel` strategy** when your pressmind project has URL Steuerung (channel-based URLs) enabled. The URL is then taken 1:1 from the pressmind API response (`pretty_urls` per channel), so URLs stay in sync with pressmind and can be managed centrally there. For older setups or when channel URLs are not available, use `unique` or `count-up` with field-based generation.

---

## Strategy overview

| Strategy | URL source | When to use |
|----------|------------|-------------|
| **channel** *(recommended for new setups)* | pressmind API `pretty_urls` for a configured channel | URL Steuerung in pressmind; one config entry per object type with `id_channel` |
| **unique** | Built from object fields | SEO slugs from name/code; import fails if the same URL already exists |
| **count-up** | Built from object fields | Same as unique, but duplicates get a numeric suffix (`-001`, `-002`, …) instead of failing |

---

## Product lifecycle and SEO

Products are often **re-released per season** (e.g. "Spanien Entdecken" 2022, 2023, 2024) with the same or similar name but different content. If two such variants are marketed at the same time, the choice of URL strategy directly affects **SEO** (link equity, indexing) and **operational effort**.

| Scenario | Strategy: count-up | Strategy: unique | Single URL for several variants |
|----------|--------------------|------------------|----------------------------------|
| **Example (same product, different seasons)** | Saison 2022: `/reise/spanien-entdecken/` · Saison 2023: `/reise/spanien-entdecken-001/` · Saison 2024: `/reise/spanien-entdecken-002/` | Only one URL exists. A second product with the same slug is **rejected**; the user must change the URL in pressmind. | All seasons under one URL: `/reise/spanien-entdecken/` (not provided by SDK, see below). |
| **Behaviour** | If a URL already exists, it is "counted up" (`-001`, `-002`, …). | URL must be unique in the system; duplicates cannot be published. | Multiple product variants are served under one URL. |
| **SEO** | Each variant has its own URL. After the campaign ends, that URL is no longer used; **link equity** is hard to accumulate or only short-term. | One URL per product; user is forced to differentiate (e.g. by season in the slug), so URLs can stay meaningful long-term. | One stable URL accumulates all link equity; best for SEO, but requires custom logic. |
| **Effort** | Low (no config change, no manual URL). | Low (system enforces uniqueness). | High: custom detail template and search/routing logic; only for strongly SEO-driven projects. |

**Single URL for multiple variants ("none" style)** is **not implemented** in the SDK. Showing several product variants (e.g. seasons) under one route would require your own detail template and search logic (e.g. resolve by product code + season or date). Use this approach only when SEO is critical and you can invest in the implementation.

---

## Strategy 1: `unique`

- **Behaviour:** The URL is built from the configured `fields` (e.g. `name`, `code`), joined by `separator`, with `prefix`/`suffix` applied. Non-word characters are normalized to hyphens. **The URL must exist only once in the system.** If a route with the same URL already exists, the import throws an exception and the product cannot be published until the URL is changed (e.g. in pressmind or via channel strategy).
- **Config (new format):** `strategy: "unique"`, plus `fields` or `field`, `separator`, `prefix`, `suffix`. No `id_channel`.
- **Example URL:** With `prefix: "/"`, `fields: ["name"]`, a product named "Toskana Rundreise" → `/toskana-rundreise`.
- **Exception:** `Route with url … already exists and route-building strategy is set to unique in config. Please check your configuration file.`
- **Effort:** Low (no manual count-up; system enforces uniqueness).

## Strategy 2: `count-up`

- **Behaviour:** Same URL building as `unique`. If the generated URL already exists, the code appends `-001`, `-002`, … until a free route is found. No exception on duplicates. **Each product variant gets its own URL** (e.g. `/reise/spanien-entdecken/`, `/reise/spanien-entdecken-001/`, `/reise/spanien-entdecken-002/`).
- **Config (new format):** `strategy: "count-up"`, plus `fields`/`field`, `separator`, `prefix`, `suffix`.
- **Example URL:** First product "Toskana Rundreise" → `/toskana-rundreise`; second with same name → `/toskana-rundreise-001`.
- **Multi-field example:** `fields: ["code", "name"]` with `code: "020906"`, `name: "Toskana Rundreise"` → `/020906-toskana-rundreise`.
- **SEO note:** After the marketing period, a variant’s URL is often no longer used; building long-term link equity on that URL is difficult. Prefer **channel** or **unique** (with distinct slugs) if stable, SEO-strong URLs are important.
- **Effort:** Low (no manual URL management).

## Strategy 3: `channel` (recommended for new setups)

- **Behaviour:** The URL is not built from fields. During import, the SDK uses the API response field `pretty_urls` (array of `{ id_channel, channel_name, url }`). The entry whose `id_channel` matches the configured `id_channel` is used; its `url` is taken 1:1 (with optional `prefix`/`suffix`). If no API data is available (e.g. when running `RebuildRoutesCommand`), no route is generated and a warning is emitted.
- **Prerequisites:** In pressmind, URL Steuerung must be enabled and the channel must be assigned to the media object so that `pretty_urls` contains an entry for your `id_channel`.
- **Config (new format):** `strategy: "channel"`, `id_channel: <number>`, optional `prefix`, `suffix`. `language: null` means the entry applies to all languages.
- **Example URL:** If the API returns `"url": "/mtf-musical-020906"` for the configured channel, the stored route is that path (plus any prefix/suffix).
- **Warnings and errors:**
  - Missing config: `URL strategy "channel" requires "id_channel" to be configured in media_types_pretty_url for object type …`
  - Channel not in API: `URL strategy "channel": no entry found for id_channel … in pretty_urls of media object …. Check that the channel is assigned in pressmind.`
  - Empty URL: `URL strategy "channel": channel id … found in pretty_urls but url is empty for media object …`
  - RebuildRoutesCommand: `MediaObject #… (type …): URL strategy "channel" requires a full re-import to rebuild routes. Run the importer instead.`

---

## Supported field types for `fields` (strategies `unique` and `count-up`)

| Field type | Example | Supported |
|------------|--------|-----------|
| MediaObject properties | `name`, `code` | Yes |
| Content fields of type `text` / `plaintext` | `headline` | Yes (HTML is stripped automatically) |
| `categorytree` | `zielgebiet` | No (value is ignored) |
| `objectlink` / relation | e.g. `veranstalter` | No |

Use `fields: ["code", "name"]` or `fields: ["headline"]` etc. as needed. For `headline`, the value is taken from the content and HTML tags are removed before building the slug.

---

## Legacy format vs. new format

- **Legacy:** `media_types_pretty_url` is an object keyed by `id_object_type` (e.g. `"607": { "fields": ["name"], "strategy": "unique", … }`). No per-language entries.
- **New format:** `media_types_pretty_url` is an array of objects. Each object has `id_object_type` and optionally `language`. Use `language: null` to apply one entry to all languages. Supports `id_channel` for the channel strategy.

---

## Warnings and errors (summary)

| Message / Exception | Cause | What to do |
|--------------------|--------|------------|
| `URL strategy "channel" requires "id_channel" to be configured …` | Channel strategy is set but `id_channel` is missing in config | Add `id_channel` (pressmind channel ID) to the config entry |
| `no entry found for id_channel … in pretty_urls …` | Channel strategy and `id_channel` are set, but the API response has no `pretty_urls` entry for that channel | Assign the channel to the media object in pressmind (URL Steuerung) |
| `channel id … found in pretty_urls but url is empty` | API returns the channel but with an empty `url` | Fix the URL in pressmind for that channel |
| `Route with url … already exists … strategy is set to unique` | Duplicate URL with `unique` strategy | Use `count-up` or make names/codes unique, or change the URL in pressmind (channel strategy) |
| RebuildRoutesCommand: `URL strategy "channel" requires a full re-import …` | RebuildRoutesCommand has no API data; channel URLs cannot be rebuilt | Run a full import instead of rebuild routes |

---

## Full configuration examples

### New format (array)

```json
"media_types_pretty_url": [
  {
    "id_object_type": 607,
    "language": null,
    "strategy": "channel",
    "id_channel": 1053,
    "prefix": "",
    "suffix": ""
  },
  {
    "id_object_type": 607,
    "language": null,
    "strategy": "count-up",
    "fields": ["code", "name"],
    "separator": "-",
    "prefix": "/",
    "suffix": ""
  },
  {
    "id_object_type": 607,
    "language": "de",
    "strategy": "unique",
    "fields": ["headline"],
    "separator": "-",
    "prefix": "/reise",
    "suffix": ""
  }
]
```

### Legacy format (object)

```json
"media_types_pretty_url": {
  "607": {
    "fields": ["name"],
    "separator": "-",
    "strategy": "unique",
    "prefix": "/",
    "suffix": ""
  }
}
```

### Example URLs

- **Channel:** API returns `url: "/mtf-musical-020906"` → route stored as `/mtf-musical-020906` (or with prefix/suffix if set).
- **Unique/count-up with fields:** `prefix: "/"`, `fields: ["code", "name"]`, `code: "020906"`, `name: "Toskana Rundreise"` → `/020906-toskana-rundreise`.
- **Unique with headline:** `fields: ["headline"]`, headline content "Beautiful Mallorca Holiday" → slug like `/beautiful-mallorca-holiday` (HTML stripped).

---

## Related

- [Import Process](import-process.md) — routes are built during media object import
- [CLI Reference](cli-reference.md) — `RebuildRoutesCommand` rebuilds routes (channel strategy requires full re-import)
- [Sections, Languages & Misc](config-sections-languages-misc.md) — other `data.*` options (sections, languages, schema_migration)
