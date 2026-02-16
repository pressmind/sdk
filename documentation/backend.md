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

The backend uses a **pluggable authentication** layer: `Pressmind\Backend\Auth\ProviderInterface`. Exactly one provider is active per request. It is chosen either from config (`backend.auth`) when you use `new Application()` with no arguments, or passed explicitly when you use `new Application($auth)` (e.g. in the WordPress entry point, which typically passes `WordPressProvider` and ignores `backend.auth`).

**Config reference:** See `EXAMPLE_auth_providers` in `config.default.json` for ready-to-use JSON snippets for each provider.

The following sections describe each built-in provider in detail: behaviour, config options, request flow, security aspects, and full examples.

---

### 1. password (ConfigPasswordProvider)

**Class:** `Pressmind\Backend\Auth\ConfigPasswordProvider`

**What it does:** Authenticates with a **single shared password** (no username). The login state is stored in a **PHP session**. If the user is not authenticated, the backend shows a **custom login form** (Bootstrap-styled). After a successful POST login, the user is redirected (optionally to a return URL). Logout is done via `?logout=1` and is only supported when this provider is active.

**Typical use:** Standalone backend (no WordPress), dev or small teams where one shared password is enough.

#### Config options

| Key | Required | Default | Description |
|-----|----------|---------|-------------|
| `password` | Yes | — | The secret password. Must be non-empty; otherwise no one can log in. |
| `returnUrlParam` | No | `return_url` | Name of the query parameter used for the URL to redirect to after login (e.g. `?return_url=/backend.php?page=config`). |

#### Request flow

1. **Unauthenticated GET/POST (no valid session):**  
   `Application` calls `isAuthenticated()` → false. The provider returns HTML for the login form via `renderLoginForm()`. The user sees the “Backend Login” page with one password field and a CSRF nonce (`_pm_backend_nonce`).

2. **Login POST:**  
   `Application` calls `handleLoginRequest()`. The provider checks:
   - Rate limit: max 5 failed attempts per 15 minutes (per session). If exceeded, login is rejected and the form shows “Too many failed login attempts…”
   - CSRF: `_pm_backend_nonce` must match the session-stored nonce for action `backend_login`.
   - Password must match the configured `password`.  
   If all pass: session is regenerated (`session_regenerate_id(true)`), session keys are set, and the provider returns `true`. `Application` then redirects to the return URL (or dashboard). If the return URL is not a relative path (e.g. absolute URL), it is ignored for security (open-redirect protection).

3. **Authenticated requests:**  
   `isAuthenticated()` is true (session key present). The router runs and the requested backend page is rendered.

4. **Logout:**  
   If the URL contains `logout=1` and the auth provider is `ConfigPasswordProvider`, `Application` calls `logout()` (clears session auth keys) and redirects to the login URL. There is no logout link in the default nav; you can add one using `$auth->getLogoutUrl()` in your layout.

#### Session and security

- **Session cookie:** Set with secure options: `HttpOnly`, `Secure` when the request is HTTPS (or `X-Forwarded-Proto: https`), `SameSite=Lax`. Session is started on first use (login form, login check, or any action that needs the session).
- **Rate limiting:** After 5 failed login attempts, further attempts are blocked for 15 minutes (same session). The counter resets after the time window or on successful login.
- **CSRF:** Login form and all state-changing actions (e.g. config save, log truncate) use nonces; the provider implements `createNonce()` and `verifyNonce()` via the session.

#### Full config example

```json
"backend": {
  "enabled": true,
  "cli_runner": "APPLICATION_PATH/cli/run.php",
  "auth": {
    "provider": "password",
    "config": {
      "password": "your-secret-password",
      "returnUrlParam": "return_url"
    }
  }
}
```

#### Optional: instantiate in PHP

If you need to override config (e.g. password from env), you can pass the provider into `Application`:

```php
$auth = new \Pressmind\Backend\Auth\ConfigPasswordProvider([
    'password' => getenv('BACKEND_PASSWORD') ?: 'fallback-dev',
    'returnUrlParam' => 'return_url',
]);
$app = new \Pressmind\Backend\Application($auth);
$app->handle();
```

---

### 2. basic_auth (BasicAuthProvider)

**Class:** `Pressmind\Backend\Auth\BasicAuthProvider`

**What it does:** Uses **HTTP Basic Authentication**. The backend does not render a custom login form; when the user is not authenticated, it sends a `401 Unauthorized` response with a `WWW-Authenticate: Basic` header. The **browser** then shows its native username/password dialog. On subsequent requests, the browser sends the credentials in the `Authorization` header; the provider compares them to the username and password from config. There is no “logout” in the sense of clearing a session—the user stays “logged in” until the browser discards the credentials (e.g. close tab or clear site data).

