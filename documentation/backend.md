# Management Backend

The SDK includes a web-based **Management Backend** (`Pressmind\Backend`) that provides a Bootstrap 5 UI for configuration, CLI commands, logs, data browsing, MongoDB search, documentation, import control, and validation. It can be used standalone (with config-based auth) or integrated into WordPress (Travelshop theme) with WordPress authentication.

---

## Overview

| Area | Description |
|------|-------------|
| **Dashboard** | System info, PHP/SDK version, DB/Redis/MongoDB status, last errors, media object stats, image formats |
| **Config** | Section-based config editor, raw JSON view, save (if config adapter supports write) |
| **Commands** | List of all CLI commands with metadata; execute with live SSE output |
| **Logs** | Browse `pmt2core_logs` with type/category filters and pagination |
| **Data** | Table browser, media object detail (stamm, cheapest prices, booking packages, validation tab), insurances |
| **Search** | MongoDB collections list, index list per collection, query by `id_media_object`, document detail |
| **Docs** | SDK documentation (Markdown from `documentation/`), TOC, search in docs, ObjectType (Touristic) list |
| **Import** | Queue status, lock status, quick actions (fullimport, mediaobject, touristic) with live output |
| **Validation** | Touristic orphans, insurance checks |

Routing is query-based: `?page=<page>&action=<action>`, e.g. `?page=commands&action=execute`, `?page=data&action=media-object&id=123`.

---

## Enabling the Backend

In your config (e.g. `config.json` or `pm-config.php`), the backend is controlled by the **`backend`** section:

```json
"backend": {
  "enabled": true,
  "cli_runner": "APPLICATION_PATH/cli/run.php",
  "auth": {
    "provider": "password",
    "config": {
      "password": "change-me-in-production"
    }
  }
}
```

