# WordPress Full-Page Cache (PageCache)

[← CLI WordPress Tools](cli-wordpress-tools.md) | [Documentation Overview](documentation.md)

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
  - [1. wp-config.php Constants](#1-wp-configphp-constants)
  - [2. advanced-cache.php Drop-In](#2-advanced-cachephp-drop-in)
  - [3. Enable WP_CACHE](#3-enable-wp_cache)
- [Configuration Reference](#configuration-reference)
  - [Required Constants](#required-constants)
  - [Optional Constants](#optional-constants)
  - [Route-Based TTL](#route-based-ttl)
- [How It Works](#how-it-works)
  - [Cache Key Structure](#cache-key-structure)
  - [Request Flow](#request-flow)
  - [Background Key Refresh](#background-key-refresh)
  - [Tracking Parameter Stripping](#tracking-parameter-stripping)
- [Cache Bypass Conditions](#cache-bypass-conditions)
- [Cache Invalidation](#cache-invalidation)
  - [After SDK Import (CLI)](#after-sdk-import-cli)
  - [After SDK Import (HTTP Webhook)](#after-sdk-import-http-webhook)
  - [After WordPress Post Save](#after-wordpress-post-save)
  - [Manual Invalidation](#manual-invalidation)
- [Cache Priming](#cache-priming)
  - [Single-URL Prime](#single-url-prime)
  - [Batch Prime (curl_multi)](#batch-prime-curl_multi)
  - [Delta-Based Invalidation](#delta-based-invalidation)
- [Transient Interaction](#transient-interaction)
- [Debug Headers](#debug-headers)
- [Redis CLI Cheat Sheet](#redis-cli-cheat-sheet)
- [Backward Compatibility](#backward-compatibility)
- [Production Checklist](#production-checklist)

---

## Overview

`Pressmind\WordPress\PageCache` is a Redis-backed full-page cache for WordPress, shipped as part of the SDK. It operates as a WordPress `advanced-cache.php` drop-in — intercepting requests **before** WordPress and PHP execute, serving cached HTML directly from Redis.

**Key properties:**

- **TTFB < 200ms** for cached pages (vs. 1–3s uncached)
- Transparent background refresh — visitors never see stale content
- Automatic invalidation after SDK imports (per media object)
- Compatible with the WordPress Redis Object Cache plugin (separate Redis databases or key prefixes)
- IB3 booking session awareness — bypasses cache when `_ib3_sid` cookie is set

**File:** `src/Pressmind/WordPress/PageCache.php`
**Namespace:** `Pressmind\WordPress`
**Backward-compatible alias:** `\RedisPageCache` (via `class_alias`)

---

## Architecture

```
Browser Request
     │
     ▼
  Nginx / Apache
     │
     ▼
  PHP-FPM
     │
     ▼
  wp-settings.php (loads advanced-cache.php early)
     │
     ▼
  PageCache::cache_init()
     │
     ├── Cache HIT  → serve from Redis, exit (no WordPress boot)
     ├── Cache MISS  → ob_start(), let WordPress render, store in Redis
     └── Do Not Cache → pass through (logged-in, POST, admin, etc.)
```

---

## Requirements

- PHP 8.0+ with `ext-redis`
- Redis server (same host recommended for lowest latency)
- WordPress 6.0+
- Pressmind SDK installed via Composer

---

## Installation

### 1. wp-config.php Constants

Add these constants to `wp-config.php` **before** `/* That's all, stop editing! */`:

```php
// Pressmind WordPress PageCache
define('PM_REDIS_HOST', '127.0.0.1');
define('PM_REDIS_PORT', '6379');
define('PM_REDIS_TTL', 3600);                  // seconds (1 hour)
define('PM_REDIS_BACKGROUND_KEY_REFRESH', 900); // seconds (15 min soft expiry)
define('PM_REDIS_DEBUG', false);                // true: emit X-PM-Cache-* headers
define('PM_REDIS_GZIP', false);                 // true: gzip-compress stored HTML
define('PM_REDIS_KEY_PREFIX', 'fpc');
define('PM_REDIS_ACTIVATE', true);
```

### 2. advanced-cache.php Drop-In

Create a symlink from the theme's `advanced-cache.php` into `wp-content/`:

```bash
cd /var/www/vhosts/example.com/production
ln -s wp-content/themes/travelshop/advanced-cache.php wp-content/advanced-cache.php
```

The file should contain:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

if (!defined('ABSPATH')) {
    die();
}
require_once __DIR__ . '/vendor/pressmind/sdk/src/Pressmind/WordPress/PageCache.php';
if (php_sapi_name() !== 'cli' && defined('PM_REDIS_ACTIVATE') === true && PM_REDIS_ACTIVATE === true) {
    \Pressmind\WordPress\PageCache::cache_init();
}
```

### 3. Enable WP_CACHE

In `wp-config.php`:

```php
define('WP_CACHE', true);
```

---

## Configuration Reference

### Required Constants

| Constant | Type | Description |
|---|---|---|
| `PM_REDIS_HOST` | `string` | Redis server hostname or IP |
| `PM_REDIS_PORT` | `string\|int` | Redis server port |
| `PM_REDIS_TTL` | `int\|array` | Hard TTL in seconds. After this, the Redis key expires entirely. Can be an array for route-based TTL (see below). |
| `PM_REDIS_BACKGROUND_KEY_REFRESH` | `int` | Soft TTL in seconds. After this, stale content is served while a background request refreshes the cache. Must be less than `PM_REDIS_TTL`. Set to `0` to disable caching entirely. |
| `PM_REDIS_DEBUG` | `bool` | Emit `X-PM-Cache-*` response headers for debugging |
| `PM_REDIS_GZIP` | `bool` | Compress cached HTML with gzip. Saves Redis memory but adds ~15ms TTFB for decompression on large pages. |
| `PM_REDIS_KEY_PREFIX` | `string` | Prefix for all Redis keys (e.g. `fpc`). Allows multiple sites to share one Redis instance. |
| `PM_REDIS_ACTIVATE` | `bool` | Master switch. Set to `false` to disable the cache without removing the drop-in. |

### Optional Constants

| Constant | Type | Default | Description |
|---|---|---|---|
| `PM_REDIS_BLACKLIST_URLS` | `array` | `[]` | Array of URI path substrings that should never be cached. Example: `['/warenkorb', '/checkout', '/mein-konto']` |

### Route-Based TTL

Instead of a single TTL, pass an associative array ordered by specificity (longest path first):

```php
define('PM_REDIS_TTL', [
    '/suche' => 90,      // search pages: 90 seconds
    '/reise' => 86400,   // detail pages: 24 hours (invalidated by import)
    '/' => 3600,         // everything else: 1 hour (last entry = default)
]);
```

The first matching route wins (`strpos` match against the current URL).

---

## How It Works

### Cache Key Structure

Each cached page is stored under a Redis key with this structure:

```
{PM_REDIS_KEY_PREFIX}:{host}:{path}:{segments}:{md5_hash}
```

**Example:** For `https://www.example.com/reise/malta-trip/?pax=2`:

```
fpc:www.example.com:reise:malta-trip:a1b2c3d4e5f6...
```

- The **path part** (before the md5) is derived from the URL with `/` replaced by `:`
- The **md5 hash** is computed from the full cleaned URL, host, HTTPS state, HTTP method, and cleaned GET parameters
- Tracking parameters (see below) are stripped before hashing, so `?fbclid=abc` produces the same key as the clean URL

### Request Flow

1. `cache_init()` connects to Redis and computes the request hash
2. If a cache entry exists **and** the request is not a `cache-refresh` request:
   - If the entry is younger than `PM_REDIS_BACKGROUND_KEY_REFRESH`: serve immediately (**hit**)
   - If older: serve the stale content, fire a background curl with `?cache-refresh=1` to refresh
3. If no cache entry or `cache-refresh=1`: let WordPress render, capture output via `ob_start()`, store in Redis

### Background Key Refresh

The two-tier TTL system ensures visitors never wait for a cold cache:

```
0s ──────── BACKGROUND_KEY_REFRESH ──────── TTL
            (soft expiry)                    (hard expiry)

  Fresh      │  Serve stale + background    │  Key gone,
  cache hit  │  refresh (visitor sees fast)  │  full cache miss
```

When `?cache-refresh=1` is set, the cache is bypassed and the page is rendered fresh. The response header shows `X-PM-Cache-Status: refresh`.

### Tracking Parameter Stripping

The following GET parameters are automatically stripped before computing the cache key, so they don't create duplicate cache entries:

`utm_*`, `fbclid`, `gclid`, `gclsrc`, `msclkid`, `_ga`, `_gl`, `dclid`, `gbraid`, `wbraid`, `gad_source`, `mc_cid`, `mc_eid`, `matomo_*`, `mtm_*`, `piwik_*`, `pk_*`, `hsa_*`, `ef_id`, `s_kwcid`, `dm_i`, `epik`, `awc`, `belboon`, `campid`, `iclid`, `gdpr_consent`, and more (~80 parameters total).

---

## Cache Bypass Conditions

The cache is **not** used (page is rendered fresh) when any of these conditions is true:

| Condition | Description |
|---|---|
| `PM_REDIS_BACKGROUND_KEY_REFRESH < 1` | Caching effectively disabled |
| `PM_REDIS_TTL < 1` (for the matching route) | TTL is zero for this page |
| `$_SERVER['REQUEST_METHOD'] === 'POST'` | POST requests are never cached |
| `DOING_AJAX` | WordPress AJAX requests |
| `XMLRPC_REQUEST` | XML-RPC requests |
| `REST_REQUEST` | WordPress REST API requests |
| `robots.txt` in URI | Robot exclusion file |
| `wp-admin` in URI | Admin pages |
| `DONOTCACHE` or `DONOTCACHEPAGE` | Constants set by plugins (e.g. Beaver Builder editor) |
| `?no-cache` or `?no_cache` | Manual bypass via GET parameter |
| `?preview` | WordPress preview mode |
| `wordpress_logged_in_*` cookie | Logged-in users |
| `_ib3_sid` cookie | Active IB3 booking session |
| `PM_REDIS_BLACKLIST_URLS` match | URL contains a blacklisted substring |

---

## Cache Invalidation

### After SDK Import (CLI)

The Travelshop theme's `cli/import.php` and `cli/run.php` register a post-import callback:

```php
$command->setOnAfterImportCallback(function (array $ids, ?\Pressmind\Import $importer = null): void {
    // 1. Flush all WordPress transients (MySQL + Redis Object Cache)
    $result = Tools::deleteTransients();

    // 2. Invalidate FPC for changed media objects
    if (defined('PM_REDIS_ACTIVATE') && PM_REDIS_ACTIVATE) {
        if ($importer !== null && method_exists($importer, 'getChangedIds')) {
            // Delta-based: only invalidate what actually changed
            \Pressmind\WordPress\PageCache::invalidate_changed($importer);
        } else {
            // Fallback: invalidate all imported IDs
            \Pressmind\WordPress\PageCache::del_by_id_media_object($ids);
            \Pressmind\WordPress\PageCache::prime_by_id_media_object($ids);
        }
    }
});
```

**Flow:** Transients flush → FPC keys deleted → Pages re-primed via background curl.

The deletion pattern `fpc:{host}:{path}:*` matches **all** cached variants of a URL (different GET parameter combinations).

### After SDK Import (HTTP Webhook)

`pm-import.php` follows the same pattern for single-object imports triggered by the pressmind backend.

### After WordPress Post Save

`functions/after_wp_save.php` hooks into `save_post` to invalidate the FPC when a WordPress page is saved:

```php
add_action('save_post', function ($post_id) {
    \Pressmind\WordPress\PageCache::init();
    $pattern = \Pressmind\WordPress\PageCache::get_key_path_from_url($url) . '*';
    \Pressmind\WordPress\PageCache::del_by_pattern($pattern, $level);
});
```

### Manual Invalidation

```php
// Delete all FPC keys for a specific media object (all URL variants)
\Pressmind\WordPress\PageCache::init();
\Pressmind\WordPress\PageCache::del_by_id_media_object([12345]);

// Delete by URL pattern
\Pressmind\WordPress\PageCache::init();
\Pressmind\WordPress\PageCache::del_by_pattern('fpc:www.example.com:reise:*');

// Flush ALL FPC keys for a domain
\Pressmind\WordPress\PageCache::init();
\Pressmind\WordPress\PageCache::del_by_pattern('fpc:www.example.com:*');
```

---

## Cache Priming

### Single-URL Prime

Used for inline background refresh (one page at a time):

```php
\Pressmind\WordPress\PageCache::prime('https://www.example.com/reise/malta-trip/');
```

Fires a background `nohup curl` that runs asynchronously. The URL is escaped via `escapeshellarg()`.

### Batch Prime (curl_multi)

Used after imports for efficient parallel priming. Uses a sliding-window concurrency model with `curl_multi` (default: 10 parallel requests):

```php
\Pressmind\WordPress\PageCache::prime_by_id_media_object([12345, 12346, 12347]);
```

URLs are resolved via a single SQL query against `pmt2core_routes` — no MediaObject instantiation needed.

### Delta-Based Invalidation

`invalidate_changed()` uses the importer's change tracking to only invalidate media objects that actually changed during import. It also resolves affected primary objects transitively via ObjectLink chains:

```php
// Automatically called from the import callback when available
$result = \Pressmind\WordPress\PageCache::invalidate_changed($importer);
// $result = ['deleted' => 5, 'primed' => 3]
```

---

## Transient Interaction

WordPress template transients (`load_template_transient()`) and the FPC are independent caching layers. To prevent stale transient data from being baked into freshly cached FPC pages after an import, **both layers must be flushed together**.

`Tools::deleteTransients()` handles this for both MySQL and Redis Object Cache backends:

```php
$result = \Pressmind\CLI\WordPress\Tools::deleteTransients();
// Clears MySQL transients AND flushes Redis/Object Cache transients
```

This is automatically called in the import callback before FPC invalidation.

---

## Debug Headers

When `PM_REDIS_DEBUG = true`, the following response headers are emitted:

| Header | Description |
|---|---|
| `X-PM-Cache-Status` | `hit`, `miss`, `refresh`, `do not cache`, `no server` |
| `X-PM-Cache-Key` | The full Redis key for this request |
| `X-PM-Cache-Time` | Unix timestamp when the entry was cached |
| `X-PM-Cache-Expires` | Seconds until background refresh triggers |
| `X-PM-Cache-Expired` | `true` if background refresh was triggered |
| `X-PM-Cache-Gzip` | `true` if the cached entry was gzip-compressed |

Additionally, an HTML comment is appended before `</html>`:

```html
<!-- pm-cache: request_hash: fpc:www.example.com:reise:malta:a1b2c3... -->
```

---

## Redis CLI Cheat Sheet

```bash
# List all FPC keys
redis-cli keys "fpc:*"

# Count FPC keys for a domain
redis-cli keys "fpc:www.example.com:*" | wc -l

# Inspect a specific key
redis-cli get "fpc:www.example.com:reise:malta:a1b2c3..."

# Delete all FPC keys for a domain
redis-cli keys "fpc:www.example.com:*" | xargs redis-cli del

# Flush all FPC keys
redis-cli keys "fpc:*" | xargs redis-cli del

# Monitor cache hits in real time
redis-cli monitor | grep "fpc:"
```

---

## Backward Compatibility

The SDK registers a `class_alias`:

```php
if (!class_exists('RedisPageCache', false)) {
    class_alias(PageCache::class, 'RedisPageCache');
}
```

This ensures existing theme code referencing `\RedisPageCache::` continues to work. New code should use `\Pressmind\WordPress\PageCache::`.

---

## Production Checklist

- [ ] `PM_REDIS_DEBUG` set to `false`
- [ ] `PM_REDIS_GZIP` set to `false` (unless Redis memory is critical)
- [ ] `PM_REDIS_TTL` set appropriately (e.g. `3600` or route-based)
- [ ] `PM_REDIS_BACKGROUND_KEY_REFRESH` < `PM_REDIS_TTL` (e.g. `900`)
- [ ] `PM_REDIS_BLACKLIST_URLS` configured for checkout/cart pages
- [ ] `WP_CACHE` set to `true` in `wp-config.php`
- [ ] `advanced-cache.php` symlinked to `wp-content/advanced-cache.php`
- [ ] Import callback includes transient flush (`Tools::deleteTransients()`)
- [ ] Import callback includes FPC invalidation (`PageCache::del_by_id_media_object()`)
- [ ] Crontab has fullimport scheduled (e.g. `0 4 * * 1-5`)
- [ ] Verify with `curl -I` that `X-PM-Cache-Status: hit` appears on second request
