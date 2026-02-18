# Pressmind SDK – Configuration Reference (`config.json`)

## Overview

The `config.json` is the central configuration file of the Pressmind SDK. It controls every aspect of the system – from database connections and touristic data imports to image processing and search engine integration.

The file is organized into **environments** (`development`, `testing`, `production`), where `development` serves as the reference configuration. The `testing` and `production` environments can override the `development` values.

## Accessing Configuration in Code

The SDK uses the **Registry pattern** to access configuration values:

```php
$config = \Pressmind\Registry::getInstance()->get('config');
$value  = $config['database']['host'];
```

Configuration is loaded through adapters (`Pressmind\Config\Adapter\Json` or `Pressmind\Config\Adapter\Php`).

### Placeholders / Constants

Many config values (especially paths) support placeholders that are automatically replaced at runtime:

| Placeholder | Description |
|---|---|
| `BASE_PATH` | Application root directory |
| `APPLICATION_PATH` | Application path |
| `WEBSERVER_DOCUMENT_ROOT` | Value from `server.document_root` |
| `WEBSERVER_HTTP` | Value from `server.webserver_http` |
| `DATABASE_NAME` | Value from `database.dbname` |

Replacement is handled by `HelperFunctions::replaceConstantsFromConfig()`.

---

## Structure of config.json

```json
{
  "development": {
    "server": { ... },
    "database": { ... },
    "rest": { ... },
    "ib3": { ... },
    "docs_dir": "...",
    "logging": { ... },
    "data": {
      "touristic": { ... },
      "media_type_custom_import_hooks": { ... },
      "media_type_custom_post_import_hooks": { ... },
      "search_hooks": [ ... ],
      "primary_media_type_ids": null,
      "media_types": { ... },
      "media_types_pretty_url": { ... },
      "media_types_fulltext_index_fields": { ... },
      "media_types_allowed_visibilities": { ... },
      "disable_recursive_import": { ... },
      "sections": { ... },
      "languages": { ... },
      "search_mongodb": { ... },
      "search_opensearch": { ... },
      "schema_migration": { ... },
      "import": { ... }
    },
    "price_format": { ... },
    "cache": { ... },
    "view_scripts": { ... },
    "scaffolder_templates": { ... },
    "file_handling": { ... },
    "image_handling": { ... }
  },
  "testing": [],
  "production": []
}
```

---

## Chapter Overview (Subpages)

Each section is documented in detail in its own file:

| Chapter | File | Description |
|---|---|---|
| Server & Database | [config-server-database.md](config-server-database.md) | Server settings, database connection, engine |
| REST API | [config-rest-api.md](config-rest-api.md) | Client & server configuration, authentication, controllers |
| Logging | [config-logging.md](config-logging.md) | Log modes, storage, lifetime, query logging |
| Touristic Data & Import | [config-touristic-data.md](config-touristic-data.md) | Origins, filters, offer generation, import hooks |
| Search (MongoDB, OpenSearch, Hooks) | [config-search.md](config-search.md) | MongoDB indexing, OpenSearch, search hooks |
| Cache | [config-cache.md](config-cache.md) | Redis cache, cache types, TTL, parameters |
| Image & File Handling | [config-image-file-handling.md](config-image-file-handling.md) | Derivatives, filters, storage (filesystem/S3), WebP |
| URL Strategies | [config-url-strategies.md](config-url-strategies.md) | Pretty URLs: channel, unique, count-up; config, field types, errors |
| Sections, Languages & Misc | [config-sections-languages-misc.md](config-sections-languages-misc.md) | Sections, languages, price format, views, scaffolder |

---

## Quick Reference – All Properties

