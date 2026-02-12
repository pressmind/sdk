# Pressmind SDK – Documentation

Welcome to the pressmind SDK documentation. This documentation covers the complete SDK — from configuration and architecture to the touristic data model, search engines, and template integration.

---

## Table of Contents

### Getting Started

| Document | Description |
|---|---|
| [Architecture & Design Patterns](architecture.md) | SDK design patterns (Registry, Active Record, Factory, Adapter, MVC, Pipeline), functional overview |
| [Configuration Reference](configuration.md) | Complete `config.json` / `pm-config.php` property reference |
| [Configuration Examples & Best Practices](config-examples.md) | Real-world configuration patterns from ~40 production installations |

### Configuration (Detail Pages)

| Document | Description |
|---|---|
| [Server & Database](config-server-database.md) | Server settings, database connection, engine configuration |
| [REST API Config](config-rest-api.md) | Client & server authentication, API keys, controller settings |
| [Logging](config-logging.md) | Log modes, storage backends, lifetime, query logging |
| [Touristic Data & Import](config-touristic-data.md) | Origins, date/state filters, offer generation, import hooks |
| [Search (MongoDB, OpenSearch, Hooks)](config-search.md) | MongoDB indexing, OpenSearch integration, search hooks |
| [Cache](config-cache.md) | Redis cache, cache types, TTL, bypass parameters |
| [Image & File Handling](config-image-file-handling.md) | Derivatives, WebP, filters, storage providers (filesystem/S3) |
| [Sections, Languages & Misc](config-sections-languages-misc.md) | Sections, languages, price format, views, scaffolder |

### Data Model

| Document | Description |
|---|---|
| [MediaObject](mediaobject.md) | Central entity — properties, visibility, relations |

#### Touristic Entities

| Document | Description |
|---|---|
| [Booking Package](Touristic/Booking/Package.md) | Root touristic entity — duration, price_mix, IBE type |
| [Date](Touristic/Date.md) | Departure/return dates, state enum, season matching |
| [Option](Touristic/Option.md) | Services (housing, extra, ticket, sightseeing) — price_due, required groups, state enums |
| [Housing Package](Touristic/Housing/Package.md) | Accommodation groups (rooms/cabins) |
| [Transport](Touristic/Transport.md) | Outbound/return transport pairs, IATA codes, transport types |
| [Startingpoint](Touristic/Startingpoint.md) | Boarding point groups (for bus travel) |
| [Startingpoint Option](Touristic/Startingpoint/Option.md) | Individual boarding points with addresses and pricing |
| [EarlyBirdDiscountGroup](Touristic/EarlybirdDiscountGroup.md) | Early bird discount schedules |
| [EarlyBirdDiscountGroup Item](Touristic/EarlybirdDiscountGroup/Item.md) | Individual discount entries with date ranges |
| [Option Discount](Touristic/Option/Discount.md) | Age/occupancy-based discount groups |
| [Discount Scale](Touristic/Option/Discount/Scale.md) | Individual discount rules (percentage, fixed, replacement) |
| [Option Description](Touristic/Option/Description.md) | Supplementary descriptions for extras/tickets/sightseeings |

### Search & Indexing

| Document | Description |
|---|---|
| [MongoDB Search API (`pm-*` Parameters)](search-mongodb-api.md) | Complete query parameter reference for the MongoDB search |
| [MongoDB Index Configuration](search-mongodb-index-configuration.md) | How to configure the search index (descriptions, categories, touristic, groups) |
| [OpenSearch Configuration](search-opensearch.md) | OpenSearch fulltext search and MongoDB integration |

### Import & Processing

| Document | Description |
|---|---|
| [Import Process](import-process.md) | Complete import pipeline from pressmind PIM to local database |
| [Import Safety](import-safety.md) | Orphan removal thresholds and transaction safety |
| [Schema Migration](schema-migration.md) | Automatic schema migration for new PIM fields |
| [Image Processor](image-processor.md) | Image derivatives, filters, watermarks, storage providers |
| [CheapestPrice Aggregation](cheapest-price-aggregation.md) | Price calculation pipeline, state machine, early bird discounts |

### CLI Reference

| Document | Description |
|---|---|
| [CLI Reference](cli-reference.md) | Complete CLI command reference: Import (primary), Index Mongo, and all other commands with parameter documentation |
| [CLI WordPress Tools](cli-wordpress-tools.md) | WordPress helpers and WordPress-dependent CLI commands; CMS independence and when to use |

### API

| Document | Description |
|---|---|
| [Pressmind Webcore API Endpoints](pressmind-api-endpoints.md) | External PIM API endpoints used by the SDK |
| [REST API Endpoints](rest-api-endpoints.md) | SDK's own REST API with all controllers |

### Templates & Frontend

| Document | Description |
|---|---|
| [Template Interface](template-interface.md) | PHP template system, scaffolding, `MediaObject::render()` |
| [Real-World Examples](real-world-examples.md) | 13 proven SDK usage patterns from ~80 production installations |

### Troubleshooting

| Document | Description |
|---|---|
| [Troubleshooting: Missing Products](troubleshooting-missing-products.md) | 25+ diagnostic scenarios for products not appearing in search |

---

## Entity Relationship Overview

```
MediaObject
 ├── data (AbstractMediaType[])        → dynamic content fields
 ├── routes (Route[])                  → pretty URLs
 ├── brand (Brand)                     → distribution brand
 ├── agencies (Agency[])               → agency assignments
 └── booking_packages (Package[])      → touristic packages
      ├── dates (Date[])               → departure/return dates
      │    ├── transports (Transport[]) → outbound/return travel
      │    │    └── starting_points     → boarding points (bus)
      │    └── early_bird_discount_group → discounts
      ├── housing_packages (Housing\Package[])
      │    └── options (Option[])       → rooms/cabins
      │         └── discount (Discount) → age/occupancy pricing
      ├── extras (Option[])             → additional services
      ├── tickets (Option[])            → entrance tickets
      └── sightseeings (Option[])       → excursions
```

---

## Data Flow Overview

```
pressmind PIM
     │
     ▼
 REST API (Webcore)
     │
     ▼
 SDK Import Pipeline
     │
     ├── MediaObject Data  → MySQL/MariaDB
     ├── Touristic Data    → MySQL/MariaDB
     ├── Images            → Image Processor → Filesystem/S3
     ├── CheapestPrice     → CheapestPriceSpeed Table (MySQL)
     └── MongoDB Index     → MongoDB (search documents)
                                │
                                ▼
                           Search Query (pm-* params)
                                │
                                ▼
                           Template Rendering
```

---

## Quick Links

- **New to the SDK?** Start with [Architecture](architecture.md) → [Configuration](configuration.md) → [Configuration Examples](config-examples.md)
- **Setting up search?** See [MongoDB Index Configuration](search-mongodb-index-configuration.md) → [Search API](search-mongodb-api.md)
- **Products not showing?** See [Troubleshooting](troubleshooting-missing-products.md)
- **Building templates?** See [Template Interface](template-interface.md) → [Real-World Examples](real-world-examples.md)
- **Understanding prices?** See [CheapestPrice Aggregation](cheapest-price-aggregation.md)
- **CLI commands (import, index-mongo, etc.)?** See [CLI Reference](cli-reference.md); for WordPress helpers see [CLI WordPress Tools](cli-wordpress-tools.md)