- **`enabled`** (boolean) – If `false`, the backend responds with 404 "Backend disabled."
- **`cli_runner`** – Path to the CLI entry script used when executing commands via the backend. Use a **command router** (e.g. `cli/run.php`) so all CommandRegistry commands work; see [Command router](#command-router) and [Why cli_runner?](#why-cli_runner).
- **`auth`** – Authentication provider and its config (see [Auth providers](#auth-providers)).

### Why cli_runner?

All command **logic** lives in the SDK (`Pressmind\CLI\ImportCommand`, `RebuildCacheCommand`, `IndexMongoCommand`, etc.). The Backend still needs a **runner script** for two reasons:

1. **Subprocess execution** – Commands are run in a separate PHP process (`proc_open`), so long-running imports do not block the web request and stdout/stderr can be streamed via SSE. That process must be started by some executable (a PHP script).
2. **Application bootstrap** – That script must load the **application** environment: config path, autoload, and in WordPress setups also WordPress and any app-specific hooks (e.g. Redis cache callbacks after import). The SDK does not know the project root or config location, so this bootstrap remains in the application or theme.

So `cli_runner` points to a bootstrap script. To support **all** Backend commands (import, rebuild-cache, index-mongo, etc.) from one config value, use the **command router** described below.

### Command router

The SDK provides `Pressmind\Backend\CLIRouter`, which dispatches `argv` to the correct CLI command class (ImportCommand, RebuildCacheCommand, IndexMongoCommand, etc.). Your project only needs one runner script that bootstraps the app and calls `CLIRouter::run($argv)`.

**Example (WordPress/Travelshop):** `wp-travelshop-theme/cli/run.php` – bootstraps with `Tools::boot()`, optionally registers `CLIRouter::setImportCallback()` for Redis cache after import, then `exit(CLIRouter::run($argv))`.

Set in config:

```json
"backend": {
  "cli_runner": "APPLICATION_PATH/cli/run.php"
}
```

For **wp-travelshop-theme**, `APPLICATION_PATH` is the theme root, so the runner is at `APPLICATION_PATH/cli/run.php`. If your app layout uses a `cli` folder next to the application root, use `APPLICATION_PATH/../cli/run.php` instead.

Then all commands from the Backend “Commands” page (fullimport, import mediaobject, rebuild-cache, index-mongo all, etc.) run through this single script.

---

## Entry Points

### 1. Standalone (without WordPress)

Create a PHP entry script in your document root (or any path served by the web server) that bootstraps the SDK and runs the backend:

```php
<?php
// e.g. httpdocs/backend.php
require_once __DIR__ . '/../bootstrap.php';  // or your SDK bootstrap

$app = new \Pressmind\Backend\Application();
$app->handle();
```

The application will read the auth provider from config (`backend.auth`). No WordPress is required.

**URL:** `https://your-domain.com/backend.php` (or the path where you placed the script).

### 2. WordPress (Travelshop theme)

When the backend is used inside the **wp-travelshop-theme**, WordPress is loaded via the SDK’s `Tools::boot()` so that auth and capabilities work correctly:

**File:** `wp-travelshop-theme/tools/backend.php`

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

use Pressmind\CLI\WordPress\Tools;

Tools::boot(false, __DIR__);
$auth = new \Pressmind\Backend\Auth\WordPressProvider('edit_pages');
$app = new \Pressmind\Backend\Application($auth);
$app->handle();
```

- `Tools::boot(false, __DIR__)` loads WordPress (searches for `wp-config.php` starting from the `tools/` directory). This avoids hardcoded paths to `wp-load.php`.
- `WordPressProvider('edit_pages')` restricts access to users with the `edit_pages` capability. Unauthenticated users are redirected to the WordPress login.

**URL:** `https://your-domain.com/wp-content/themes/wp-travelshop-theme/tools/backend.php` (path may vary with your setup).

---

## Auth Providers

The backend supports pluggable auth via `Pressmind\Backend\Auth\ProviderInterface`. The provider can be chosen in config or set explicitly when instantiating `Application` (e.g. in the WordPress entry point).

| Provider | Config `backend.auth` | Use case |
|----------|------------------------|----------|
| **password** | `"provider": "password"`, `"config": { "password": "…" }` | Simple single password, no username. Good for dev/small setups. |
| **basic_auth** | `"provider": "basic_auth"`, `"config": { "username": "…", "password": "…" }` | HTTP Basic Auth (browser dialog). |
| **wordpress** | Set by WP entry point, not from config | Uses `current_user_can($capability)` and WP login redirect. |
| **callback** | Custom closures for auth and nonce | For custom integration (e.g. other CMS). |

For standalone, the most common choice is **password** or **basic_auth**. For the Travelshop theme, the entry point forces **WordPressProvider** and ignores `backend.auth` for the actual check.

---

## Process Streaming (SSE)

Long-running actions (command execution, import, re-import from validation) stream output to the browser via **Server-Sent Events (SSE)**.

- **Server:** `Pressmind\Backend\Process\SSEResponse` and `StreamExecutor` send events: `start`, `message` (with `type`/`text`), `complete`, `error`.
- **Client:** The reusable **ProcessStream** JavaScript class (in `View/partials/process-stream.php`) opens an `EventSource`, appends log lines, and updates status/buttons. Use it with the terminal log area or with the **process modal** (`View/partials/process-modal.php`).

Views that use streaming (Commands execute, Import quick actions, Validation orphan re-import) include these partials and call `new ProcessStream(logContainerId, options)` and `stream.start(url)`.

---

## Security Notes

- Set **`backend.enabled`** to `false` in production if you do not use the backend.
- Use a **strong password** or **basic_auth** in production when using the standalone entry point.
- The WordPress entry point should only be reachable by authenticated users with the required capability (e.g. `edit_pages`); protect the theme path with your server/auth setup as needed.
- Do not expose the backend URL publicly without authentication.

---

## Related Documentation

- [CLI Reference](cli-reference.md) – Commands that can be run from the backend “Commands” page.
- [CLI WordPress Tools](cli-wordpress-tools.md) – `Tools::boot()` and WordPress-related CLI usage.
- [Configuration Reference](configuration.md) – Full config structure, including `backend` and other sections.