| Property Path | Type | Default | Page |
|---|---|---|---|
| `server.document_root` | string | `"BASE_PATH/httpdocs"` | [Server](config-server-database.md) |
| `server.webserver_http` | string | `"http://127.0.0.1"` | [Server](config-server-database.md) |
| `server.php_cli_binary` | string | `"php"` | [Server](config-server-database.md) |
| `server.timezone` | string | `"Europe/Berlin"` | [Server](config-server-database.md) |
| `database.username` | string | `""` | [Database](config-server-database.md) |
| `database.password` | string | `""` | [Database](config-server-database.md) |
| `database.host` | string | `"127.0.0.1"` | [Database](config-server-database.md) |
| `database.port` | string | `"3306"` | [Database](config-server-database.md) |
| `database.dbname` | string | `""` | [Database](config-server-database.md) |
| `database.engine` | string | `"MySQL"` | [Database](config-server-database.md) |
| `rest.client.api_key` | string | `""` | [REST](config-rest-api.md) |
| `rest.client.api_user` | string | `""` | [REST](config-rest-api.md) |
| `rest.client.api_password` | string | `""` | [REST](config-rest-api.md) |
| `rest.server.api_endpoint` | string | `"/rest"` | [REST](config-rest-api.md) |
| `rest.server.api_key` | string | `""` | [REST](config-rest-api.md) |
| `rest.server.api_user` | string | `""` | [REST](config-rest-api.md) |
| `rest.server.api_password` | string | `""` | [REST](config-rest-api.md) |
| `rest.server.controller` | object | `{...}` | [REST](config-rest-api.md) |
| `ib3.endpoint` | string | `""` | [REST](config-rest-api.md) |
| `docs_dir` | string | `"WEBSERVER_DOCUMENT_ROOT/docs"` | [Misc](config-sections-languages-misc.md) |
| `logging.*` | various | – | [Logging](config-logging.md) |
| `data.touristic.*` | various | – | [Touristic](config-touristic-data.md) |
| `data.search_mongodb.*` | various | – | [Search](config-search.md) |
| `data.search_opensearch.*` | various | – | [Search](config-search.md) |
| `data.search_hooks` | array | `[]` | [Search](config-search.md) |
| `data.sections.*` | various | – | [Sections](config-sections-languages-misc.md) |
| `data.languages.*` | various | – | [Languages](config-sections-languages-misc.md) |
| `data.media_types` | object | `{}` | [Touristic](config-touristic-data.md) |
| `data.media_types_pretty_url` | object / array | `[]` | [URL Strategies](config-url-strategies.md) |
| `data.media_types_fulltext_index_fields` | object | `{}` | [Search](config-search.md) |
| `data.media_types_allowed_visibilities` | object | `{}` | [Touristic](config-touristic-data.md) |
| `data.schema_migration` | object | `{...}` | [Misc](config-sections-languages-misc.md) |
| `data.import` | object | `{...}` | [Touristic](config-touristic-data.md) |
| `cache.*` | various | – | [Cache](config-cache.md) |
| `price_format.*` | various | – | [Misc](config-sections-languages-misc.md) |
| `image_handling.*` | various | – | [Image](config-image-file-handling.md) |
| `file_handling.*` | various | – | [File](config-image-file-handling.md) |
| `view_scripts.*` | various | – | [Misc](config-sections-languages-misc.md) |
| `scaffolder_templates.*` | various | – | [Misc](config-sections-languages-misc.md) |

---

## Environments

The `config.json` supports three environments:

- **`development`** – Development environment (reference configuration)
- **`testing`** – Testing environment
- **`production`** – Production environment

The active environment is determined when the configuration is loaded. Values from `testing` or `production` override those from `development`.

```json
{
  "development": {
    "database": { "host": "127.0.0.1" }
  },
  "production": {
    "database": { "host": "db.production.example.com" }
  }
}
```

---

## Related Documentation

- **[Configuration Examples & Best Practices](config-examples.md)** – Real-world configuration patterns from ~40 production installations, complete examples for tour operators and cruise operators
- [Architecture & Design Patterns](architecture.md) – SDK design patterns, pipeline architecture, functional overview
- [REST API Endpoints](rest-api-endpoints.md) – Complete REST API reference with all controllers
- [MongoDB Search API (`pm-*` Parameters)](search-mongodb-api.md) – Complete query parameter reference
- [MongoDB Index Configuration](search-mongodb-index-configuration.md) – How to configure the search index (descriptions, categories, touristic, groups)
- [Import Process](import-process.md) – Complete import pipeline from pressmind PIM to local database
- [Image Processor](image-processor.md) – Image derivatives, filters, watermarks, storage providers
- [Template Interface](template-interface.md) – PHP template system, scaffolding, MediaObject::render()
- [OpenSearch Configuration](search-opensearch.md) – OpenSearch fulltext search and MongoDB integration
- [CheapestPrice Aggregation](cheapest-price-aggregation.md) – Price calculation pipeline, entities, early bird discounts, state machine
- [Troubleshooting: Missing Products](troubleshooting-missing-products.md) – 25 cases for products not appearing in search
- [Real-World Examples](real-world-examples.md) – 13 proven SDK usage patterns from ~80 production installations

---

## Notes

- The `config.default.json` file in the SDK root serves as a template. Create a copy as `config.json` and adjust the values accordingly.
- Properties prefixed with `EXAMPLE_` (e.g., `EXAMPLE_storage`, `EXAMPLE_search_hooks`) are purely illustrative and are ignored by the code.
- All paths should use placeholder constants to ensure portability.
- Empty strings (`""`) in required fields (e.g., `database.dbname`, `rest.client.api_key`) must be filled in before use.
