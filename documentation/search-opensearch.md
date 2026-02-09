# OpenSearch Configuration & MongoDB Integration

[← Back to MongoDB Search API](search-mongodb-api.md) | [→ MongoDB Index Configuration](search-mongodb-index-configuration.md)

---

## Table of Contents

- [Overview](#overview)
- [Architecture: OpenSearch + MongoDB](#architecture-opensearch--mongodb)
  - [How It Works](#how-it-works)
  - [Why Two Search Engines?](#why-two-search-engines)
  - [Data Flow Diagram](#data-flow-diagram)
- [Configuration](#configuration)
  - [Basic Settings](#basic-settings)
  - [Connection Settings](#connection-settings)
  - [Cluster Settings](#cluster-settings)
  - [Index Field Mapping](#index-field-mapping)
  - [Object Type Mapping](#object-type-mapping)
  - [Field Types & Boosting](#field-types--boosting)
  - [Language Support](#language-support)
- [Index Management](#index-management)
  - [Index Template Creation](#index-template-creation)
  - [Index Naming Convention](#index-naming-convention)
  - [Automatic Analyzers](#automatic-analyzers)
  - [Index Lifecycle](#index-lifecycle)
- [Fulltext Search Query](#fulltext-search-query)
  - [Multi-Match Query](#multi-match-query)
  - [Autocomplete / Phrase Prefix](#autocomplete--phrase-prefix)
  - [Search Term Sanitization](#search-term-sanitization)
- [MongoDB Integration in Detail](#mongodb-integration-in-detail)
  - [Condition Replacement](#condition-replacement)
  - [Impact on MongoDB Index](#impact-on-mongodb-index)
  - [Impact on MongoDB Documents](#impact-on-mongodb-documents)
  - [Fulltext Field Behavior](#fulltext-field-behavior)
- [Indexing During Import](#indexing-during-import)
- [Caching](#caching)
- [Complete Configuration Example](#complete-configuration-example)
- [Common Scenarios](#common-scenarios)
  - [Scenario 1: MongoDB Only (Default)](#scenario-1-mongodb-only-default)
  - [Scenario 2: OpenSearch + MongoDB (Recommended)](#scenario-2-opensearch--mongodb-recommended)
  - [Scenario 3: OpenSearch for Autocomplete Only](#scenario-3-opensearch-for-autocomplete-only)
- [Troubleshooting](#troubleshooting)

---

## Overview

The SDK supports **OpenSearch** as an external fulltext search engine that works **in combination with MongoDB** – not as a replacement. OpenSearch takes over the fulltext search responsibility (free-text queries, fuzzy matching, autocomplete), while MongoDB continues to handle structured queries (price ranges, dates, categories, transport types).

This hybrid approach leverages the strengths of both systems:

| Capability | MongoDB | OpenSearch |
|---|---|---|
| Structured filters (price, date, category) | Excellent | Not used |
| Fulltext search | Basic (text index) | Excellent (analyzers, stemming, fuzzy) |
| Autocomplete / suggestions | Not supported | Built-in (edge n-gram) |
| Faceted aggregation | Excellent | Not used |
| Language-aware search | Limited | Full (stemming, stopwords per language) |
| Scoring / relevance | Basic | Advanced (boost, BM25) |

---

## Architecture: OpenSearch + MongoDB

### How It Works

When a user performs a search that includes a text query (e.g. `pm-t=Mallorca`), the system follows this flow:

```
User Query: ?pm-ot=123&pm-t=Mallorca&pm-pr=500-1500

                    ┌──────────────────────────┐
                    │  Search Controller        │
                    │  (pm-* parameter parsing) │
                    └────────────┬─────────────┘
                                 │
                    ┌────────────▼─────────────┐
                    │  MongoDB Search           │
                    │  Pressmind\Search\MongoDB │
                    │                           │
                    │  Conditions:               │
                    │  ├─ ObjectType: 123        │
                    │  ├─ Fulltext: "Mallorca"  │ ◄── This gets intercepted
                    │  └─ PriceRange: 500-1500  │
                    └────────────┬─────────────┘
                                 │
                    ┌────────────▼─────────────┐
                    │  OpenSearch Interception   │
                    │  (if enabled_in_mongo)     │
                    │                           │
                    │  1. Extract "Mallorca"     │
                    │  2. Remove Fulltext cond.  │
                    │  3. Query OpenSearch        │
                    │  4. Get matching IDs        │
                    │     [45, 89, 123, 456, ...] │
                    │  5. Add MediaObject cond.   │
                    │     {id: {$in: [45,89,...]}} │
                    └────────────┬─────────────┘
                                 │
                    ┌────────────▼─────────────┐
                    │  MongoDB Aggregation       │
                    │                           │
                    │  Final Conditions:         │
                    │  ├─ ObjectType: 123        │
                    │  ├─ MediaObject: $in [IDs] │ ◄── Replaced
                    │  └─ PriceRange: 500-1500  │
                    │                           │
                    │  → Structured query only   │
                    │  → Returns search results  │
                    └──────────────────────────┘
```

### Why Two Search Engines?

**MongoDB alone** offers a basic `$text` index with limited capabilities:
- No language-aware stemming (German: "Reisen" ≠ "Reise")
- No fuzzy matching (typos like "Mallroca" don't work)
- No field boosting (destination name should rank higher than description)
- No autocomplete support

**OpenSearch alone** cannot handle the SDK's complex structured queries:
- Price range aggregation with occupancy bucketing
- Date range filtering with departure windows
- Category tree hierarchies with AND/OR logic
- Faceted counts for filters

**The hybrid approach** combines both: OpenSearch finds relevant product IDs via fulltext, then MongoDB applies all structured filters and returns the self-contained search documents.

### Data Flow Diagram

```
┌──────────────┐         ┌──────────────┐         ┌──────────────┐
│  Import      │         │  OpenSearch   │         │  MongoDB     │
│  Pipeline    │         │  Cluster      │         │  Cluster     │
│              │         │               │         │              │
│  Each media  │────────▶│  Per language: │         │  Per lang +  │
│  object is   │  Index  │  index_{hash} │         │  origin:     │
│  indexed in  │  doc    │  index_{hash}_de│       │  best_price_ │
│  BOTH systems│────────▶│               │         │  search_...  │
│              │         │  Fields:      │         │              │
│              │         │  headline ────│────┐    │  Fields:     │
│              │         │  subline      │    │    │  description │
│              │         │  zielgebiet   │    │    │  categories  │
│              │         │  code         │    │    │  prices      │
│              │         │  fulltext     │    │    │  groups      │
│              │         └──────────────┘    │    │  locations   │
│              │                             │    │  fulltext    │
│              │                             │    │  (conditional)│
│              │              Search time:   │    └──────────────┘
│              │                             │           ▲
│              │         ┌──────────────┐    │           │
│              │         │  pm-t=Mallorca│   │           │
│              │         │              │    │           │
│              │         │  OpenSearch  │◀───┘           │
│              │         │  returns IDs │                │
│              │         │  [45,89,123] │────────────────┘
│              │         │              │  IDs passed as
│              │         │              │  $in condition
│              │         └──────────────┘
└──────────────┘
```

---

## Configuration

All OpenSearch settings are under `data.search_opensearch` in `config.json`:

### Basic Settings

```json
{
  "data": {
    "search_opensearch": {
      "enabled": false,
      "enabled_in_mongo_search": true
    }
  }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `enabled` | Boolean | `false` | Enable OpenSearch indexing during import |
| `enabled_in_mongo_search` | Boolean | `true` | Use OpenSearch for fulltext queries within MongoDB search. If `false`, OpenSearch is indexed but not used at query time |

**Important:** Both flags must be `true` for the hybrid search to work. Setting `enabled: true` but `enabled_in_mongo_search: false` allows indexing into OpenSearch without using it for search (useful for testing).

### Connection Settings

```json
{
  "data": {
    "search_opensearch": {
      "uri": "http://opensearch:9200",
      "user": null,
      "password": null
    }
  }
}
```

| Property | Type | Description |
|---|---|---|
| `uri` | String | OpenSearch cluster URL (e.g. `http://opensearch:9200` or `https://search.example.com:9200`) |
| `user` | String/null | Basic Auth username (null = no auth) |
| `password` | String/null | Basic Auth password |

**Note:** SSL verification is disabled by default in the SDK client. For production environments behind a proxy or with self-signed certificates, this simplifies the connection.

### Cluster Settings

```json
{
  "data": {
    "search_opensearch": {
      "number_of_shards": 1,
      "number_of_replicas": 0
    }
  }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `number_of_shards` | Integer | `1` | Number of primary shards per index. For small-to-medium catalogs (< 50,000 products), `1` is sufficient |
| `number_of_replicas` | Integer | `0` | Number of replica shards. Set to `1` or higher for production high-availability setups |

### Index Field Mapping

The `index` configuration defines which fields from the media objects are indexed in OpenSearch:

```json
{
  "data": {
    "search_opensearch": {
      "index": {
        "code": {
          "type": "keyword",
          "object_type_mapping": { ... }
        },
        "headline_default": {
          "type": "text",
          "object_type_mapping": { ... }
        },
        "subline_default": {
          "type": "text",
          "boost": 2,
          "object_type_mapping": { ... }
        },
        "zielgebiet_default": {
          "type": "text",
          "boost": 2,
          "object_type_mapping": { ... }
        }
      }
    }
  }
}
```

Each key in `index` becomes a field in the OpenSearch index document. The key name is the **OpenSearch field name** – it does not need to match the MediaObject property name (the mapping is defined in `object_type_mapping`).

### Object Type Mapping

Each field has an `object_type_mapping` that defines which MediaObject property maps to this OpenSearch field, per object type:

```json
{
  "headline_default": {
    "type": "text",
    "object_type_mapping": {
      "607": [{
        "language": null,
        "field": {
          "name": "headline_default",
          "params": []
        }
      }],
      "609": [{
        "language": null,
        "field": {
          "name": "headline_default",
          "params": []
        }
      }]
    }
  }
}
```

| Property | Type | Description |
|---|---|---|
| `object_type_mapping` | Object | Keys are object type IDs (e.g. `"607"`, `"609"`) |
| `[id].language` | String/null | Language filter: `null` = all languages, `"de"` = only German content |
| `[id].field.name` | String | MediaObject property name to read the value from. For core fields: `code`, `name`, `tags`. For content fields: the scaffolded property name (e.g. `headline_default`) |
| `[id].field.params` | Array | Reserved for future filter/transform parameters |

**Field Resolution Logic:**

The indexer resolves field values in this order:
1. Core MediaObject fields (`code`, `name`, `tags`) → read from `$mediaObject->code`
2. Content fields → read from `$mediaObject->getDataForLanguage($language)->field_name`
3. Category tree fields → automatically extracts `item->name` from all tree items and joins them with spaces
4. HTML content → automatically stripped to plain text (tags removed, entities decoded)

### Field Types & Boosting

| Type | Description | Use For |
|---|---|---|
| `text` | Full-text analyzed field. Supports stemming, fuzzy matching, and autocomplete | Headlines, descriptions, category names |
| `keyword` | Exact match, not analyzed. No stemming or fuzzy matching | Product codes, IDs, exact identifiers |

**Boosting:**

```json
{
  "subline_default": {
    "type": "text",
    "boost": 2
  }
}
```

The `boost` value (default `1`) multiplies the relevance score for matches in this field. Higher values make matches in this field rank higher:

| Boost | Effect |
|---|---|
| `1` | Normal relevance (default) |
| `2` | Double weight – matches here rank higher |
| `5` | Five times weight – strong preference |
| `10+` | Very strong preference – almost exclusive |

**Example:** If `zielgebiet_default` (destination) has `boost: 2` and `headline_default` has no boost, searching for "Mallorca" will rank products where "Mallorca" appears in the destination higher than products where it only appears in the headline.

### Language Support

The SDK creates **separate OpenSearch indexes per language**:

```
index_{hash}        → fallback (no language)
index_{hash}_de     → German content
index_{hash}_en     → English content
```

Each language gets its own analyzer configuration:

**German:**
- Stemmer: `light_german` (Reisen → Reis, Flüge → Flug)
- Stopwords: German stopwords (der, die, das, und, ...)
- Autocomplete tokenizer: edge n-gram (2-20 chars)

**English:**
- Stemmer: `light_english` (traveling → travel, flights → flight)
- Stopwords: English stopwords (the, a, an, and, ...)
- Autocomplete tokenizer: edge n-gram (2-20 chars)

If no language is specified in the field mapping (`"language": null`), the field is indexed in all language indexes.

---

## Index Management

### Index Template Creation

Before any documents can be indexed, index templates must be created. This happens automatically during the first `upsertMediaObject()` call:

```php
$indexer = new \Pressmind\Search\OpenSearch\Indexer();
$indexer->createIndexTemplates();  // Creates templates + indexes
$indexer->createIndexes();         // Indexes all media objects
```

The template defines:
- Shard/replica settings
- Analyzer configuration (per language)
- Field mappings (from `index` config)
- Tokenizer settings (autocomplete edge n-gram)

### Index Naming Convention

```
index_{configHash}
index_{configHash}_{language}
```

The `configHash` is an MD5 hash of the OpenSearch config (excluding `uri`, `username`, `password`). This ensures:
- Config changes create new indexes automatically
- Old indexes from previous configurations are cleaned up
- Connection changes don't trigger reindexing

### Automatic Analyzers

The SDK creates these analyzers per language:

**For German (`de`):**

| Analyzer | Type | Usage |
|---|---|---|
| `german_default` | Standard + German stopwords | Default text analysis |
| `autocomplete_de` | Edge n-gram + German stemmer + stopwords | Index-time autocomplete |
| `autocomplete_search_de` | Standard + German stemmer + stopwords | Search-time autocomplete |

**For English (`en`):**

| Analyzer | Type | Usage |
|---|---|---|
| `english_default` | Standard + English stopwords | Default text analysis |
| `autocomplete_en` | Edge n-gram + English stemmer + stopwords | Index-time autocomplete |
| `autocomplete_search_en` | Standard + English stemmer + stopwords | Search-time autocomplete |

The edge n-gram tokenizer creates partial word tokens for autocomplete:
```
"Mallorca" → ["Ma", "Mal", "Mall", "Mallo", "Mallor", "Mallorc", "Mallorca"]
```

### Index Lifecycle

```
1. First import run:
   └─ createIndexTemplates() → creates template + empty index
   └─ upsertMediaObject()   → indexes all documents

2. Subsequent imports:
   └─ upsertMediaObject()   → updates/creates individual documents

3. Config change:
   └─ createIndexTemplates() → creates new template + index (new hash)
   └─ deleteAllIndexesThatNotMatchConfigHash() → removes old indexes

4. MediaObject deletion:
   └─ Orphan detection in upsertMediaObject() → deletes from OpenSearch
```

---

## Fulltext Search Query

### Multi-Match Query

The standard search uses a `multi_match` query across all configured fields:

```json
{
  "query": {
    "bool": {
      "must": [{
        "multi_match": {
          "query": "Mallorca",
          "fields": ["code^1", "headline_default^1", "subline_default^2", "zielgebiet_default^2"],
          "type": "best_fields",
          "operator": "and",
          "fuzziness": "AUTO",
          "prefix_length": 3
        }
      }]
    }
  }
}
```

| Parameter | Value | Description |
|---|---|---|
| `type` | `best_fields` | Uses the best matching field's score |
| `operator` | `and` | All search terms must match |
| `fuzziness` | `AUTO` | Automatic edit distance (1 for short words, 2 for long words) |
| `prefix_length` | `3` | First 3 characters must match exactly (prevents excessive fuzzy expansion) |

**Pagination:** The search uses `search_after` cursor pagination to retrieve all matching IDs (up to the configured limit, default 10,000 for MongoDB integration).

### Autocomplete / Phrase Prefix

For autocomplete suggestions (`SearchType::AUTOCOMPLETE`), a different query is used:

```json
{
  "query": {
    "multi_match": {
      "query": "Mall",
      "fields": ["headline_default^1", "subline_default^2", "zielgebiet_default^2"],
      "type": "phrase_prefix",
      "operator": "and"
    }
  },
  "size": 10
}
```

Key differences from standard search:
- `type: phrase_prefix` – matches partial words at the end
- Only `text` type fields (no `keyword`)
- Limited to 10 results
- No fuzziness (prefix matching is sufficient)

### Search Term Sanitization

Before querying, the search term is sanitized:

```php
$term = FulltextSearch::replaceChars($input); // Normalize special chars
$term = preg_replace('/[\x00-\x1F]+/', ' ', $term); // Remove control chars
$term = trim($term);
```

---

## MongoDB Integration in Detail

### Condition Replacement

The integration mechanism in `Pressmind\Search\MongoDB::getResult()`:

```
Before OpenSearch interception:
  Conditions: [ObjectType, Fulltext("Mallorca"), PriceRange, DateRange]

After OpenSearch interception:
  Conditions: [ObjectType, MediaObject($in: [45,89,123,...]), PriceRange, DateRange]
```

**Step-by-step:**

1. Check if `_use_opensearch` is `true`
2. Check if a `Fulltext` or `AtlasLuceneFulltext` condition exists
3. Extract the raw search string from the condition
4. Remove the fulltext condition from the MongoDB query
5. Execute OpenSearch query with the search string (limit: 10,000 IDs)
6. Create a new `MediaObject` condition with the returned IDs: `{id: {$in: [45, 89, 123, ...]}}`
7. Add the `MediaObject` condition to the MongoDB query
8. MongoDB executes the remaining aggregation pipeline with the ID filter

### Impact on MongoDB Index

When OpenSearch is **enabled**, MongoDB's text index is **not created**:

```php
// In MongoDB Indexer:
if(!$this->_use_opensearch){
    $this->createCollectionIndexIfNotExists(
        $collection_name,
        ['fulltext' => 'text', 'categories.path_str' => 'text', 'code' => 'text'],
        ['default_language' => 'none', 'weights' => [...], 'name' => 'fulltext_text']
    );
}
```

This has two benefits:
1. **Smaller MongoDB indexes** – text indexes are large and slow to build
2. **Faster writes** – no text index maintenance during document upserts

### Impact on MongoDB Documents

When OpenSearch is **enabled**, the `fulltext` field is **not populated** in MongoDB documents:

```php
// In MongoDB Indexer::createIndex():
if(!$this->_use_opensearch){
    $searchObject->fulltext = FulltextSearch::getFullTextWords(...);
}
```

And when returning search results, the `fulltext` field is **not removed** from the output (because it doesn't exist):

```php
// In MongoDB::getResult():
if(!$this->_use_opensearch){
    $stages[] = ['$unset' => ['fulltext']]; // Only clean up if using MongoDB fulltext
}
```

### Fulltext Field Behavior

| Configuration | `fulltext` in MongoDB doc | MongoDB text index | Search via |
|---|---|---|---|
| OpenSearch disabled | Present (populated) | Created | MongoDB `$text` operator |
| OpenSearch enabled | Absent (not populated) | Not created | OpenSearch → ID list → MongoDB `$in` |

---

## Indexing During Import

OpenSearch documents are created/updated during the import pipeline:

```
Import::importMediaObject()
  └─ ... (all other import steps) ...
  └─ createOpenSearchIndex()
       └─ OpenSearch\Indexer::upsertMediaObject($id)
            └─ For each language:
                 └─ createIndex($id, $language)
                      ├─ Load MediaObject + content data
                      ├─ For each configured field:
                      │   ├─ Read field value (string/category/HTML)
                      │   ├─ Convert HTML to plaintext
                      │   └─ Sanitize special characters
                      ├─ Generate fulltext field
                      └─ Index document in OpenSearch
```

The same happens during `importTouristicDataOnly()` to keep the index current even for touristic-only updates.

---

## Caching

OpenSearch results can be cached in Redis:

```json
{
  "cache": {
    "enabled": true,
    "types": ["OPENSEARCH"]
  }
}
```

Cache key format: `OPENSEARCH:{md5(search_term + index_name + language + limit)}`

The cache is TTL-based and must be explicitly requested via the `$ttl` parameter in `getResult()`.

---

## Complete Configuration Example

```json
{
  "data": {
    "search_opensearch": {
      "enabled": true,
      "enabled_in_mongo_search": true,
      "uri": "https://opensearch.example.com:9200",
      "user": "admin",
      "password": "secretPassword123",
      "number_of_shards": 1,
      "number_of_replicas": 1,
      "index": {
        "code": {
          "type": "keyword",
          "object_type_mapping": {
            "607": [{"language": null, "field": {"name": "code", "params": []}}],
            "609": [{"language": null, "field": {"name": "code", "params": []}}]
          }
        },
        "headline_default": {
          "type": "text",
          "boost": 3,
          "object_type_mapping": {
            "607": [{"language": null, "field": {"name": "headline_default", "params": []}}],
            "609": [{"language": null, "field": {"name": "headline_default", "params": []}}]
          }
        },
        "subline_default": {
          "type": "text",
          "boost": 2,
          "object_type_mapping": {
            "607": [{"language": null, "field": {"name": "subline_default", "params": []}}],
            "609": [{"language": null, "field": {"name": "subline_default", "params": []}}]
          }
        },
        "zielgebiet_default": {
          "type": "text",
          "boost": 5,
          "object_type_mapping": {
            "607": [{"language": null, "field": {"name": "zielgebiet_default", "params": []}}],
            "609": [{"language": null, "field": {"name": "zielgebiet_default", "params": []}}]
          }
        },
        "beschreibung_default": {
          "type": "text",
          "boost": 1,
          "object_type_mapping": {
            "607": [{"language": null, "field": {"name": "beschreibung_default", "params": []}}]
          }
        }
      }
    }
  }
}
```

This configuration:
- Indexes product codes as exact match (`keyword`)
- Indexes headlines with high boost (`3`) for strong relevance
- Indexes destinations with very high boost (`5`) – destination matches rank highest
- Indexes descriptions with default boost (`1`) – lowest priority but still searchable
- Supports two object types (`607`, `609`)
- Uses no language filter → all languages indexed in all indexes

---

## Common Scenarios

### Scenario 1: MongoDB Only (Default)

```json
{
  "search_opensearch": { "enabled": false }
}
```

- MongoDB `$text` index is created
- `fulltext` field is populated in MongoDB documents
- Text search uses MongoDB's built-in text search
- No fuzzy matching, no autocomplete, limited stemming
- **Simplest setup, no additional infrastructure**

### Scenario 2: OpenSearch + MongoDB (Recommended)

```json
{
  "search_opensearch": {
    "enabled": true,
    "enabled_in_mongo_search": true
  }
}
```

- OpenSearch handles all fulltext queries
- MongoDB text index is NOT created (smaller, faster)
- Fuzzy matching, language-aware stemming, field boosting
- Autocomplete support for search suggestions
- **Best search quality, requires OpenSearch infrastructure**

### Scenario 3: OpenSearch for Autocomplete Only

```json
{
  "search_opensearch": {
    "enabled": true,
    "enabled_in_mongo_search": false
  }
}
```

- OpenSearch is indexed during import (for autocomplete API)
- MongoDB still uses its own `$text` index for regular search
- Useful for adding autocomplete without changing search behavior
- **Incremental adoption path**

---

## Troubleshooting

### Common Issues

| Symptom | Cause | Solution |
|---|---|---|
| "Index template does not exist" | First run, templates not yet created | Run `createIndexTemplates()` before indexing |
| Empty search results with OpenSearch | `enabled_in_mongo_search` is `false` | Set both `enabled` and `enabled_in_mongo_search` to `true` |
| Fuzzy matching not working | `prefix_length: 3` requires first 3 chars correct | Expected behavior – first 3 characters must be correct |
| Wrong language stemming | `language` field in mapping is wrong or `null` | Set correct language code in `object_type_mapping` |
| Stale search results | Old index with different config hash | Check `deleteAllIndexesThatNotMatchConfigHash()` runs during template creation |
| OpenSearch connection refused | Wrong URI or OpenSearch not running | Verify `uri` setting and OpenSearch service status |
| `search_opensearch.search_opensearch.enabled` bug | Nested key check in `MediaObject::createOpenSearchIndex()` | The import pipeline checks the correct path; this only affects direct `createOpenSearchIndex()` calls |

### Debugging

Enable debug output for OpenSearch queries:

```php
// Via GET parameter
?debug=1

// Via constant
define('PM_SDK_DEBUG', true);
```

This prints the raw OpenSearch query JSON to the output.

Enable advanced logging:

```json
{
  "logging": {
    "enable_advanced_object_log": true
  }
}
```

This adds timestamped log entries for each OpenSearch operation.
