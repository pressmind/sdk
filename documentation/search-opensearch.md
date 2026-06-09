# OpenSearch Integration

[← Back to MongoDB Search API](search-mongodb-api.md) | [→ MongoDB Index Configuration](search-mongodb-index-configuration.md)

---

## Table of Contents

- [Overview](#overview)
- [Architecture: OpenSearch + MongoDB](#architecture-opensearch--mongodb)
  - [How It Works](#how-it-works)
  - [Why Two Search Engines?](#why-two-search-engines)
  - [Important: OpenSearch vs. MongoDB Atlas Search](#important-opensearch-vs-mongodb-atlas-search)
  - [Data Flow Diagram](#data-flow-diagram)
- [OpenSearch Concepts Reference](#opensearch-concepts-reference)
  - [Index](#index)
  - [Document](#document)
  - [Mapping](#mapping)
  - [Field Types](#field-types)
  - [Analyzers](#analyzers)
  - [Tokenizers](#tokenizers)
  - [Token Filters](#token-filters)
  - [Query DSL](#query-dsl)
  - [Shards and Replicas](#shards-and-replicas)
  - [Index Templates](#index-templates)
  - [k-NN (Vector Search)](#k-nn-vector-search)
- [Configuration](#configuration)
  - [Basic Settings](#basic-settings)
  - [Connection Settings](#connection-settings)
  - [Cluster Settings](#cluster-settings)
  - [Index Field Mapping](#index-field-mapping)
  - [Object Type Mapping](#object-type-mapping)
  - [Field Types & Boosting](#field-types--boosting)
  - [Language Support](#language-support)
- [Vector Search Configuration](#vector-search-configuration)
  - [Overview](#vector-search-overview)
  - [Vector Settings](#vector-settings)
  - [Embedding Providers](#embedding-providers)
  - [Search Modes](#search-modes)
  - [Embedding Cache](#embedding-cache)
- [Index Management](#index-management)
  - [Index Template Creation](#index-template-creation)
  - [Index Naming Convention](#index-naming-convention)
  - [Automatic Analyzers](#automatic-analyzers)
  - [Index Lifecycle](#index-lifecycle)
- [Search Query Architecture](#search-query-architecture)
  - [Lexical Search (Bool/Should Query)](#lexical-search-boolshould-query)
  - [Multi-Match Query Types](#multi-match-query-types)
  - [Autocomplete / Phrase Prefix](#autocomplete--phrase-prefix)
  - [Vector Search (k-NN Query)](#vector-search-k-nn-query)
  - [Hybrid Search (Score Fusion)](#hybrid-search-score-fusion)
  - [Search Term Sanitization](#search-term-sanitization)
  - [Pagination with search_after](#pagination-with-search_after)
- [MongoDB Integration in Detail](#mongodb-integration-in-detail)
  - [Condition Replacement](#condition-replacement)
  - [Impact on MongoDB Index](#impact-on-mongodb-index)
  - [Impact on MongoDB Documents](#impact-on-mongodb-documents)
  - [Fulltext Field Behavior](#fulltext-field-behavior)
- [Indexing During Import](#indexing-during-import)
- [Caching](#caching)
- [Complete Configuration Example](#complete-configuration-example)
- [Fuzzy Matching Tuning](#fuzzy-matching-tuning)
- [Stop Words Configuration](#stop-words-configuration)
- [TermResolver (Category-Based Search Optimization)](#termresolver-category-based-search-optimization)
- [Common Scenarios](#common-scenarios)
  - [Scenario 1: MongoDB Only (Default)](#scenario-1-mongodb-only-default)
  - [Scenario 2: OpenSearch + MongoDB (Recommended)](#scenario-2-opensearch--mongodb-recommended)
  - [Scenario 3: OpenSearch for Autocomplete Only](#scenario-3-opensearch-for-autocomplete-only)
  - [Scenario 4: Hybrid Search with Vector Embeddings](#scenario-4-hybrid-search-with-vector-embeddings)
- [PHP Class Reference](#php-class-reference)
  - [Pressmind\Search\OpenSearch](#pressmindsearchopensearch)
  - [Pressmind\Search\OpenSearch\AbstractIndex](#pressmindsearchopensearchabstractindex)
  - [Pressmind\Search\OpenSearch\Indexer](#pressmindsearchopensearchindexer)
  - [Pressmind\Search\Embedding Namespace](#pressmindsearchembedding-namespace)
- [Troubleshooting](#troubleshooting)

---

## Overview

The SDK supports **OpenSearch** as an external fulltext search engine that works **in combination with MongoDB** – not as a replacement. OpenSearch takes over the fulltext search responsibility (free-text queries, fuzzy matching, autocomplete, semantic/vector search), while MongoDB continues to handle structured queries (price ranges, dates, categories, transport types).

This hybrid approach leverages the strengths of both systems:

| Capability | MongoDB | OpenSearch |
|---|---|---|
| Structured filters (price, date, category) | Excellent | Not used |
| Fulltext search | Basic (text index) | Excellent (analyzers, stemming, fuzzy) |
| Autocomplete / suggestions | Not supported | Built-in (edge n-gram) |
| Faceted aggregation | Excellent | Not used |
| Language-aware search | Limited | Full (stemming, stopwords per language) |
| Scoring / relevance | Basic | Advanced (boost, BM25) |
| Semantic / vector search | Not supported | k-NN with HNSW algorithm |
| Typo tolerance | Not supported | Fuzzy matching (Levenshtein distance) |

---

## Architecture: OpenSearch + MongoDB

### How It Works

When a user performs a search that includes a text query (e.g. `pm-t=Mallorca`), the system follows this flow:

```
User Query: ?pm-ot=123&pm-t=Mallorca&pm-pr=500-1500

                    ┌───────────────────────────┐
                    │  Search Controller        │
                    │  (pm-* parameter parsing) │
                    └────────────┬──────────────┘
                                 │
                    ┌────────────▼──────────────┐
                    │  MongoDB Search           │
                    │  Pressmind\Search\MongoDB │
                    │                           │
                    │  Conditions:              │
                    │  ├─ ObjectType: 123       │
                    │  ├─ Fulltext: "Mallorca"  │ ◄── This gets intercepted
                    │  └─ PriceRange: 500-1500  │
                    └────────────┬──────────────┘
                                 │
                    ┌────────────▼─────────────────┐
                    │  OpenSearch Interception     │
                    │  (if enabled_in_mongo)       │
                    │                              │
                    │  1. Extract "Mallorca"       │
                    │  2. Remove Fulltext cond.    │
                    │  3. Query OpenSearch         │
                    │  4. Get matching IDs         │
                    │     [45, 89, 123, 456, ...]  │
                    │  5. Add MediaObject cond.    │
                    │     {id: {$in: [45,89,...]}} │
                    └────────────┬─────────────────┘
                                 │
                    ┌────────────▼───────────────┐
                    │  MongoDB Aggregation       │
                    │                            │
                    │  Final Conditions:         │
                    │  ├─ ObjectType: 123        │
                    │  ├─ MediaObject: $in [IDs] │ ◄── Replaced
                    │  └─ PriceRange: 500-1500   │
                    │                            │
                    │  → Structured query only   │
                    │  → Returns search results  │
                    └────────────────────────────┘
```

### Why Two Search Engines?

**MongoDB alone** offers a basic `$text` index with limited capabilities:
- No language-aware stemming (German: "Reisen" ≠ "Reise")
- No fuzzy matching (typos like "Mallroca" don't work)
- No field boosting (destination name should rank higher than description)
- No autocomplete support
- No semantic understanding

**OpenSearch alone** cannot handle the SDK's complex structured queries:
- Price range aggregation with occupancy bucketing
- Date range filtering with departure windows
- Category tree hierarchies with AND/OR logic
- Faceted counts for filters

**The hybrid approach** combines both: OpenSearch finds relevant product IDs via fulltext (and optionally vector similarity), then MongoDB applies all structured filters and returns the self-contained search documents.

### Important: OpenSearch vs. MongoDB Atlas Search

> **OpenSearch and MongoDB Atlas Search cannot be combined.** These are two mutually exclusive fulltext search strategies.

The SDK does support MongoDB Atlas Search (`AtlasLuceneFulltext` condition) as an alternative fulltext search mechanism. However, this feature is **not recommended** and exists only for legacy compatibility. The preferred and fully supported stack is:

**Recommended: OpenSearch + MongoDB Community Edition**

| Component | Role |
|---|---|
| OpenSearch | Fulltext search, fuzzy matching, autocomplete, vector/semantic search |
| MongoDB Community Edition | Structured queries, aggregation, price/date/category filters |

This combination provides a complete, self-hosted search stack without requiring MongoDB Atlas (and its associated costs and vendor lock-in).

**Not recommended: MongoDB Atlas Search**

| Limitation | Description |
|---|---|
| Requires MongoDB Atlas (paid, cloud-only) | Cannot be used with self-hosted MongoDB Community Edition |
| Cannot be combined with OpenSearch | Activating both leads to undefined behavior – the SDK processes fulltext conditions only once |
| No autocomplete support | Atlas Search does not provide edge n-gram based autocomplete |
| No vector/semantic search integration | The SDK's vector search is exclusively integrated with OpenSearch |
| Limited analyzer customization | Less control over stemming, stopwords, and tokenization |

**If OpenSearch is enabled (`enabled: true` + `enabled_in_mongo_search: true`), any `AtlasLuceneFulltext` condition is ignored** because the fulltext condition is intercepted and replaced with an OpenSearch ID list before it reaches the MongoDB aggregation pipeline.

**Rule of thumb:** Use OpenSearch + MongoDB Community Edition. If you are already on MongoDB Atlas for other reasons, do NOT additionally activate Atlas Search alongside OpenSearch – choose one fulltext strategy.

### Data Flow Diagram

```
┌──────────────┐         ┌─────────────────┐         ┌──────────────┐
│  Import      │         │  OpenSearch     │         │  MongoDB     │
│  Pipeline    │         │  Cluster        │         │  Cluster     │
│              │         │                 │         │              │
│  Each media  │────────▶│  Per language:  │         │  Per lang +  │
│  object is   │  Index  │  index_{hash}   │         │  origin:     │
│  indexed in  │  doc    │  index_{hash}_de│         │  best_price_ │
│  BOTH systems│────────▶│                 │         │  search_...  │
│              │         │  Fields:        │         │              │
│              │         │  headline ──────│──────┐  │  Fields:     │
│              │         │  subline        │      │  │  description │
│              │         │  zielgebiet     │      │  │  categories  │
│              │         │  code           │      │  │  prices      │
│              │         │  fulltext       │      │  │  groups      │
│              │         │  content_vector │      │  │  locations   │
│              │         └─────────────────┘      │  └──────────────┘
│              │                                  │           ▲
│              │         ┌────────────────┐       │           │
│              │         │  pm-t=Mallorca │       │           │
│              │         │                │       │           │
│              │         │  OpenSearch    │◀──────┘           │
│              │         │  returns IDs   │                   │
│              │         │  [45,89,123]   │───────────────────┘
│              │         │                │  IDs passed as
│              │         │                │  $in condition
│              │         └────────────────┘
└──────────────┘
```

---

## OpenSearch Concepts Reference

This section explains the OpenSearch concepts and terminology used throughout the SDK integration. Each concept includes a reference to the [official OpenSearch documentation](https://opensearch.org/docs/latest/).

### Index

An **index** in OpenSearch is a collection of documents that share similar characteristics. It is comparable to a database table in a relational database. Each index has a unique name, a set of mappings (field definitions), and configurable settings (shards, replicas, analyzers).

In the SDK, one index is created per language:
- `index_{hash}_de` – German content
- `index_{hash}_en` – English content

> **Reference:** [OpenSearch Index](https://opensearch.org/docs/latest/im-plugin/index/)

### Document

A **document** is the basic unit of data stored in an OpenSearch index. It is a JSON object with fields. In the SDK context, each document represents one media object (travel product) and contains the configured text fields plus the fulltext aggregation.

Example document:
```json
{
  "id": 12345,
  "id_object_type": 607,
  "fulltext": "Rundreise Mallorca Palma Strände ...",
  "headline_default": "Mallorca Rundreise",
  "subline_default": "7 Tage Inselparadies",
  "zielgebiet_default": "Mallorca Balearen",
  "code": "MAL-2024-001",
  "content_vector": [0.0123, -0.0456, ...]
}
```

> **Reference:** [OpenSearch Documents](https://opensearch.org/docs/latest/im-plugin/index/#introduction-to-indexing)

### Mapping

A **mapping** defines the schema for documents in an index – which fields exist and what their data types are. Unlike relational databases, OpenSearch can infer mappings dynamically, but the SDK defines explicit mappings for deterministic behavior.

The SDK creates mappings during `createIndexTemplates()`:
```json
{
  "mappings": {
    "properties": {
      "id": { "type": "integer" },
      "id_object_type": { "type": "integer" },
      "fulltext": { "type": "text", "analyzer": "german_default" },
      "headline_default": {
        "type": "text",
        "analyzer": "autocomplete_de",
        "search_analyzer": "autocomplete_search_de"
      },
      "code": { "type": "keyword" },
      "content_vector": {
        "type": "knn_vector",
        "dimension": 1536,
        "method": { "name": "hnsw", "space_type": "cosinesimil" }
      }
    }
  }
}
```

> **Reference:** [OpenSearch Mappings](https://opensearch.org/docs/latest/field-types/)

### Field Types

The SDK uses these OpenSearch field types:

#### `text`

A `text` field is analyzed – its content is broken into individual tokens (words) by an analyzer before being stored in the inverted index. This enables full-text search with features like stemming, stopword removal, and partial matching.

**Characteristics:**
- Content is analyzed (tokenized, lowercased, stemmed)
- Supports full-text queries (`match`, `multi_match`, `phrase_prefix`)
- Supports fuzzy matching and autocomplete
- Not suitable for exact matching, sorting, or aggregation
- Uses BM25 scoring algorithm for relevance

**Used for:** Headlines, descriptions, category names, destination names – any field where users search with natural language.

> **Reference:** [OpenSearch Text Field Type](https://opensearch.org/docs/latest/field-types/supported-field-types/text/)

#### `keyword`

A `keyword` field is not analyzed – it is stored exactly as provided, as a single token. It supports exact matching, sorting, and aggregation but no full-text search features.

**Characteristics:**
- Content is NOT analyzed (stored as-is)
- Supports exact match queries (`term`, `terms`)
- Supports sorting and aggregation
- No stemming, no fuzzy matching, no partial matching
- Case-sensitive by default

**Used for:** Product codes (e.g. `MAL-2024-001`), identifiers, tags that must be matched exactly.

> **Reference:** [OpenSearch Keyword Field Type](https://opensearch.org/docs/latest/field-types/supported-field-types/keyword/)

#### `integer`

A numeric field for whole numbers. Used for ID fields (`id`, `id_object_type`).

> **Reference:** [OpenSearch Numeric Field Types](https://opensearch.org/docs/latest/field-types/supported-field-types/numeric/)

#### `knn_vector`

A specialized field type for storing dense vectors (embeddings). Enables approximate k-nearest-neighbor (k-NN) search using algorithms like HNSW.

**Characteristics:**
- Stores fixed-dimension float vectors
- Requires `dimension` parameter matching the embedding model output
- Supports similarity search (cosine, L2, inner product)
- Requires `index.knn: true` in index settings

**Used for:** Content embeddings for semantic/vector search.

> **Reference:** [OpenSearch k-NN Plugin](https://opensearch.org/docs/latest/search-plugins/knn/index/)

### Analyzers

An **analyzer** is a pipeline that processes text before it is stored in the index (at index time) or before a search query is executed (at search time). An analyzer consists of:

1. **Character filters** – Transform raw text (e.g. strip HTML)
2. **Tokenizer** – Split text into individual tokens
3. **Token filters** – Transform tokens (lowercase, stemming, stopwords)

The SDK defines these custom analyzers per language:

#### `german_default` / `english_default`

```json
{
  "german_default": {
    "type": "standard",
    "stopwords": "_german_"
  }
}
```

The `standard` analyzer type uses the Unicode Text Segmentation tokenizer and applies lowercase transformation. Combined with `stopwords`, it removes common words that don't contribute to search relevance (der, die, das, und, etc.).

**Used for:** The `fulltext` field containing the full aggregated text.

#### `autocomplete_{lang}` (Index Analyzer)

```json
{
  "autocomplete_de": {
    "type": "custom",
    "tokenizer": "autocomplete_tokenizer",
    "filter": ["lowercase", "german_stop", "german_stemmer"]
  }
}
```

Uses the `edge_ngram` tokenizer to create partial-word tokens at index time. This means for the word "Mallorca", these tokens are stored:

```
"Mallorca" → ["ma", "mal", "mall", "mallo", "mallor", "mallorc", "mallorca"]
```

This enables autocomplete: when the user types "Mall", it matches the token "mall" in the index.

**Used for:** All `text` type fields in the index mapping (index-time analyzer).

#### `autocomplete_search_{lang}` (Search Analyzer)

```json
{
  "autocomplete_search_de": {
    "type": "custom",
    "tokenizer": "standard",
    "filter": ["lowercase", "german_stop", "german_stemmer"]
  }
}
```

At search time, the query is NOT edge-n-grammed – it uses the standard tokenizer. This asymmetry is intentional: the index stores all prefixes, but the search query is matched as a whole term against those prefixes.

**Used for:** All `text` type fields in the index mapping (search-time analyzer).

> **Reference:** [OpenSearch Analyzers](https://opensearch.org/docs/latest/analyzers/)

### Tokenizers

A **tokenizer** splits text into individual tokens according to specific rules.

#### `standard`

Splits on word boundaries (spaces, punctuation) and removes most punctuation. Used at search time.

```
"Reise nach Mallorca!" → ["Reise", "nach", "Mallorca"]
```

#### `edge_ngram`

Generates substrings (n-grams) from the beginning of each token. The SDK configures:

```json
{
  "autocomplete_tokenizer": {
    "type": "edge_ngram",
    "min_gram": 2,
    "max_gram": 20,
    "token_chars": ["letter", "digit"]
  }
}
```

| Parameter | Value | Description |
|---|---|---|
| `min_gram` | `2` | Minimum token length (2 characters) |
| `max_gram` | `20` | Maximum token length (20 characters) |
| `token_chars` | `["letter", "digit"]` | Only generate tokens from letters and digits; other characters act as separators |

```
"Mal" → ["Ma", "Mal"]
"Mallorca2024" → ["Ma", "Mal", "Mall", ..., "Mallorca2024"]
```

> **Reference:** [OpenSearch Tokenizers](https://opensearch.org/docs/latest/analyzers/tokenizers/index/)

### Token Filters

**Token filters** transform tokens after the tokenizer has split the text.

#### `lowercase`

Converts all tokens to lowercase. Makes search case-insensitive.

```
"Mallorca" → "mallorca"
```

#### `stemmer`

Reduces words to their stem (root form). The SDK uses "light" stemmers that are less aggressive to avoid over-stemming:

| Language | Stemmer | Example |
|---|---|---|
| German | `light_german` | Reisen → reis, Flüge → flug, Wanderungen → wanderung |
| English | `light_english` | traveling → travel, flights → flight, beaches → beach |

"Light" stemmers preserve more of the original word compared to aggressive stemmers, reducing false matches while still enabling morphological matching.

#### `stop`

Removes stopwords – common words with little search value:

| Language | Stopwords (examples) |
|---|---|
| German (`_german_`) | der, die, das, und, in, von, zu, mit, für, auf, ... |
| English (`_english_`) | the, a, an, and, in, of, to, with, for, on, ... |

> **Reference:** [OpenSearch Token Filters](https://opensearch.org/docs/latest/analyzers/token-filters/index/)

### Query DSL

The OpenSearch Query DSL (Domain-Specific Language) is the JSON-based query language used to search documents. The SDK uses these query types:

#### `bool` Query

A compound query that combines multiple clauses with Boolean logic:

```json
{
  "bool": {
    "should": [...],
    "minimum_should_match": 1,
    "filter": [...]
  }
}
```

| Clause | Meaning | Scoring |
|---|---|---|
| `must` | All clauses MUST match (AND logic) | Contributes to score |
| `should` | At least `minimum_should_match` clauses SHOULD match (OR logic) | Contributes to score |
| `filter` | Clauses MUST match but do NOT contribute to scoring | No scoring |
| `must_not` | Clauses MUST NOT match (NOT logic) | No scoring |

The SDK uses `should` with `minimum_should_match: 1` to combine text and keyword queries. This means: a document matches if it matches either the text query OR the keyword query (or both). Both contribute to the relevance score.

> **Reference:** [OpenSearch Bool Query](https://opensearch.org/docs/latest/query-dsl/compound/bool/)

#### `multi_match` Query

Searches across multiple fields simultaneously:

```json
{
  "multi_match": {
    "query": "Mallorca",
    "fields": ["headline_default^1", "subline_default^2", "zielgebiet_default^2"],
    "type": "best_fields",
    "operator": "and",
    "fuzziness": "AUTO",
    "prefix_length": 3
  }
}
```

| Parameter | SDK Value | Description |
|---|---|---|
| `query` | User search term | The text to search for |
| `fields` | From config with boost | Fields to search in. `^N` syntax applies boost |
| `type` | `best_fields` | Uses the score from the single best-matching field |
| `operator` | `and` | ALL terms in the query must be present in a field |
| `fuzziness` | `AUTO` | Auto edit distance: 0 for 1-2 chars, 1 for 3-5 chars, 2 for 6+ chars |
| `prefix_length` | `5` (configurable) | First N characters must match exactly (no fuzziness applied). Configured via `search_opensearch.prefix_length` |

**`type` options explained:**
- `best_fields` (used): Takes the score from the field that matches best. Ideal when fields contain similar content and one is "the best match".
- `most_fields`: Combines scores from all matching fields. Better for fields with different aspects of the same content.
- `phrase_prefix`: Matches a phrase where the last term is treated as a prefix. Used for autocomplete.
- `cross_fields`: Treats all fields as one large field. Good when terms might span across fields.

**`operator` options explained:**
- `and` (used): "Reise Mallorca" → document must contain BOTH "Reise" AND "Mallorca"
- `or`: "Reise Mallorca" → document must contain "Reise" OR "Mallorca" (or both)

**`fuzziness: AUTO` explained:**

Fuzziness uses Levenshtein edit distance (insertions, deletions, substitutions):

| Term Length | Max Edit Distance | Example |
|---|---|---|
| 1-2 characters | 0 (exact match) | "zu" matches only "zu" |
| 3-5 characters | 1 edit allowed | "Reiße" matches "Reise" |
| 6+ characters | 2 edits allowed | "Mallroca" matches "Mallorca" |

`prefix_length` (default: `5`, configurable via `search_opensearch.prefix_length`) restricts fuzziness to start after the Nth character. This prevents "Berlin" from matching "Bernina" and significantly reduces false positives in tourism search contexts.

> **Reference:** [OpenSearch Multi-Match Query](https://opensearch.org/docs/latest/query-dsl/full-text/multi-match/)

#### `knn` Query

A k-nearest-neighbor query for vector similarity search:

```json
{
  "knn": {
    "content_vector": {
      "vector": [0.0123, -0.0456, ...],
      "k": 50
    }
  }
}
```

| Parameter | Description |
|---|---|
| Field name (`content_vector`) | The `knn_vector` field to search |
| `vector` | The query vector (same dimensions as indexed vectors) |
| `k` | Number of nearest neighbors to return |

> **Reference:** [OpenSearch k-NN Search](https://opensearch.org/docs/latest/search-plugins/knn/knn-score-script/)

### Shards and Replicas

OpenSearch distributes data across **shards** for horizontal scaling:

#### Primary Shards (`number_of_shards`)

A shard is a self-contained unit of the index with its own Lucene index. Each document lives on exactly one primary shard.

| Value | Use Case |
|---|---|
| `1` (SDK default) | Small to medium catalogs (< 50,000 products). Simpler, less overhead |
| `2-5` | Large catalogs (50,000-500,000 products). Enables parallel search |
| `5+` | Very large catalogs. Requires multi-node cluster |

**Important:** The number of primary shards cannot be changed after index creation. A new index must be created if you need to change this.

#### Replica Shards (`number_of_replicas`)

Replicas are copies of primary shards for:
1. **High availability** – If a node fails, replicas on other nodes serve requests
2. **Read throughput** – Search requests can be served from replicas in parallel

| Value | Use Case |
|---|---|
| `0` (SDK default) | Development/single-node. No redundancy |
| `1` | Production. Survives one node failure |
| `2+` | High-availability production. Survives multiple failures |

> **Reference:** [OpenSearch Index Settings](https://opensearch.org/docs/latest/im-plugin/index-settings/)

### Index Templates

An **index template** is a preconfigured set of settings and mappings that are automatically applied when a new index is created with a matching name pattern.

The SDK uses templates to ensure that:
- Analyzer configuration is consistently applied
- Field mappings are predefined before indexing
- New indexes automatically inherit the configuration

```json
{
  "index_patterns": ["index_{hash}_de"],
  "settings": { ... },
  "mappings": { ... }
}
```

> **Reference:** [OpenSearch Index Templates](https://opensearch.org/docs/latest/im-plugin/index-templates/)

### k-NN (Vector Search)

**k-Nearest Neighbors (k-NN)** is a search technique that finds the `k` most similar vectors to a given query vector. OpenSearch implements this using the **HNSW (Hierarchical Navigable Small World)** algorithm for approximate nearest neighbor search.

**HNSW** is a graph-based algorithm that:
- Builds a multi-layer graph during indexing
- Performs a greedy search from top (sparse) layers to bottom (dense) layers
- Trades a small amount of accuracy for dramatically faster search (compared to brute-force)

**Space Types** define how similarity is measured:

| Space Type | SDK Config Value | Description | Range |
|---|---|---|---|
| Cosine Similarity | `cosinesimil` | Measures the angle between vectors. Most common for text embeddings | 0.0 to 2.0 (0 = identical) |
| L2 (Euclidean) | `l2` | Measures straight-line distance in vector space | 0.0 to ∞ (0 = identical) |
| Inner Product | `innerproduct` | Dot product of vectors. Fast but requires normalized vectors | -∞ to ∞ (higher = more similar) |

The SDK defaults to `cosinesimil` which is the standard for text embeddings because it focuses on directional similarity rather than magnitude.

> **Reference:** [OpenSearch k-NN Plugin](https://opensearch.org/docs/latest/search-plugins/knn/index/)

---

## Configuration

All OpenSearch settings are under `data.search_opensearch` in `config.json`:

### Basic Settings

```json
{
  "data": {
    "search_opensearch": {
      "enabled": false,
      "enabled_in_mongo_search": true,
      "prefix_length": 5,
      "stopwords": null
    }
  }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `enabled` | Boolean | `false` | Enable OpenSearch indexing during import. When `true`, media objects are indexed into OpenSearch during the import pipeline |
| `enabled_in_mongo_search` | Boolean | `true` | Use OpenSearch for fulltext queries within MongoDB search at query time. If `false`, OpenSearch is indexed but not queried |
| `prefix_length` | Integer | `5` | Number of leading characters that must match exactly in fuzzy queries. Higher values reduce false positives (e.g. "Berlin" no longer matches "Bernina"). See [Fuzzy Matching Tuning](#fuzzy-matching-tuning) |
| `stopwords` | mixed | `null` | Custom stop words configuration. `null` = use SDK built-in tourism-optimized list. Can also be an array of words, a file path, or a built-in identifier like `"_german_"`. See [Stop Words Configuration](#stop-words-configuration) |

**Important:** Both flags must be `true` for the hybrid search to work. Setting `enabled: true` but `enabled_in_mongo_search: false` allows indexing into OpenSearch without using it for search (useful for testing or building the index before switching over).

### Connection Settings

```json
{
  "data": {
    "search_opensearch": {
      "uri": "http://opensearch:9200",
      "username": null,
      "password": null,
      "timeout": 30,
      "max_retries": 2
    }
  }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `uri` | String | — | OpenSearch cluster URL (e.g. `http://opensearch:9200` or `https://search.example.com:9200`) |
| `username` | String/null | `null` | HTTP Basic Auth username. `null` = no authentication |
| `password` | String/null | `null` | HTTP Basic Auth password |
| `timeout` | Integer | `30` | HTTP request timeout in seconds |
| `max_retries` | Integer | `2` | Number of retry attempts on connection failure during indexing |

**Note:** SSL peer verification is disabled by default (`verify_peer: false`) in the SDK client. This simplifies connections to OpenSearch clusters with self-signed certificates (common in Docker/Kubernetes environments).

The SDK uses the `OpenSearch\SymfonyClientFactory` to create the HTTP client, passing `max_retries` to the factory constructor for automatic retry logic.

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
| `number_of_shards` | Integer | `1` | Number of primary shards per index. See [Shards and Replicas](#shards-and-replicas) |
| `number_of_replicas` | Integer | `0` | Number of replica shards per primary shard. See [Shards and Replicas](#shards-and-replicas) |

**Sizing guidelines:**

| Catalog Size | Shards | Replicas | Notes |
|---|---|---|---|
| < 10,000 products | 1 | 0 | Single node, development |
| 10,000 - 50,000 | 1 | 1 | Production, single node with safety |
| 50,000 - 200,000 | 2-3 | 1 | Multi-node cluster recommended |
| 200,000+ | 3-5 | 1-2 | Multi-node cluster required |

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

Each key in `index` becomes a field in the OpenSearch document. The key name is the **OpenSearch field name** – it does not need to match the MediaObject property name (the actual source property is defined in `object_type_mapping`).

**Mandatory system fields** (always created automatically):

| Field | Type | Description |
|---|---|---|
| `id` | `integer` | MediaObject primary key |
| `id_object_type` | `integer` | Object type ID |
| `fulltext` | `text` | Full aggregated fulltext (all searchable content combined) |

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
        "language": "de",
        "field": {
          "name": "headline_default",
          "params": []
        }
      },
      {
        "language": "en",
        "field": {
          "name": "headline_en",
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
| `[id]` | Array | Array of field definitions – multiple entries for different languages |
| `[id][].language` | String/null | Language filter: `null` = index in all language indexes, `"de"` = only in German index |
| `[id][].field.name` | String | MediaObject property name to read the value from |
| `[id][].field.params` | Array | Reserved for future filter/transform parameters |

**Field Resolution Logic:**

The indexer (`Indexer::createIndex()`) resolves field values in this order:

1. **Core MediaObject fields** (`id`, `code`, `name`, `tags`) → read from `$mediaObject->{field_name}`
2. **Content fields** (any other name) → read from `$mediaObject->getDataForLanguage($language)->{field_name}`
3. **String content** → stored directly after character sanitization
4. **Array content (Category Trees)** → automatically extracts `item->name` from all tree items and joins with spaces
5. **HTML content** → stripped to plain text (tags removed, entities decoded, whitespace normalized)

### Field Types & Boosting

| Type | OpenSearch Behavior | Analyzer Applied | Use For |
|---|---|---|---|
| `text` | Full-text analyzed. Stored in inverted index as tokens | `autocomplete_{lang}` (index) / `autocomplete_search_{lang}` (search) | Headlines, descriptions, category names, destinations |
| `keyword` | Exact match, not analyzed. Stored as single token | None | Product codes, IDs, exact identifiers |

**Boosting:**

```json
{
  "subline_default": {
    "type": "text",
    "boost": 2
  }
}
```

The `boost` value (default `1`) is applied at query time in the `multi_match` query using the `field^boost` syntax. It multiplies the BM25 relevance score for matches in this field.

| Boost | Effect | Use Case |
|---|---|---|
| `1` | Normal relevance (default) | Low-priority fields (long descriptions) |
| `2` | Double weight | Important content (sublines, category names) |
| `3-5` | Strong preference | Primary identifiers (headlines, destinations) |
| `10+` | Dominant | Exact match priority (product codes usually use `keyword` type instead) |

**Example:** If `zielgebiet_default` (destination) has `boost: 5` and `headline_default` has `boost: 3`, searching for "Mallorca" will:
1. First rank products where "Mallorca" appears in the destination field (5× weight)
2. Then products where "Mallorca" appears in the headline (3× weight)
3. Then products where "Mallorca" appears in default-boost fields

### Language Support

The SDK creates **separate OpenSearch indexes per language**:

```
index_{hash}        → fallback (no language)
index_{hash}_de     → German content
index_{hash}_en     → English content
```

Languages are automatically detected from the `object_type_mapping` configuration. If any field specifies a `language`, that language is added to the list of indexes to create.

Each language gets its own analyzer configuration:

**German (`de`):**
- Stemmer: `light_german` (Reisen → reis, Flüge → flug, Wanderungen → wanderung)
- Stopwords: `_german_` built-in list (der, die, das, und, ...)
- Autocomplete tokenizer: edge n-gram (2-20 chars)

**English (`en`):**
- Stemmer: `light_english` (traveling → travel, flights → flight)
- Stopwords: `_english_` built-in list (the, a, an, and, ...)
- Autocomplete tokenizer: edge n-gram (2-20 chars)

**Fallback:** If an unrecognized language code is used, the German configuration is applied as default.

If no language is specified in the field mapping (`"language": null`), the field is indexed in **all** language indexes.

---

## Vector Search Configuration

### Vector Search Overview

The SDK supports semantic vector search using dense embeddings. This allows finding relevant results based on meaning rather than exact keyword matching. For example, searching for "Beach holiday Mediterranean" can find products about "Mallorca sun vacation" even if those exact words don't appear.

Vector search requires:
1. An embedding provider (OpenAI API or local Ollama)
2. OpenSearch k-NN plugin enabled
3. Embeddings generated during indexing and at query time

### Vector Settings

```json
{
  "data": {
    "search_opensearch": {
      "vector": {
        "enabled": false,
        "enabled_in_search": false,
        "search_mode": "hybrid",
        "vector_field": "content_vector",
        "provider": "openai",
        "model": "text-embedding-3-small",
        "dimensions": 1536,
        "api_key_env": "OPENAI_API_KEY",
        "api_url": "https://api.openai.com/v1/embeddings",
        "space_type": "cosinesimil",
        "k": 50,
        "text_source": "fulltext",
        "lexical_weight": 0.4,
        "semantic_weight": 0.6,
        "min_text_length": 50,
        "min_score": 0.75,
        "cache": {
          "enabled": true,
          "query_cache_ttl": 604800
        }
      }
    }
  }
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `enabled` | Boolean | `false` | Enable vector embedding generation during indexing |
| `enabled_in_search` | Boolean | `false` | Enable vector search at query time |
| `search_mode` | String | `"hybrid"` | Search strategy: `"vector"` (k-NN only), `"hybrid"` (lexical + k-NN combined) |
| `vector_field` | String | `"content_vector"` | Name of the `knn_vector` field in the OpenSearch mapping |
| `provider` | String | `"openai"` | Embedding provider: `"openai"` or `"ollama"` |
| `model` | String | `"text-embedding-3-small"` | Model name passed to the embedding provider |
| `dimensions` | Integer | `1536` | Vector dimensionality. Must match the model's output dimension |
| `api_key_env` | String | `"OPENAI_API_KEY"` | Environment variable name containing the API key (OpenAI only) |
| `api_url` | String | `"https://api.openai.com/v1/embeddings"` | API endpoint URL |
| `space_type` | String | `"cosinesimil"` | OpenSearch k-NN similarity metric. See [k-NN section](#k-nn-vector-search) |
| `k` | Integer | `50` | Number of nearest neighbors to retrieve from vector search |
| `text_source` | String | `"fulltext"` | Which fields to embed. `"fulltext"` uses the aggregated fulltext, or comma-separated field names |
| `lexical_weight` | Float | `0.4` | Weight for lexical (BM25) scores in hybrid mode (0.0 to 1.0) |
| `semantic_weight` | Float | `0.6` | Weight for semantic (vector) scores in hybrid mode (0.0 to 1.0) |
| `min_text_length` | Integer | `50` | Minimum text length required to generate an embedding. Shorter texts get `null` vector |
| `min_score` | Float | `0.75` | Minimum similarity score threshold. Results below this are filtered out |
| `cache.enabled` | Boolean | `true` | Enable MongoDB-based embedding cache (reduces API calls and cost) |
| `cache.query_cache_ttl` | Integer | `604800` | Time-to-live for cached query embeddings in seconds (default: 7 days) |

### Embedding Providers

#### OpenAI

```json
{
  "provider": "openai",
  "model": "text-embedding-3-small",
  "dimensions": 1536,
  "api_key_env": "OPENAI_API_KEY",
  "api_url": "https://api.openai.com/v1/embeddings"
}
```

Uses the OpenAI Embeddings API. Requires a valid API key in the environment variable specified by `api_key_env`.

**Available models:**

| Model | Dimensions | Cost | Quality |
|---|---|---|---|
| `text-embedding-3-small` | 1536 | Low | Good for most use cases |
| `text-embedding-3-large` | 3072 | Medium | Higher quality, more storage |
| `text-embedding-ada-002` | 1536 | Low | Legacy model |

The provider supports batching (multiple texts in one API call) for efficiency during indexing.

#### Ollama (Local)

```json
{
  "provider": "ollama",
  "model": "nomic-embed-text",
  "dimensions": 768,
  "api_url": "http://127.0.0.1:11434"
}
```

Uses a locally running Ollama instance. No API key required. Lower latency, no API costs, but requires local GPU/compute resources.

**Common Ollama models:**

| Model | Dimensions | Notes |
|---|---|---|
| `nomic-embed-text` | 768 | Good general-purpose, 8K context |
| `mxbai-embed-large` | 1024 | Higher quality, 512 token limit |
| `all-minilm` | 384 | Fast, lower quality |

**Important:** The `dimensions` value in the config MUST exactly match the model's actual output dimension. A mismatch will cause indexing errors.

### Search Modes

#### `vector` Mode

Pure k-NN search. Only uses vector similarity to find results:

```php
$search->getVectorSearchResultIds($queryVector, $k);
```

- Finds semantically similar documents regardless of exact keyword matches
- Best when users describe what they want in natural language
- May miss results that match keywords but are semantically distant

#### `hybrid` Mode (Recommended)

Combines lexical BM25 search with k-NN vector search using weighted score fusion:

```php
$search->getHybridSearchResultIds($term, $queryVector, $k);
```

**Score fusion algorithm:**

1. Execute lexical search → get document scores
2. Execute vector search → get document scores
3. Normalize both score sets to [0, 1] range (divide by max score)
4. Combine: `final_score = lexical_weight × normalized_lexical + semantic_weight × normalized_semantic`
5. Sort by combined score, return top-k

This ensures:
- Exact keyword matches are still found (via lexical)
- Semantically related content is discovered (via vector)
- Both result sets contribute proportionally to the final ranking

**Tuning weights:**

| Scenario | lexical_weight | semantic_weight | Rationale |
|---|---|---|---|
| Product code + name search | 0.7 | 0.3 | Users search with exact terms |
| Inspirational search | 0.3 | 0.7 | Users describe what they want |
| Balanced (default) | 0.4 | 0.6 | Good for most travel search use cases |

### Embedding Cache

The `EmbeddingCache` uses MongoDB to cache embeddings and reduce API calls:

**Two cache collections:**

| Collection | Purpose | TTL |
|---|---|---|
| `embedding_cache` | Document embeddings (generated during indexing) | No TTL (permanent) |
| `query_embedding_cache` | Query embeddings (generated at search time) | `query_cache_ttl` (default 7 days) |

**Cache key:** `md5(model + "|" + dimensions + "|" + text)`

**How it works during indexing:**
1. Before calling the embedding API, check cache for the document text
2. If cached → use cached vector (free, instant)
3. If not cached → call API → store result in cache

**How it works at search time:**
1. Normalize query (lowercase, trim)
2. Check `query_embedding_cache` for this query + model + dimensions
3. If found and not expired → return cached vector
4. If expired or not found → call API → store with TTL

This dramatically reduces costs for repeated searches (e.g. popular queries) and re-indexing (same content, same embedding).

---

## Index Management

### Index Template Creation

Before any documents can be indexed, index templates must be created. This happens automatically during the first `upsertMediaObject()` call or can be triggered manually:

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
- k-NN vector field (if vector search enabled)

**Index template structure created by the SDK:**

```json
{
  "name": "index_{hash}_{lang}",
  "body": {
    "index_patterns": ["index_{hash}_{lang}"],
    "settings": {
      "number_of_shards": 1,
      "number_of_replicas": 0,
      "index.knn": true,
      "analysis": {
        "tokenizer": {
          "autocomplete_tokenizer": {
            "type": "edge_ngram",
            "min_gram": 2,
            "max_gram": 20,
            "token_chars": ["letter", "digit"]
          }
        },
        "filter": {
          "german_stemmer": { "type": "stemmer", "language": "light_german" },
          "german_stop": { "type": "stop", "stopwords": "_german_" }
        },
        "analyzer": {
          "german_default": { "type": "standard", "stopwords": "_german_" },
          "autocomplete_de": { ... },
          "autocomplete_search_de": { ... }
        }
      }
    },
    "mappings": {
      "properties": {
        "id": { "type": "integer" },
        "id_object_type": { "type": "integer" },
        "fulltext": { "type": "text", "analyzer": "german_default" },
        "headline_default": {
          "type": "text",
          "analyzer": "autocomplete_de",
          "search_analyzer": "autocomplete_search_de"
        },
        "code": { "type": "keyword" },
        "content_vector": {
          "type": "knn_vector",
          "dimension": 1536,
          "method": { "name": "hnsw", "space_type": "cosinesimil" }
        }
      }
    }
  }
}
```

### Index Naming Convention

```
index_{configHash}
index_{configHash}_{language}
```

The `configHash` is an MD5 hash of the OpenSearch config object (excluding connection-only fields: `uri`, `username`, `password`). This ensures:
- **Config changes create new indexes automatically** – Adding a field or changing boost values generates a new hash
- **Old indexes from previous configurations are cleaned up** – `deleteAllIndexesThatNotMatchConfigHash()` removes stale indexes
- **Connection changes don't trigger reindexing** – Changing the URI/credentials doesn't affect the hash

**Hash calculation:**
```php
$config = $this->_config; // full search_opensearch config
unset($config['uri'], $config['username'], $config['password']);
return md5(serialize($config));
```

### Automatic Analyzers

The SDK creates these analyzers per language:

**For German (`de`):**

| Analyzer | Type | Pipeline | Usage |
|---|---|---|---|
| `german_default` | Standard + German stopwords | tokenize → lowercase → remove stopwords | `fulltext` field |
| `autocomplete_de` | Custom: edge_ngram + stemmer | edge_ngram → lowercase → stop → stem | Index-time for `text` fields |
| `autocomplete_search_de` | Custom: standard + stemmer | standard → lowercase → stop → stem | Search-time for `text` fields |

**For English (`en`):**

| Analyzer | Type | Pipeline | Usage |
|---|---|---|---|
| `english_default` | Standard + English stopwords | tokenize → lowercase → remove stopwords | `fulltext` field |
| `autocomplete_en` | Custom: edge_ngram + stemmer | edge_ngram → lowercase → stop → stem | Index-time for `text` fields |
| `autocomplete_search_en` | Custom: standard + stemmer | standard → lowercase → stop → stem | Search-time for `text` fields |

**Why different index and search analyzers?**

The asymmetric analyzer pattern is key to how autocomplete works:

- **At index time:** "Mallorca" → `["ma", "mal", "mall", ..., "mallorca"]` (all edge n-grams stored)
- **At search time:** "mall" → `["mall"]` (searched as a single token)
- **Result:** The search token "mall" matches the indexed token "mall", finding "Mallorca"

If the search analyzer also used edge n-grams, searching for "mall" would generate `["ma", "mal", "mall"]` and match too many unrelated terms.

### Index Lifecycle

```
1. First import run:
   └─ createIndexTemplates() → creates template + empty index per language
   └─ upsertMediaObject()   → indexes all documents

2. Subsequent imports:
   └─ upsertMediaObject()   → updates/creates individual documents
   └─ Orphan detection      → deletes documents for missing media objects

3. Config change (field added/removed, boost changed):
   └─ createIndexTemplates() → creates new template + index (new hash)
   └─ deleteAllIndexesThatNotMatchConfigHash() → removes old indexes
   └─ Reindex required      → all documents must be re-indexed

4. MediaObject deletion:
   └─ Orphan detection in upsertMediaObject() → deletes from OpenSearch

5. Connection failure:
   └─ indexWithRetry()      → up to max_retries attempts with 500ms delay
   └─ reconnectOpenSearchClient() → fresh client on retry
```

---

## Search Query Architecture

### Lexical Search (Bool/Should Query)

The SDK's standard fulltext search uses a `bool` query with `should` clauses to combine text and keyword searches:

```json
{
  "query": {
    "bool": {
      "should": [
        {
          "multi_match": {
            "query": "Mallorca",
            "fields": ["headline_default^1", "subline_default^2", "zielgebiet_default^2"],
            "type": "best_fields",
            "operator": "and",
            "fuzziness": "AUTO",
            "prefix_length": 5
          }
        },
        {
          "multi_match": {
            "query": "Mallorca",
            "fields": ["code^1"],
            "type": "best_fields",
            "operator": "and"
          }
        }
      ],
      "minimum_should_match": 1,
      "filter": []
    }
  }
}
```

**Why `should` instead of `must`?**

The `should` clause with `minimum_should_match: 1` creates an OR relationship between the text-field search and the keyword-field search. A document matches if:
- It matches the fuzzy text search in any `text` field, OR
- It matches the exact keyword search in any `keyword` field, OR
- It matches both (higher score from both contributions)

This is important because:
- `text` fields apply fuzziness (typo tolerance) but `keyword` fields don't
- A product code "MAL-001" should match an exact search for "MAL-001" via the keyword clause
- A destination "Mallorca" should match a fuzzy search "Mallroca" via the text clause

**The SDK separates queries by field type:**

1. **Text fields** → `multi_match` with `fuzziness: AUTO` and `prefix_length: 3`
2. **Keyword fields** → `multi_match` without fuzziness (exact match only)

Both are placed in `should` so either can match independently, but matching both results in a higher score (additive).

### Multi-Match Query Types

The SDK uses different `multi_match` types depending on the search context:

#### Standard Search: `best_fields`

```json
{
  "multi_match": {
    "query": "Reise Mallorca",
    "fields": ["headline^3", "subline^2", "zielgebiet^5"],
    "type": "best_fields",
    "operator": "and"
  }
}
```

**`best_fields` behavior:**
- Executes a `match` query per field
- Takes the **highest score** from any single field
- Applies the field's boost multiplier
- Best when the same information may appear in different fields

**`operator: and` behavior:**
- "Reise Mallorca" → both "Reise" AND "Mallorca" must appear in the SAME field
- Without `and` (default `or`): either word matching would be enough (too permissive)

#### Autocomplete: `phrase_prefix`

```json
{
  "multi_match": {
    "query": "Mallor",
    "fields": ["headline^3", "zielgebiet^5"],
    "type": "phrase_prefix",
    "operator": "and"
  }
}
```

**`phrase_prefix` behavior:**
- All terms except the last must match exactly
- The last term is treated as a prefix (matches any token starting with it)
- "Reise Mall" → matches "Reise" exactly + any token starting with "Mall" → "Mallorca", "Malle"

### Autocomplete / Phrase Prefix

For autocomplete suggestions, the SDK uses a specialized query:

```php
$search->fetchAutocompleteSuggestions();
```

```json
{
  "_source": ["id"],
  "size": 10,
  "sort": [{"_score": "desc"}],
  "query": {
    "multi_match": {
      "query": "Mall",
      "fields": ["headline_default^1", "subline_default^2", "zielgebiet_default^2"],
      "type": "phrase_prefix",
      "operator": "and"
    }
  }
}
```

Key differences from standard search:
- `type: phrase_prefix` – last term treated as prefix
- Only `text` type fields (no `keyword` – partial match on exact fields doesn't make sense)
- Limited to `10` results
- No fuzziness (prefix matching provides sufficient flexibility)
- Returns immediately (no scroll/pagination)

### Vector Search (k-NN Query)

When `vector.enabled_in_search` is `true` and `search_mode` is `"vector"`:

```php
$ids = $search->getVectorSearchResultIds($queryVector, $k);
```

Generates this OpenSearch query:

```json
{
  "_source": false,
  "size": 50,
  "query": {
    "knn": {
      "content_vector": {
        "vector": [0.0123, -0.0456, 0.0789, ...],
        "k": 50
      }
    }
  }
}
```

**Process:**
1. User query text is embedded into a vector via the configured provider
2. Vector is sent to OpenSearch k-NN query
3. OpenSearch finds the `k` most similar document vectors using HNSW
4. Results below `min_score` threshold are filtered out
5. Document IDs are returned ordered by similarity

### Hybrid Search (Score Fusion)

When `search_mode` is `"hybrid"`:

```php
$ids = $search->getHybridSearchResultIds($term, $queryVector, $k);
```

**Algorithm implementation:**

```
1. Execute k-NN vector search → semMap {docId: score, ...}
2. Execute lexical bool/should search → lexMap {docId: score, ...}
3. Normalize scores:
   - maxL = max(lexMap scores) or 1.0
   - maxS = max(semMap scores) or 1.0
   - normalizedLexical[id] = lexMap[id] / maxL
   - normalizedSemantic[id] = semMap[id] / maxS
4. Combine all unique document IDs from both result sets
5. For each document:
   combined[id] = lexical_weight × normalizedLexical[id]
                + semantic_weight × normalizedSemantic[id]
6. Sort by combined score (descending)
7. Return top-k document IDs
```

**Edge cases handled:**
- If vector search returns no results → falls back to lexical-only results
- If lexical search returns no results → falls back to vector-only results
- If both return nothing → returns empty array
- If a document only appears in one result set → the missing score is treated as 0

### Search Term Sanitization

Before querying, the search term is sanitized to prevent injection and ensure clean queries:

```php
function sanitizeSearchTerm(string $input): string {
    return trim(preg_replace('/[\x00-\x1F]+/', ' ', FulltextSearch::replaceChars($input)));
}
```

1. `FulltextSearch::replaceChars()` – Normalizes special characters and diacritics
2. Remove control characters (`\x00-\x1F`) – Replace with space
3. `trim()` – Remove leading/trailing whitespace

### Pagination with search_after

The SDK uses OpenSearch's `search_after` cursor-based pagination to retrieve all matching document IDs:

```json
{
  "size": 100,
  "sort": [
    {"_score": "desc"},
    {"id": "asc"}
  ],
  "search_after": [3.45, 1234]
}
```

**How it works:**
1. First request: retrieve first `size` results, sorted by score (desc) then ID (asc)
2. Take the `sort` values from the last hit (e.g. `[3.45, 1234]`)
3. Pass as `search_after` in the next request
4. Repeat until no more hits are returned

**Why `search_after` instead of `from/size`?**
- `from/size` has a default 10,000 document limit and performance degrades with deep pagination
- `search_after` has no depth limit and consistent performance
- The SDK may need all matching IDs (for MongoDB `$in` filter), potentially thousands

The `id` field as secondary sort ensures deterministic ordering for documents with the same score.

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
5. Execute OpenSearch query with the search string (limit configurable, default 100)
6. Create a new `MediaObject` condition with the returned IDs: `{id: {$in: [45, 89, 123, ...]}}`
7. Add the `MediaObject` condition to the MongoDB query
8. MongoDB executes the remaining aggregation pipeline with the ID filter

### Impact on MongoDB Index

When OpenSearch is **enabled**, MongoDB's text index is **not created**:

```php
// In MongoDB Indexer:
if (!$this->_use_opensearch) {
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
if (!$this->_use_opensearch) {
    $searchObject->fulltext = FulltextSearch::getFullTextWords(...);
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
            └─ allIndexTemplatesExist()? → If not: createIndexTemplates()
            └─ For each language:
                 └─ createIndex($id, $language)
                      ├─ Load MediaObject (base row only, no full ORM graph)
                      ├─ Load getDataForLanguage($language)
                      ├─ Generate fulltext via FulltextSearch::getFullTextWords()
                      ├─ For each configured field:
                      │   ├─ Read field value (string/category/HTML)
                      │   ├─ Convert HTML to plaintext (htmlToFulltext)
                      │   ├─ Extract category names (for array fields)
                      │   └─ Sanitize special characters (FulltextSearch::replaceChars)
                      ├─ If vector.enabled:
                      │   ├─ Build embedding source text (from text_source config)
                      │   ├─ Check min_text_length threshold
                      │   ├─ Check embedding cache (MongoDB)
                      │   ├─ If not cached: call embedding provider API
                      │   ├─ Store embedding in cache
                      │   └─ Attach vector to document
                      └─ Index document in OpenSearch (with retry logic)
```

**Retry logic during indexing:**
- If OpenSearch returns an error, the SDK retries up to `max_retries` times
- Between retries: fresh client connection + 500ms delay
- After all retries exhausted: skip document and log warning

**Orphan handling:**
- If a media object ID is in the batch but not found in the database, its document is deleted from OpenSearch
- This handles cases where products are removed from pressmind

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

The cache is TTL-based and must be explicitly requested via the `$ttl` parameter in `getResult()`:

```php
$search = new \Pressmind\Search\OpenSearch('Mallorca', 'de', 100);
$result = $search->getResult(false, 3600); // TTL: 1 hour
```

**Cache bypass:** Set `$skip_cache = true` or use the `no_cache` parameter to bypass the cache.

---

## Complete Configuration Example

```json
{
  "data": {
    "search_opensearch": {
      "enabled": true,
      "enabled_in_mongo_search": true,
      "uri": "https://opensearch.example.com:9200",
      "username": "admin",
      "password": "secretPassword123",
      "timeout": 30,
      "max_retries": 2,
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
      },
      "vector": {
        "enabled": true,
        "enabled_in_search": true,
        "search_mode": "hybrid",
        "vector_field": "content_vector",
        "provider": "openai",
        "model": "text-embedding-3-small",
        "dimensions": 1536,
        "api_key_env": "OPENAI_API_KEY",
        "api_url": "https://api.openai.com/v1/embeddings",
        "space_type": "cosinesimil",
        "k": 50,
        "text_source": "fulltext",
        "lexical_weight": 0.4,
        "semantic_weight": 0.6,
        "min_text_length": 50,
        "min_score": 0.75,
        "cache": {
          "enabled": true,
          "query_cache_ttl": 604800
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
- Enables hybrid vector search with OpenAI embeddings
- Caches query embeddings for 7 days in MongoDB

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
    "enabled_in_mongo_search": true,
    "vector": { "enabled": false }
  }
}
```

- OpenSearch handles all fulltext queries (lexical only)
- MongoDB text index is NOT created (smaller, faster)
- Fuzzy matching, language-aware stemming, field boosting
- Autocomplete support for search suggestions
- **Best search quality for keyword-based search**

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

### Scenario 4: Hybrid Search with Vector Embeddings

```json
{
  "search_opensearch": {
    "enabled": true,
    "enabled_in_mongo_search": true,
    "vector": {
      "enabled": true,
      "enabled_in_search": true,
      "search_mode": "hybrid",
      "provider": "openai",
      "model": "text-embedding-3-small",
      "dimensions": 1536,
      "lexical_weight": 0.4,
      "semantic_weight": 0.6,
      "min_score": 0.75
    }
  }
}
```

- Lexical search + vector similarity combined
- Finds results by both keyword match AND semantic meaning
- "Beach holiday" finds "Strandurlaub Mallorca" even without shared keywords
- Higher API costs due to embedding generation
- **Best search quality overall, requires embedding API**

---

## PHP Class Reference

### Pressmind\Search\OpenSearch

**File:** `src/Pressmind/Search/OpenSearch.php`

The main search class for executing fulltext queries against OpenSearch.

```php
$search = new \Pressmind\Search\OpenSearch($search_term, $language, $limit);
$ids = $search->getResult($auto_complete, $ttl);
```

| Method | Description |
|---|---|
| `__construct($search_term, $language, $limit)` | Initialize with search term, optional language, and result limit |
| `getResult($auto_complete, $ttl)` | Execute search and return array of media object IDs |
| `fetchAutocompleteSuggestions()` | Get autocomplete suggestions (phrase_prefix query) |
| `fetchAllOpenSearchHits()` | Get all lexical search hits (paginated via search_after) |
| `getVectorSearchResultIds($queryVector, $k)` | k-NN only search, returns document IDs |
| `getHybridSearchResultIds($term, $queryVector, $k)` | Hybrid lexical + k-NN search |
| `getIndexTemplateName($language)` | Get the index name for a language |
| `getConfigHash()` | Get the MD5 config hash (excluding connection settings) |
| `sanitizeSearchTerm($input)` | Clean a search term for safe OpenSearch query |
| `generateCacheKey()` | Generate the Redis cache key for current search |
| `getLog()` | Get timestamped log entries (if advanced logging enabled) |

### Pressmind\Search\OpenSearch\AbstractIndex

**File:** `src/Pressmind/Search/OpenSearch/AbstractIndex.php`

Base class with shared index management logic.

| Method | Description |
|---|---|
| `getIndexTemplateName($language)` | Compute index name from config hash + language |
| `getConfigHash()` | MD5 hash of config (excluding uri/username/password) |
| `getLanguages()` | Extract all configured languages from index config |
| `getAllRequiredObjectTypes()` | Get all object type IDs from index config |
| `getIndexes()` | List all indexes in the OpenSearch cluster |
| `indexExists($templateName)` | Check if an index exists |
| `deleteAllIndexesThatNotMatchConfigHash()` | Remove stale indexes from old configurations |
| `htmlToFulltext($html)` | Convert HTML to searchable plain text |
| `getAnalyzerNameForLanguage($language)` | Get the default analyzer name for a language |
| `getDefaultFilterForLanguage($language)` | Get stemmer + stop filter config |
| `getDefaultAnalyzerForLanguage($language)` | Get full analyzer config (default + autocomplete) |
| `getStringWithLanguageSuffix($string, $language)` | Append language suffix if not already present |

### Pressmind\Search\OpenSearch\Indexer

**File:** `src/Pressmind/Search/OpenSearch/Indexer.php`

Handles index creation and document indexing.

| Method | Description |
|---|---|
| `createIndexTemplates()` | Create OpenSearch index templates and indexes for all languages |
| `createIndexes()` | Index all media objects for all configured object types |
| `allIndexTemplatesExist()` | Check if all required indexes exist |
| `upsertMediaObject($id_media_objects)` | Create or update OpenSearch documents for given media objects |
| `createIndex($idMediaObject, $language)` | Build a single document from a media object |
| `getFields($language, $id_object_type)` | Get configured fields for a language/object type |

### Pressmind\Search\Embedding Namespace

**Files:** `src/Pressmind/Search/Embedding/`

| Class | Description |
|---|---|
| `ProviderInterface` | Interface for embedding providers (`embed()`, `embedBatch()`, `getDimensions()`) |
| `ProviderFactory` | Creates the correct provider from config (`"openai"` or `"ollama"`) |
| `OpenAIProvider` | OpenAI Embeddings API implementation with batch support |
| `OllamaProvider` | Local Ollama API implementation |
| `QueryEmbedding` | Resolves query embeddings with optional cache |
| `EmbeddingCache` | MongoDB-based cache for document and query embeddings |

---

## Fuzzy Matching Tuning

The `prefix_length` setting controls how many leading characters of a search term must match exactly before fuzziness (Levenshtein edits) is applied. This is critical for avoiding false positives in tourism search.

### Problem

With the previous default (`prefix_length: 3`), searching for "Berlin" would also match "Bernina" (only 2 character difference after the 3rd position). Similarly, "Mittelmeer" would match "Mittelalter".

### Solution

The SDK now defaults to `prefix_length: 5`, which means the first 5 characters must match exactly. This eliminates most false positives while still allowing reasonable typo tolerance for longer terms.

### Configuration

```json
{
  "data": {
    "search_opensearch": {
      "prefix_length": 5
    }
  }
}
```

| Value | Effect | Example |
|---|---|---|
| `3` | Old default, very permissive | "Berlin" matches "Bernina" |
| `4` | Moderate | "Berlin" no longer matches "Bernina", but "Mittel" still matches both "Mittelmeer" and "Mittelalter" |
| `5` | New default, recommended | Eliminates most false positives in German tourism context |
| `6+` | Very strict | Almost no fuzziness, only for very long words |

**Recommendation:** Use the default `5` for German tourism search. Consider `4` only if you need more lenient typo tolerance.

---

## Stop Words Configuration

Stop words are common words that are removed from the search index and queries because they don't contribute to relevance. The SDK provides a **tourism-optimized German stop words list** that removes problematic geographic terms from the standard German list.

### Problem with Standard German Stop Words

The built-in OpenSearch `_german_` stop words list contains words that are also geographic names in the tourism context:

| Stop Word | Geographic Relevance |
|---|---|
| `seine` | River Seine (Paris river cruises) |
| `oder` | River Oder (cycling tours, Germany-Poland border) |
| `aller` | River Aller (Lower Saxony tours) |

With the standard list, searching for "Seine" (the river) would produce zero results because the term is removed before querying.

### SDK Built-in List

When `stopwords` is `null` (default), the SDK uses its built-in tourism-optimized list located at `src/Pressmind/Search/OpenSearch/resources/stopwords_de.txt`. This list is based on the standard German list with the above geographic terms removed.

### Configuration Options

```json
{
  "data": {
    "search_opensearch": {
      "stopwords": null
    }
  }
}
```

| Value | Behavior |
|---|---|
| `null` | Use SDK built-in tourism-optimized list (recommended) |
| `"_german_"` | Use OpenSearch built-in German list (legacy behavior) |
| `["word1", "word2", ...]` | Use a custom array of stop words |
| `"/path/to/file.txt"` | Load stop words from a text file (one word per line, `#` comments) |

### Custom Stop Words File Format

```text
# Custom stop words (one per line, # for comments)
aber
alle
als
am
an
```

**Important:** After changing stop words, you must recreate index templates and reindex all documents:

```bash
php bin/index-opensearch create_index_templates
php bin/index-opensearch all
```

---

## TermResolver (Category-Based Search Optimization)

The `TermResolver` detects when a user's search term matches a known category name (e.g. a destination, trip type, or ship name) and converts the fulltext search into a precise category filter. This ensures exact results without relying on fuzzy text matching.

### How It Works

```
User searches: "Berlin"
                │
                ▼
┌──────────────────────────────┐
│  TermResolver::resolve()     │
│                              │
│  1. Normalize: "berlin"      │
│  2. Lookup in dictionary     │
│  3. Match found:             │
│     field: zielgebiet_default│
│     id: "cat_12345"         │
│     name: "Berlin"          │
└──────────┬───────────────────┘
           │
           ▼
┌──────────────────────────────┐
│  Search Controller           │
│                              │
│  Instead of:                 │
│    pm-t=Berlin (fulltext)    │
│  Uses:                       │
│    pm-c[zielgebiet]=cat_123  │
│    (exact category filter)   │
└──────────────────────────────┘
```

### Dictionary Source

The TermResolver dictionary is automatically built from **all category fields** defined in the `search_mongodb.search.categories` configuration. It reads all unique category names from the MongoDB search collections and stores them in a pre-computed lookup collection.

### MongoDB Collections

The indexer creates `term_resolver_*` collections during `createIndexes()`:

| Collection Name | Content |
|---|---|
| `term_resolver_de_origin_0` | German terms, origin 0 |
| `term_resolver_origin_0` | Terms without language prefix, origin 0 |
| `term_resolver_de_origin_0_agency_1` | German terms, origin 0, agency 1 |

Each document in the collection:

```json
{
  "_id": "berlin",
  "field": "zielgebiet_default",
  "id_item": "cat_12345",
  "name": "Berlin",
  "count": 42
}
```

### Configuration

The TermResolver requires no explicit configuration. It automatically derives the resolvable fields from:

```json
{
  "data": {
    "search_mongodb": {
      "search": {
        "categories": {
          "123": {
            "zielgebiet_default": null,
            "reiseart_default": null
          }
        }
      }
    }
  }
}
```

All fields listed in `categories` are automatically included in the TermResolver dictionary.

### Rebuilding the Dictionary

The dictionary is rebuilt automatically when the MongoDB indexer runs `createIndexes()`. To manually rebuild:

```php
$indexer = new \Pressmind\Search\MongoDB\Indexer();
$indexer->rebuildTermDictionary();
```

Or via the standard CLI:

```bash
php bin/index-mongo
```

### PHP Usage

```php
use Pressmind\Search\TermResolver;

$match = TermResolver::resolve('Berlin', 'de', 0);
// Returns: ['field' => 'zielgebiet_default', 'id' => 'cat_123', 'name' => 'Berlin', 'count' => 42]
// Or null if no match found

$fields = TermResolver::getCategoryFields();
// Returns: ['zielgebiet_default', 'reiseart_default']
```

### Integration in Travelshop

In a Travelshop theme's `BuildSearch.php`, the TermResolver is called before the fulltext search:

```php
if (!empty($term)) {
    $termMatch = \Pressmind\Search\TermResolver::resolve($term);
    if ($termMatch !== null) {
        $request[$prefix.'-c'][$termMatch['field']] = $termMatch['id'];
        $term = null; // Skip fulltext search
    }
}
```

---

## Troubleshooting

### Common Issues

| Symptom | Cause | Solution |
|---|---|---|
| "Index template does not exist" | First run, templates not yet created | Run `createIndexTemplates()` before indexing |
| Empty search results with OpenSearch | `enabled_in_mongo_search` is `false` | Set both `enabled` and `enabled_in_mongo_search` to `true` |
| Fuzzy matching not working for short queries | `prefix_length: 5` (default) requires first 5 chars correct | Expected behavior – reduce `prefix_length` in config if more lenience needed |
| False positives like "Berlin" → "Bernina" | `prefix_length` too low | Increase `prefix_length` (default 5 should prevent this) |
| Geographic names not found (e.g. "Seine") | Standard `_german_` stop words remove the term | Use SDK default (`stopwords: null`) which has these terms removed |
| Wrong language stemming | `language` field in mapping is wrong or `null` | Set correct language code in `object_type_mapping` |
| Stale search results after config change | Old index with different config hash | `deleteAllIndexesThatNotMatchConfigHash()` runs during template creation |
| OpenSearch connection refused | Wrong URI or OpenSearch not running | Verify `uri` setting and OpenSearch service status |
| Vector search returns no results | `min_score` too high or embeddings not generated | Lower `min_score` or check indexing logs for embedding errors |
| "Embedding API key not set" | Environment variable not configured | Set `OPENAI_API_KEY` (or configured env var) in the environment |
| "Embedding dimension mismatch" | Config `dimensions` doesn't match model output | Align `dimensions` config with the actual model dimension |
| High API costs from embeddings | No cache or cache expired | Enable `cache.enabled: true` and increase `query_cache_ttl` |
| Indexing timeout | Large documents or slow network | Increase `timeout` setting or reduce `text_source` content |
| 403 Forbidden during indexing | Cluster memory pressure or permission issue | Check cluster health, increase memory, or verify credentials |

### CLI Commands

The SDK provides the `index-opensearch` CLI command for managing the OpenSearch index. This is the primary tool for indexing, debugging, and verifying the search infrastructure.

**Binary location:** `bin/index-opensearch` (SDK standalone) or `cli/index_opensearch.php` (Travelshop project)

#### Full Reindex

Indexes all media objects of all configured object types into OpenSearch. Creates index templates if they don't exist yet.

```bash
php bin/index-opensearch all
```

**Use when:**
- Initial setup after enabling OpenSearch
- After a config change (new fields, changed boost values)
- Recovery from corrupted/empty index
- After manual index deletion

**Duration:** Depends on catalog size. ~100-500 media objects/second (without vector embeddings), ~10-50/second (with embeddings due to API calls).

#### Index Specific Media Objects

Indexes or updates one or more specific media objects by ID.

```bash
php bin/index-opensearch mediaobject 12345
php bin/index-opensearch mediaobject 12345,12346,12347
```

**Use when:**
- Testing index creation for a single product
- Manually reindexing after a content update
- Debugging why a specific product isn't found in search

#### Create Index Templates

Creates the OpenSearch index templates (settings, analyzers, mappings) and the indexes themselves without indexing any documents. Lists all existing indexes after creation.

```bash
php bin/index-opensearch create_index_templates
```

**Use when:**
- Verifying analyzer/mapping configuration before full reindex
- Setting up a new OpenSearch cluster
- After a config change to create the new template (old indexes are automatically removed)

**Output example:**
```
Current indexes:
  - index_a1b2c3d4_de | status open | health: green
  - index_a1b2c3d4_en | status open | health: green
```

#### Search Test

Executes a search query against the OpenSearch index and returns matching media object IDs. Useful for verifying that the index works correctly.

```bash
php bin/index-opensearch search "Mallorca" de
php bin/index-opensearch search "MAL-001"
php bin/index-opensearch search "Strandurlaub" de
```

**Arguments:**
- Argument 1: Search term (quote multi-word terms)
- Argument 2: Language code (optional, e.g. `de`, `en`)

**Output example:**
```
Found 12 results for term 'Mallorca'
45
89
123
456
...
```

**Use when:**
- Verifying a product is findable after indexing
- Testing fuzzy matching (try misspellings)
- Checking if boost configuration produces expected ranking
- Comparing results before/after config changes

#### Help

Displays available subcommands and usage examples.

```bash
php bin/index-opensearch --help
php bin/index-opensearch help
```

#### Typical Workflow

```bash
# 1. Create index templates (verify config is correct)
php bin/index-opensearch create_index_templates

# 2. Full reindex (populate the index)
php bin/index-opensearch all

# 3. Verify with a test search
php bin/index-opensearch search "Mallorca" de

# 4. After content changes: reindex specific products
php bin/index-opensearch mediaobject 12345,12346

# 5. After config changes: templates are recreated, then full reindex
php bin/index-opensearch create_index_templates
php bin/index-opensearch all
```

### Debugging

Enable debug output for OpenSearch queries:

```php
// Via GET parameter
?debug=1

// Via constant
define('PM_SDK_DEBUG', true);
```

This prints the raw OpenSearch query JSON and vector result counts to the output.

Enable advanced logging:

```json
{
  "logging": {
    "enable_advanced_object_log": true
  }
}
```

This adds timestamped log entries for each OpenSearch operation, accessible via `$search->getLog()`.

### Verifying the Index

Use the OpenSearch REST API directly to inspect your index:

```bash
# Check cluster health
curl -k https://opensearch:9200/_cluster/health

# List all indexes
curl -k https://opensearch:9200/_cat/indices?v

# View index mapping
curl -k https://opensearch:9200/index_{hash}_de/_mapping

# Count documents
curl -k https://opensearch:9200/index_{hash}_de/_count

# Search manually
curl -k -X POST https://opensearch:9200/index_{hash}_de/_search \
  -H 'Content-Type: application/json' \
  -d '{"query": {"match": {"headline_default": "Mallorca"}}}'
```

---

## Further Reading

- [OpenSearch Documentation](https://opensearch.org/docs/latest/)
- [OpenSearch Query DSL](https://opensearch.org/docs/latest/query-dsl/)
- [OpenSearch Analyzers](https://opensearch.org/docs/latest/analyzers/)
- [OpenSearch k-NN Plugin](https://opensearch.org/docs/latest/search-plugins/knn/)
- [OpenSearch Field Types](https://opensearch.org/docs/latest/field-types/)
- [BM25 Scoring Algorithm](https://opensearch.org/docs/latest/search-plugins/search-relevance/)
- [OpenAI Embeddings API](https://platform.openai.com/docs/guides/embeddings)
- [Ollama Embeddings](https://ollama.com/blog/embedding-models)
