# pressmind® SDK

This library is meant to be used as a composer requirement and is not intended to be used *as-is*. 

## Install

```bash
composer require pressmind/sdk
```

## Documentation

The complete documentation is in the `documentation` folder:

**[→ Documentation Index](documentation/documentation.md)**

### Key Documentation Pages

| Topic | Link |
|---|---|
| Architecture & Design Patterns | [architecture.md](documentation/architecture.md) |
| Configuration Reference | [configuration.md](documentation/configuration.md) |
| Configuration Examples (Production) | [config-examples.md](documentation/config-examples.md) |
| Touristic Data Model | [Booking Package](documentation/Touristic/Booking/Package.md), [Date](documentation/Touristic/Date.md), [Option](documentation/Touristic/Option.md), [Transport](documentation/Touristic/Transport.md) |
| MongoDB Search API | [search-mongodb-api.md](documentation/search-mongodb-api.md) |
| Import Process | [import-process.md](documentation/import-process.md) |
| CheapestPrice Aggregation | [cheapest-price-aggregation.md](documentation/cheapest-price-aggregation.md) |
| Template Interface | [template-interface.md](documentation/template-interface.md) |
| Real-World Examples | [real-world-examples.md](documentation/real-world-examples.md) |
| Troubleshooting | [troubleshooting-missing-products.md](documentation/troubleshooting-missing-products.md) |

## Misc

If you are looking for an *out-of-the-box* working implementation, please have a look at the 

[pressmind web-core](https://github.com/pressmind/web-core-skeleton-basic) for a working implementation in vanilla PHP

or ask us for a complete WordPress based Travelshop Theme.

## System Overview

The pressmind SDK is a caching layer for non-bookable (and extendable for bookable) content between pressmind® PIM and your application with a comfortable PHP and REST API.

```
+---------------------+          +-------------------------+
|                     |          |                         |
|   pressmind® PIM    |  REST    |     pressmind SDK       |
|                     | -------> |                         |
|   (Content &        |   API    |  +-------------------+  |
|    Touristic Data)  |          |  | Import Pipeline   |  |
|                     |          |  +--------+----------+  |
+---------------------+          |           |             |
                                 |           v             |
                                 |  +--------+----------+  |
                                 |  |   MySQL/MariaDB   |  |
                                 |  |                   |  |
                                 |  | - MediaObjects    |  |
                                 |  | - Touristic Data  |  |
                                 |  | - CheapestPrice   |  |
                                 |  +--------+----------+  |
                                 |           |             |
                                 |           v             |
                                 |  +--------+----------+  |
                                 |  |     MongoDB       |  |
                                 |  |                   |  |
                                 |  | - Search Index    |  |
                                 |  | - Self-contained  |  |
                                 |  |   Documents       |  |
                                 |  +--------+----------+  |
                                 |           |             |
                                 +-----------|---+----------+
                                             |   |
                                 +-----------v-+ | REST API
                                 |             | |
                                 | Your App    | +--------->  IBE / CRS
                                 |             |
                                 | - Search    |
                                 | - Templates |
                                 | - Booking   |
                                 +-------------+
```

### Data Flow

```
1. PIM  ──REST──>  SDK Import  ──>  MySQL (MediaObjects, Touristic, Prices)
                                        │
2.                                      ├──>  Image Processor  ──>  Filesystem/S3
                                        │
3.                                      └──>  MongoDB Indexer   ──>  MongoDB (Search Docs)

4. User  ──HTTP──>  Your App   ──pm-*──>  SDK Search (Query)  ──>  MongoDB
                                                   │
5.                                                 └──>  Template Render  ──>  HTML
```
