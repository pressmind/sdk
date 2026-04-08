# Pressmind File Storage (full import)

[← Documentation overview](documentation.md) | [Import process](import-process.md) | [CLI reference](cli-reference.md) | [Configuration](configuration.md)

---

## Table of contents

- [Background](#background)
- [Default behaviour vs full File Storage sync](#default-behaviour-vs-full-file-storage-sync)
- [Configuration](#configuration)
- [What gets imported](#what-gets-imported)
- [CLI entry points](#cli-entry-points)
- [Full import integration](#full-import-integration)
- [Post-import: downloads](#post-import-downloads)
- [Orphans and consistency](#orphans-and-consistency)
- [When to enable full sync](#when-to-enable-full-sync)

---

## Background

In **pressmind**, **File Storage** is a dedicated file management area: physical files live in the PIM with folders, metadata, and stable IDs. That is separate from **media objects** and from attachments discovered only through product content.

The SDK stores downloadable files in `pmt2core_attachments` and on disk (or S3) according to [`file_handling`](config-image-file-handling.md). By default, attachment rows are created when the import encounters file references—typically **text fields** and **WYSIWYG** content (e.g. download links inserted in the pressmind editor). Those attachments are tied to media object data and are not required to cover the entire File Storage tree.

---

## Default behaviour vs full File Storage sync

| Mode | What is synced | Typical use |
|------|----------------|-------------|
| **Default** (`file_storage.import_enabled`: `false`) | Only files **referenced** from imported media object content (including WYSIWYG links). | Smaller DB and storage footprint; matches what products actually use. |
| **Full File Storage** (`file_storage.import_enabled`: `true`) | **All** files returned by the File Storage API for the account (full tree walk or flat list, depending on API layout). | Central mirror of the PIM file library on the shop side; useful when you need every document available locally without per–media-object linkage. |

Enabling full sync is a deliberate choice: imports pull **complete** metadata for File Storage, then optionally download binaries in a separate step (see [Post-import: downloads](#post-import-downloads)).

---

## Configuration

Add or merge the top-level `file_storage` block in your `pm-config` / `config.json` (see [`config.default.json`](../config.default.json)):

```json
{
  "file_storage": {
    "import_enabled": false
  }
}
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `import_enabled` | `boolean` | `false` | When `true`, a **`fullimport`** run also executes the File Storage metadata import after media objects (see [Full import integration](#full-import-integration)). Does not affect partial/sync import types. |

The pressmind **REST API** credentials used for normal import must allow `FileStorage` API calls (`getStats`, `getFolders`, `getFiles`, etc.).

---

## What gets imported

Implementation: [`Pressmind\Import\FileStorage`](../src/Pressmind/Import/FileStorage.php).

- **Metadata** is written to `pmt2core_attachments` using the File Storage file `_id` as `Attachment.id` (same identifier space as API).
- Rows imported from File Storage are marked with `synced_from_file_storage = true`.
- `download_successful` is set to `false` until the binary is fetched.
- **Hash-based skip:** If the file already exists and the API `hash` matches the stored hash, the row is left unchanged (unless `--force` is used).
- **`--force`:** Re-applies metadata and marks files for re-download even when the hash matches.

Attachments created from **WYSIWYG / media object** import set `synced_from_file_storage = false` so they remain in the “classic” orphan path (see [Orphans and consistency](#orphans-and-consistency)).

---

## CLI entry points

### SDK (recommended)

| Script | Purpose |
|--------|---------|
| `php bin/file-storage-import` | Runs [`FileStorageImportCommand`](../src/Pressmind/CLI/FileStorageImportCommand.php): File Storage metadata import, then (unless `--no-download`) the attachment download worker. |
| `php bin/import filestorage` | Same File Storage import via the main import CLI; optional `--force`, `--folder=<id>`, `--no-download`. After metadata, spawns download via [`AttachmentDownloaderCommand`](../src/Pressmind/CLI/AttachmentDownloaderCommand.php). |
| `php bin/attachment-downloader` | Downloads **all** attachments with `download_successful = 0` and a non-empty `drive_url` (File Storage + WYSIWYG). |

### Travelshop theme wrappers

Themes ship thin wrappers that bootstrap the app and delegate to the same SDK commands, for example:

- `php cli/file_storage_import.php` → `FileStorageImportCommand`
- `php cli/file_storage_downloader.php` / `php cli/attachment_downloader.php` → `AttachmentDownloaderCommand`

`file_storage_downloader.php` is **functionally identical** to `attachment_downloader.php`; the name signals intent when `file_storage.import_enabled` is used. [`Import::postImport()`](../src/Pressmind/Import.php) prefers `file_storage_downloader.php` if that file exists, otherwise falls back to `attachment_downloader.php`.

### Common options (File Storage import)

| Option | Effect |
|--------|--------|
| `--force` | Ignore hash equality; refresh metadata and queue re-download. |
| `--folder=<id>` | Import only that folder subtree; **does not** run full-tree orphan removal for File Storage rows. |
| `--no-download` | Metadata only; skip spawning the attachment downloader at the end of the standalone command. |

---

## Full import integration

During **`fullimport`**, after media objects are processed, [`Import::importFileStorageIfEnabled()`](../src/Pressmind/Import.php) runs when `file_storage.import_enabled` is `true`:

1. Instantiates `Pressmind\Import\FileStorage` with `force = false`, full tree (`rootFolderId = null`), and **orphan removal enabled** for File Storage–synced rows.
2. Appends File Storage log lines and errors to the main import log.

**Note:** This step imports **metadata only** (same as the standalone importer). Binary downloads are **not** part of this synchronous block; they are handled by [post-import](#post-import-downloads) when running under CLI.

---

## Post-import: downloads

[`Import::postImport()`](../src/Pressmind/Import.php) (CLI only) already starts:

1. `cli/image_processor.php` (derivatives)
2. `cli/file_downloader.php` (Media Object **File** datatype downloads)

If `file_storage.import_enabled` is `true`, it additionally starts **one** of `cli/file_storage_downloader.php` or `cli/attachment_downloader.php`, which both run `AttachmentDownloaderCommand`: fetch binaries for every attachment still marked not downloaded.

Processes are started with `nohup` and **stdout/stderr redirected to `/dev/null`** (same pattern as other post-import workers). Monitor progress via the configured log writer / log files for the `attachment_downloader` channel, not the parent import terminal.

---

## Orphans and consistency

Two mechanisms apply; both aim for a **consistent** local mirror.

### 1. Attachment orphans (no media object link)

[`Import::_removeOrphanAttachments()`](../src/Pressmind/Import.php) removes attachment rows that have **no** row in `pmt2core_attachment_to_media_object`, **excluding** rows with `synced_from_file_storage = 1`.

Full File Storage–synced files are **not** linked to media objects by design; without this exclusion they would be deleted as “orphans” after every full import. WYSIWYG-driven attachments (`synced_from_file_storage = 0`) remain subject to this cleanup when unreferenced.

### 2. Stale File Storage rows (removed in PIM)

On a **full** File Storage import (`rootFolderId === null`), after walking the API, any attachment with `synced_from_file_storage = 1` whose ID was **not** seen in the current import is treated as stale: file on storage is deleted and the DB row removed.

If you import **only** `--folder=<id>`, this global stale pass is **skipped** so partial runs do not delete files outside the imported subtree.

---

## When to enable full sync

Reasons teams enable `file_storage.import_enabled`:

- **Complete local library** aligned with pressmind File Storage (e.g. brochures, conditions, assets not yet linked in every text).
- **Predictable disk usage** planning when all files must exist on the shop infrastructure.
- **Operational simplicity** versus relying on WYSIWYG-only discovery.

Trade-offs: longer full imports, more DB rows, and larger storage; ensure cron/infra can handle the attachment downloader workload after each run.

---

[← Documentation overview](documentation.md)
