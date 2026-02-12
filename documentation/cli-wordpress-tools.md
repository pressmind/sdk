# CLI WordPress Tools

[← CLI Reference](cli-reference.md) | [Documentation Overview](documentation.md)

---

## Table of Contents

- [Purpose and CMS Independence](#purpose-and-cms-independence)
- [When to Use WordPress Tools](#when-to-use-wordpress-tools)
- [Class: `Pressmind\CLI\WordPress\Tools`](#class-pressmindcliwordpresstools)
  - [findBasePath](#findbasepath)
  - [boot](#boot)
  - [getSiteUrl](#getsiteurl)
  - [isBooted](#isbooted)
  - [deleteTransients](#deletetransients)
  - [sendTestEmail](#sendtestemail)
- [WordPress CLI Commands](#wordpress-cli-commands)
  - [Check Email](#check-email)
  - [Migrate Site](#migrate-site)
  - [Regenerate Images](#regenerate-images)
  - [Setup Beaver Builder](#setup-beaver-builder)
- [Scripts Using WordPress Tools](#scripts-using-wordpress-tools)
  - [Delete Transients](#delete-transients)
  - [Cache Primer (wrapper)](#cache-primer-wrapper)
  - [Import (wrapper)](#import-wrapper)

---

## Purpose and CMS Independence

The **WordPress Tools** and the **WordPress CLI commands** in the SDK provide helpers and commands that **require a WordPress environment** (e.g. `site_url()`, `wp_mail()`, `$wpdb`, `update_option()`). They are intended for use in **WordPress-based projects** such as the Travelshop theme.

**Important: The SDK remains independent of WordPress.**

- The SDK **does not** load or depend on WordPress by default. There is no Composer dependency on WordPress, and no global WordPress state is touched unless you explicitly call code that does so.
- The classes under `Pressmind\CLI\WordPress\*` are **optional helpers**. They only load WordPress (e.g. `wp-load.php`, `wp-admin/includes/admin.php`) when you call them (e.g. `Tools::boot()` or a WordPress command’s `run()`). If your project is not WordPress, you simply do not use these classes or the theme wrappers that depend on them.
- All **business logic** of the import, indexing, and search pipeline lives in CMS-agnostic SDK code. The WordPress namespace only provides:
  - **Bootstrapping** (finding WP root, loading `wp-load.php`).
  - **Convenience helpers** that wrap WordPress APIs (site URL, transients, test email).
  - **CLI commands** that call those helpers and/or WordPress functions (e.g. migrate site, regenerate images, Beaver Builder setup).

So: **WordPress Tools = helpers for WordPress contexts only.** They do not change the fact that the core SDK is CMS-independent.

---

## When to Use WordPress Tools

Use `Pressmind\CLI\WordPress\Tools` when:

- You run a **CLI script inside a WordPress installation** (e.g. Travelshop theme `cli/*.php`) and need to:
  - Load WordPress (e.g. for import, post-import hooks, or options).
  - Get the site URL (e.g. for cache primer base URL).
  - Delete transients or send a test email via WordPress.
- You want a **single, central place** to find the WordPress root and boot WordPress in CLI, instead of duplicating `find_wordpress_base_path()` and `wp-load.php` in every script.

Do **not** use WordPress Tools in:

- Pure SDK or non-WordPress projects (no WordPress installation).
- Code paths that must stay free of any WordPress dependency (e.g. core import pipeline, search indexers). Those use only `Pressmind\*` and do not call `WordPress\Tools`.

---

## Class: `Pressmind\CLI\WordPress\Tools`

Namespace: `Pressmind\CLI\WordPress`  
Class: `Tools`  
File: `src/Pressmind/CLI/WordPress/Tools.php`

Static helper class. All methods are `public static`. WordPress is loaded on demand when you call `boot()` (or when another method calls it internally).

---

### findBasePath

```php
public static function findBasePath(?string $startDir = null): ?string
```

**Description:** Finds the WordPress root directory by traversing upward from a given directory until a file `wp-config.php` is found.

| Parameter    | Type     | Default   | Description |
|-------------|----------|-----------|-------------|
| `$startDir` | `?string` | `null`    | Directory to start from. If `null`, uses `getcwd()`. |

**Returns:** Absolute path to the WordPress root, or `null` if not found.

**Example:**

```php
$wpRoot = \Pressmind\CLI\WordPress\Tools::findBasePath(__DIR__);
if ($wpRoot === null) {
    throw new \RuntimeException('WordPress not found.');
}
```

---

### boot

```php
public static function boot(bool $loadAdmin = false): void
```

**Description:** Loads WordPress in headless mode (no theme rendering). Defines `WP_USE_THEMES` as `false` if not already set, then includes `wp-load.php`. Optionally loads `wp-admin/includes/admin.php` (required for `$wpdb`, `update_option()`, and similar).

| Parameter     | Type   | Default | Description |
|---------------|--------|---------|-------------|
| `$loadAdmin`  | `bool` | `false` | If `true`, also require `wp-admin/includes/admin.php`. |

**Throws:** `RuntimeException` if the WordPress base path cannot be found (no `wp-config.php` in parent directories).

**Idempotent:** Calling `boot()` again after a successful call has no effect.

**Example:**

```php
\Pressmind\CLI\WordPress\Tools::boot(true);
// Now $wpdb, get_option(), update_option(), etc. are available.
```

---

### getSiteUrl

```php
public static function getSiteUrl(): string
```

**Description:** Returns the WordPress site URL via `site_url()`.

**Requires:** `boot()` must have been called at least once before.

**Throws:** `RuntimeException` if WordPress is not booted.

**Returns:** `string` (e.g. `https://example.com`).

**Example:**

```php
Tools::boot();
$baseUrl = Tools::getSiteUrl();
```

---

### isBooted

```php
public static function isBooted(): bool
```

**Description:** Returns whether WordPress has already been booted in this process.

**Returns:** `true` if `boot()` has been called successfully, otherwise `false`.

---

### deleteTransients

```php
public static function deleteTransients(): array
```

**Description:** Deletes all WordPress transients and site transients from the options table. Calls `boot(true)` if not already booted.

**Returns:** Array with keys:

| Key               | Type   | Description |
|-------------------|--------|-------------|
| `transients`      | `int`  | Number of rows deleted for `_transient_%`. |
| `site_transients` | `int`  | Number of rows deleted for `_site_transient_%`. |

**Example:**

```php
$result = Tools::deleteTransients();
echo 'Deleted ' . $result['transients'] . ' transients and ' . $result['site_transients'] . ' site transients.';
```

---

### sendTestEmail

```php
public static function sendTestEmail(string $to, bool $smtpDebug = false): bool
```

**Description:** Sends a test email via `wp_mail()` to verify mail (e.g. SMTP) configuration. Subject and body include `site_url()`. Optionally enables PHPMailer SMTP debug level 3.

| Parameter   | Type   | Default | Description |
|-------------|--------|---------|-------------|
| `$to`       | `string` | —     | Recipient email address. Must be valid (validated with `filter_var(..., FILTER_VALIDATE_EMAIL)`). |
| `$smtpDebug` | `bool` | `false` | If `true`, enables PHPMailer SMTP debug (level 3). |

**Throws:** `RuntimeException` if `$to` is not a valid email address.

**Returns:** `true` if `wp_mail()` returned success, otherwise `false`.

**Example:**

```php
Tools::boot();
$ok = Tools::sendTestEmail('admin@example.com', true);
```

---

## WordPress CLI Commands

These commands extend `Pressmind\CLI\AbstractCommand` and live in the namespace `Pressmind\CLI\WordPress`. They are invoked from the Travelshop theme CLI wrappers (e.g. `cli/check_email.php`). They call `Tools::boot()` (or equivalent) internally where WordPress is needed.

---

### Check Email

**Class:** `Pressmind\CLI\WordPress\CheckEmailCommand`  
**Wrapper:** `cli/check_email.php`

Sends a test email via WordPress to verify mail configuration.

| Argument (positional) | Description |
|----------------------|-------------|
| `[email]`            | Recipient email. If omitted, the command may prompt (e.g. `Send email to <name@email.de>:`). |

| Option        | Description |
|---------------|-------------|
| `--smtp-debug` | Enables SMTP debug output. |

**Examples:**

```bash
php cli/check_email.php
php cli/check_email.php admin@example.com
php cli/check_email.php admin@example.com --smtp-debug
```

---

### Migrate Site

**Class:** `Pressmind\CLI\WordPress\MigrateSiteCommand`  
**Wrapper:** `cli/migrate-site.php`

Migrates a WordPress site from one URL to another (options, postmeta, posts, multisite tables, rewrite rules).

| Option        | Description |
|---------------|-------------|
| `--new-site=<url>` | **Required.** The new site URL (e.g. `https://new.example.com`). |
| `--old-site=<url>` | Optional. The old site URL. If omitted, read from the current installation (e.g. `get_option('siteurl')`). |
| `--id-blog=<id>`   | Optional. For **multisite**, the blog ID to migrate. |
| `--help`, `-h`     | Print usage. |

**Examples:**

```bash
php cli/migrate-site.php --new-site=https://new.example.com
php cli/migrate-site.php --new-site=https://new.example.com --old-site=http://old.local
php cli/migrate-site.php --new-site=https://new.example.com --old-site=http://old.local --id-blog=1
```

---

### Regenerate Images

**Class:** `Pressmind\CLI\WordPress\RegenerateImagesCommand`  
**Wrapper:** `cli/regenerate-images.php`

Regenerates WordPress attachment image derivatives (thumbnails) for the media library. Supports an optional theme callback (e.g. to set thumbnail sizes via ThemeActivation).

| Option     | Description |
|------------|-------------|
| `--id=<id>` | Regenerate only the attachment with the given ID. |
| `--all`     | Regenerate all image derivatives (after confirmation unless non-interactive). |
| `--help`, `-h` | Print usage. |

**Examples:**

```bash
php cli/regenerate-images.php --all
php cli/regenerate-images.php --id=12345
```

---

### Setup Beaver Builder

**Class:** `Pressmind\CLI\WordPress\SetupBeaverBuilderCommand`  
**Wrapper:** `cli/setup_beaverbuilder.php`

Applies recommended Beaver Builder settings for the Travelshop theme (post types, templates, margins/paddings, user access).

No arguments or options.

**Example:**

```bash
php cli/setup_beaverbuilder.php
```

---

## Scripts Using WordPress Tools

These are typically theme scripts (e.g. in Travelshop `cli/`) that call `Tools` for bootstrap or helpers and optionally delegate to an SDK command.

---

### Delete Transients

**Script:** `cli/delete_transients.php` (theme)

Uses `Tools::deleteTransients()` and prints the number of deleted transients and site transients. No parameters.

---

### Cache Primer (wrapper)

**Script:** `cli/cache_primer.php` (theme)

Boots WordPress with `Tools::boot()`, then runs the SDK **Cache Primer** command and passes `--base-url=<site_url>` so the primer outputs full URLs. The SDK command itself is CMS-agnostic; only the base URL is taken from WordPress.

---

### Import (wrapper)

**Script:** `cli/import.php` (theme)

Boots WordPress with `Tools::boot(true)`, optionally registers a **post-import callback** (e.g. Redis cache invalidation/priming when `PM_REDIS_ACTIVATE` is set), then runs `Pressmind\CLI\ImportCommand`. All import subcommands and parameters are documented in the [CLI Reference – Import](cli-reference.md#import-primary-command). The wrapper does not add new parameters; it only sets up the environment and optional callback.

---

## Summary

| Item | Purpose |
|------|---------|
| **Tools** | Central WordPress bootstrap and helpers (find path, boot, site URL, transients, test email). **No SDK dependency on WordPress**; used only when explicitly called from WP context. |
| **WordPress CLI commands** | Check Email, Migrate Site, Regenerate Images, Setup Beaver Builder – all require WordPress and use Tools where needed. |
| **Theme scripts** | Delete transients, cache primer with site URL, import with optional Redis callback – use Tools for boot and/or URL/transients. |

For the full list of SDK CLI commands (including those that do **not** require WordPress), see the [CLI Reference](cli-reference.md).