**Typical use:** Simple protection for the backend URL (e.g. behind a reverse proxy), scripted or API-style access with a single fixed user, or environments where you prefer the browser’s built-in dialog over a custom form.

#### Config options

| Key | Required | Default | Description |
|-----|----------|---------|-------------|
| `username` | Yes | — | HTTP Basic username. **If empty (or missing), the backend rejects all requests**—no one can access it. |
| `password` | Yes | — | HTTP Basic password. **If empty (or missing), the backend rejects all requests.** |

The provider’s constructor also accepts a second argument `$realm` (default: `"Pressmind SDK Backend"`). This is only settable when you instantiate the provider in PHP, not via JSON config.

#### Request flow

1. **First request (no credentials):**  
   `isAuthenticated()` checks `$_SERVER['PHP_AUTH_USER']` and `$_SERVER['PHP_AUTH_PW']`. If config username or password is empty, it returns false. If credentials are configured but the request has none or wrong ones, it returns false. `Application` then calls `requireAuth()`, which sends `WWW-Authenticate: Basic realm="…"` and `401`, then exits. The browser shows its login dialog.

2. **Next request (with credentials):**  
   The browser sends `Authorization: Basic <base64(user:pass)>`. PHP populates `PHP_AUTH_USER` and `PHP_AUTH_PW`. If they match the config, `isAuthenticated()` returns true and the backend page is shown.

3. **Logout:**  
   `getLogoutUrl()` returns `null`. To “log out” the user you have to rely on the browser (e.g. close the tab or use a different browser profile).

#### Security notes

- **Never leave username or password empty in config** when using `basic_auth`. Empty credentials are intentionally treated as “not configured”; the provider then returns false for every request so the backend stays locked.
- **Nonces:** The provider still implements `createNonce()` and `verifyNonce()` using the PHP session (for CSRF on forms like config save or command execution). The session is started on first use of nonce create/verify.

#### Full config example

```json
"backend": {
  "enabled": true,
  "cli_runner": "APPLICATION_PATH/cli/run.php",
  "auth": {
    "provider": "basic_auth",
    "config": {
      "username": "admin",
      "password": "your-secret-password"
    }
  }
}
```

#### Optional: custom realm (PHP only)

```php
$auth = new \Pressmind\Backend\Auth\BasicAuthProvider(
    ['username' => 'admin', 'password' => getenv('BACKEND_PASS')],
    'My Company Backend'
);
$app = new \Pressmind\Backend\Application($auth);
$app->handle();
```

---

### 3. wordpress (WordPressProvider)

**Class:** `Pressmind\Backend\Auth\WordPressProvider`

**What it does:** Delegates authentication and authorization to **WordPress**. Access is granted if the current WordPress user has the given **capability** (`current_user_can($capability)`). There is no custom login form: if the user is not logged in (or lacks the capability), the backend either redirects to the WordPress login URL or shows a page with a “Go to WordPress Login” link (so the user can log in there and then return). Nonces for CSRF use WordPress’s `wp_create_nonce` and `wp_verify_nonce`. The current user is determined via `wp_get_current_user()` (display name: login or email).

**Typical use:** Backend integrated into a WordPress site (e.g. Travelshop theme), where you want the same users and roles as in WordPress and a single login (WordPress).

#### Requirements

- **WordPress must be loaded** before the backend runs (e.g. `wp-load.php` or `Tools::boot()`). The provider calls `current_user_can()`, `wp_get_current_user()`, `wp_login_url()`, etc. If these functions are not available, `isAuthenticated()` returns false and login URL/nonce helpers may return null or false.

#### Config options

| Key | Required | Default | Description |
|-----|----------|---------|-------------|
| `capability` | No | `edit_pages` | WordPress capability required to access the backend (e.g. `edit_pages`, `manage_options`). |

#### Request flow

1. **Request with WordPress loaded, user logged in and has capability:**  
   `isAuthenticated()` calls `current_user_can($capability)` → true. Backend page is shown.

2. **User not logged in (or missing capability):**  
   `isAuthenticated()` is false. `Application` uses `getLoginUrl($returnUrl)` (WordPress: `wp_login_url($redirect)`). For `WordPressProvider`, the backend can render a page with a “Go to WordPress Login” link instead of redirecting, so the user can open the WordPress login in the same browser and then return.

3. **After WordPress login:**  
   User returns to the backend URL; the same WordPress session/cookie is sent, so `current_user_can()` is true and the backend is accessible.

#### Using from config

If your entry point already loads WordPress (e.g. a theme “tools” script that includes `wp-load.php` or calls `Tools::boot()`), you can set `backend.auth` to `wordpress` so the factory creates `WordPressProvider`:

