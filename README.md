# TFSAppBundle

Symfony bundle that turns a Symfony app into a local-first desktop app (Tauri + FrankenPHP) — companion bundle to [TFSAppHub](https://github.com/ArnaudDelgerie/TFSAppHub), the single host.

## Status

Early stage, no release yet. The bundle covers what an app needs to run under the hub: the kernel dir relocation, `GET /healthz`, `tfsapp:init` and `tfsapp:doctor`, the hub context (`HubContextInterface` and the `tfsapp` Twig global), SQLite pragmas on every connection, the bridge services (secrets, update check, backend close guards) and upload storage. See TFSAppHub's `CONTRACT.md` for the full hub↔app contract this bundle implements.

## Requirements

- PHP `>=8.2`
- Symfony `^7.4|^8.0`

## Usage

Not published yet. Once released: `composer require` this bundle into a Symfony project meant to run under TFSAppHub.

Registering the bundle is all it takes: `GET /healthz` (and `HEAD`) then
answers `200` before routing and security run, per the hub contract —
no configuration, no route to declare. Any other path or method is left
untouched and reaches the app's own routing as usual.

### Kernel dir relocation

Extend `TFSAppKernel` instead of composing `MicroKernelTrait` directly:

```php
use ArnaudDelgerie\TFSAppBundle\Kernel\TFSAppKernel;

class Kernel extends TFSAppKernel
{
}
```

This makes `getCacheDir()`, `getBuildDir()` and `getLogDir()` honour the
hub's `APP_CACHE_DIR`/`APP_BUILD_DIR`/`APP_LOG_DIR` env vars
when they're set, so the installed app's read-only snapshot is never
written to. When the vars are absent — dev mode, or any environment the
hub doesn't control — behavior is unchanged: each dir falls back to
Symfony's own default under the project's `var/`.

### `tfsapp:init`

Run `bin/console tfsapp:init` from the host app to generate
`tfsapp.config.json` at the project root (the Symfony kernel's own
project dir, per the hub contract's project layout). It prompts for
the four required identity fields — `project_name`, `product_name`,
`identifier`, `app_version` — each with a sensible derived default,
then one yes/no `workers` question; pressing Enter accepts the default,
invalid input is re-asked rather than aborting.

The command only ever **creates** files: an existing
`tfsapp.config.json`, `CHANGELOG.md` or `TFSAPP_README.md` is reported
and left untouched — existing manifests get migration guidance from
TFSAppHub's `CONTRACT.md`, not an automatic rewrite.

The scaffold writes only settings today's hub understands: the
`actions` skeleton declares all five capability groups (`secrets`,
`update`, `picker`, `close_guard`, `open_files`) with every transport
false, and no `file_associations` — an app does not claim desktop MIME
support before its author implements a receiver. `commands` carries
only `pre-install`/`pre-update` when Doctrine Migrations is installed,
and is omitted otherwise; there are no build hooks, no `releases_repo`
and no `tfsapp_build/` directory. The contract's other optional
fields (`app_port`, `icon_path`, `splash_*`, `run`) aren't part of the
prompt flow — add them by hand afterwards.

### Hub context

`HubContextInterface` reads what the hub injects into the app's environment
(CONTRACT.md §3) once, and exposes it: `identifier()`, `version()`,
`isAsyncWorker()`, `workerTransports()`, `isKeyringAvailable()`,
`isBridgeEnabled()` and `isRunningUnderHub()`. What it exposes is a
capability, never the host: use it to warn the person or hide a feature, not
to branch business logic. Outside the hub every value is empty or false.

The same values are available in Twig as the `tfsapp` global
(`tfsapp.version`, `tfsapp.async_worker`, `tfsapp.worker_transports`,
`tfsapp.keyring_available`, `tfsapp.bridge_enabled`,
`tfsapp.running_under_hub`) when Twig is installed.

### Bundle configuration

Both options default to `true`; set one to `false` in
`config/packages/tfs_app.yaml` to opt out:

```yaml
tfs_app:
    twig_globals: true    # register the `tfsapp` Twig global
    sqlite_pragmas: true  # assert the SQLite pragmas below on every connection
```

### SQLite pragmas

`DATABASE_URL` is always a SQLite file the host injects, and Doctrine sets none
of the pragmas that make that workable. Left alone, the file stays in
rollback-journal mode, where any writer blocks every reader: the Messenger
worker's transaction would stall the web process serving the window. With
`sqlite_pragmas` on, a DBAL middleware issues `PRAGMA journal_mode=WAL`,
`PRAGMA synchronous=NORMAL` (only crash-safe under WAL, hence the pair) and
`PRAGMA busy_timeout=5000` on every SQLite connection, and only on SQLite — any
other platform is untouched. It runs per connection rather than once because
an import or rescue can restore the database without its `-wal` file, so it can
come back in rollback-journal mode.

`busy_timeout` does not cover `SQLITE_BUSY_SNAPSHOT`; that case is already
retried upstream by Messenger's Doctrine transport, so nothing here retries it.
Turn the option off if your project tunes its own connection.

### `tfsapp:doctor`

`bin/console tfsapp:doctor` prints what the app resolved at runtime: the hub
context, whether secrets and update checks are reachable through the bridge,
the effective `DATABASE_URL` and — for a SQLite file — whether it exists, holds
tables, and which `journal_mode` and `busy_timeout` apply, plus the upload
directory and whether it is writable. It also warns when the hub consumes
Messenger transports the app does not configure, and about off-origin assets in `templates/` (the
FrankenPHP hot-reload block Flex scaffolds is the usual one; the hub's CSP
allows only `'self'`).

### `open_files`: files, directories, and desktop advertisement

Two independent settings govern directory delivery; enable or disable
each on its own:

```json
{
  "actions": { "open_files": { "ipc": true, "directories": true } },
  "file_associations": { "mime_types": ["text/markdown", "inode/directory"] }
}
```

- `ipc: true` is the receiver: the webview receives local paths through
  `tfsapp-hub open <id> -- <path>...` or the desktop's "Open with" menu.
- `directories: true` additionally lets the hub deliver directories
  through every launch path, the CLI included. Leaving it off keeps the
  receiver file-only.
- `inode/directory` in `file_associations.mime_types` is the separate,
  advertising half: it is what makes the file manager offer the app for
  a directory. Declaring it without `ipc: true` and `directories: true`
  is invalid, so disabling directory delivery means removing that MIME
  value too.

MIME declarations never filter CLI paths and grant no filesystem
access: a delivered path names something, and reading it stays the app
backend's business on every path alike. The receiving webview accepts
each request id idempotently — a reload replays unacknowledged
requests — and only then acks it. See TFSAppHub's `CONTRACT.md` §7 for
the wire (`open_files_pending` / `open_files_ack`).

### Upload storage

Durable files an app keeps — avatars, attachments, invoices — go in
`APP_UPLOAD_DIR`, never under `public/`. The work is split three ways:

- **The hub** gives the directory, guarantees it survives updates and
  rollbacks, and carries it in `export`/`import` alongside the database.
- **This bundle** gives `UploadStorageInterface`: every key is confined to that
  directory (absolute keys, `..`, empty segments, null bytes and symlinks
  pointing outside are all refused with `PathOutsideStorageException`), and the
  responses are hardened — `download()` sends `Content-Disposition: attachment`
  and `nosniff`; `inline()` adds `Content-Security-Policy: default-src 'none'; sandbox`.
- **The app** writes the route and decides who may read what.

```php
#[Route('/invoices/{id}/file')]
public function file(Invoice $invoice, UploadStorageInterface $uploads): Response
{
    $this->denyAccessUnlessGranted('VIEW', $invoice);

    return $uploads->download($invoice->getFileKey(), $invoice->getOriginalName());
}
```

Writing is `$uploads->store('invoices/'.$invoice->getId().'.pdf', $uploadedFile)`;
the key, its uniqueness and any validation are the app's. A missing key throws
`StorageException` rather than answering 404 — check `has()` first when a
missing file is a normal case for the route.

No route ships here, on purpose: a download endpoint is entirely authorization
policy. See the hub's `.project/decision/006-durable-files-live-in-the-data-directory.md`.

Outside the hub (`symfony server:start`, PHPUnit, CI), the root falls back to
`var/uploads` under the project; `tfsapp:doctor` prints which one is in use and
whether it is writable.

Inside the hub's window, a `download()` response is saved straight into
the OS download directory — the name de-duplicated if taken — with no
Save-As prompt. That is a documented guarantee of the hub's HTTP
contract (`contract/4-the-http-contract.md`), not this bundle's
behaviour. An app that wants the person to choose the destination pairs
`save_path` — a webview IPC call, see TFSAppHub's `CONTRACT.md` §7 —
with a route of its own that writes the file at the returned path.
`UploadStorage` cannot write there: it refuses absolute keys by
design, so the app writes that path itself. `pick_path`'s filters are
webview IPC too; there is no PHP API for either here.

### Backend close guards

`BackendCloseGuardInterface` marks background work — a Messenger
handler, a long HTTP request — that a person should be warned about
before the last window closes the shared backend. Declare
`"close_guard": {"bridge": true}` in `tfsapp.config.json`'s `actions`
to start the transport, and register **before the work starts**, not
after: a guard registered late cannot warn about a close that already
happened.

```php
use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuardInterface;

final class ExportHandler
{
    public function __construct(private readonly BackendCloseGuardInterface $guards)
    {
    }

    public function __invoke(ExportJob $job): void
    {
        $id = 'export:' . $job->getId(); // distinct ids for simultaneous jobs

        $guarded = $this->guards->register($id);

        if (!$guarded) {
            // No guard installed: no bridge at all, or the close_guard
            // group's routes are gated — never a claim of protection.
            // Run unguarded, or refuse the work.
        }

        try {
            // the vulnerable work
        } finally {
            if ($guarded) {
                $this->guards->remove($id); // the owner removes its own guard
            }
        }
    }
}
```

Only a `true` return means the hub acknowledged the guard. `false` is
the unavailable result — no `TFS_BRIDGE_URL` (nothing declared a
bridge), or a 404-gated route while another group's bridge runs — and
it never implies protection. Every other refusal stays distinguishable
as a typed exception: an invalid id (`CloseGuardInvalidIdException`,
the hub's own non-empty/128-byte-UTF-8 rule), a full namespace
(`CloseGuardTooManyException`, 16 guards per app instance), shutdown
committed (`CloseGuardClosingException`), plus the shared bridge
401/413/invalid-body errors.

Registration is idempotent per id, and removing an absent id is
acknowledged harmlessly — but one job's removal never touches another
job's guard, which is why simultaneous jobs each own their own id.
Guards have no expiry and nothing is persisted: a task that crashes
leaves its warning standing until its owner removes it or the process
exits, and the next launch starts clean. Close guards cover normal
window closure only — mandatory shutdown never waits for a
confirmation answer.

A webview's own unsaved-document guards are a separate frontend
contract (`close_guard.ipc`, per-window and per-document, through
`close_guard_context`/`register`/`remove` IPC): see TFSAppHub's
`CONTRACT.md` §7 for that namespace. This bundle deliberately ships no
PHP abstraction for it.

## Development

Not published to Packagist yet — for now, require it via a VCS repository:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/ArnaudDelgerie/TFSAppBundle"}
    ]
}
```

```
composer require arnauddelgerie/tfs-app-bundle:dev-main
```

### Running tests

```
composer install
vendor/bin/phpunit
```