```json
"auth": {
  "provider": "wordpress",
  "config": {
    "capability": "edit_pages"
  }
}
```

#### Using from entry point (e.g. Travelshop)

The Travelshop theme typically **does not** use `backend.auth` for the backend. It loads WordPress and then passes an instance of `WordPressProvider` so the capability is explicit in code:

```php
// e.g. wp-travelshop-theme/tools/backend.php
require_once __DIR__ . '/../bootstrap.php';

use Pressmind\CLI\WordPress\Tools;

Tools::boot(false, __DIR__);
$auth = new \Pressmind\Backend\Auth\WordPressProvider('edit_pages');
$app = new \Pressmind\Backend\Application($auth);
$app->handle();
```

Here, `backend.auth` in config is ignored; the active provider is always `WordPressProvider` with capability `edit_pages`. You can change the capability (e.g. `manage_options`) by changing the constructor argument.

---

### 4. callback (CallbackProvider)

**Class:** `Pressmind\Backend\Auth\CallbackProvider`

**What it does:** Lets you implement **fully custom** authentication and login behaviour by passing **PHP callables** for each operation. There is no JSON config for this: you must instantiate the provider in code and pass it to `Application`. Defaults: not authenticated, no login URL/form, no logout URL, login request never succeeds, nonce create returns a random string, nonce verify always returns true (so you should implement proper verify if you need CSRF protection).

**Typical use:** Another CMS (e.g. Drupal, custom app), SSO, API-token-based auth, or any logic that cannot be expressed with the password/basic_auth/wordpress providers.

#### Callback list

All keys are optional. If omitted, the default behaviour in the table is used.

| Callback key | Signature | Default behaviour |
|--------------|------------|-------------------|
| `isAuthenticated` | `(): bool` | Returns `false`. |
| `getCurrentUser` | `(): ?string` | Returns `null`. |
| `getLoginUrl` | `(string $returnUrl): ?string` | Returns `null`. |
| `getLogoutUrl` | `(): ?string` | Returns `null`. |
| `renderLoginForm` | `(): ?string` | Returns `null`. |
| `handleLoginRequest` | `(): bool` | Returns `false`. |
| `createNonce` | `(string $action): ?string` | Returns a random hex string (no persistence). |
| `verifyNonce` | `(string $nonce, string $action): bool` | Returns `true` (no real check). |

- If `renderLoginForm()` returns a non-empty string, the backend shows that HTML when not authenticated (and does not redirect).
- If `getLoginUrl($returnUrl)` returns a non-empty URL and you do not use a custom form, `Application` can redirect to it or show a “Go to login” page (e.g. for WordPress-style flow).
- For CSRF-safe forms (config save, log truncate, commands), implement `createNonce` and `verifyNonce` (e.g. session-stored nonces) and use them in your forms and controllers.

#### Full PHP example

Example: authenticate via a custom session key `myapp_user`. Login is handled elsewhere (e.g. another route); here we only check the session and provide login/logout URLs.

```php
// Ensure session is started for nonces and auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new \Pressmind\Backend\Auth\CallbackProvider([
    'isAuthenticated' => function () {
        return !empty($_SESSION['myapp_user']);
    },
    'getCurrentUser' => function () {
        return $_SESSION['myapp_user']['name'] ?? null;
    },
    'getLoginUrl' => function ($returnUrl) {
        $base = '/myapp/login.php';
        return $returnUrl !== '' ? $base . '?return=' . urlencode($returnUrl) : $base;
    },
    'getLogoutUrl' => function () {
        return '/myapp/logout.php';
    },
    'renderLoginForm' => function () {
        return null; // we use external login page
    },
    'handleLoginRequest' => function () {
        return false; // login is handled by /myapp/login.php
    },
    'createNonce' => function (string $action) {
        $key = 'nonce_' . $action;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(16));
        }
        return $_SESSION[$key];
    },
    'verifyNonce' => function (string $nonce, string $action) {
        $key = 'nonce_' . $action;
        return isset($_SESSION[$key]) && hash_equals($_SESSION[$key], $nonce);
    },
]);

$app = new \Pressmind\Backend\Application($auth);
$app->handle();
```

Minimal example (only auth check; no login form, no CSRF):

```php
$auth = new \Pressmind\Backend\Auth\CallbackProvider([
    'isAuthenticated' => function () {
        return isset($_SERVER['HTTP_X_API_KEY']) && $_SERVER['HTTP_X_API_KEY'] === getenv('BACKEND_API_KEY');
    },
]);
$app = new \Pressmind\Backend\Application($auth);
$app->handle();
```

For production, add proper nonce verification and optionally `getCurrentUser` / `getLoginUrl` so that the backend can show a sensible “Unauthorized” or redirect to login.

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
