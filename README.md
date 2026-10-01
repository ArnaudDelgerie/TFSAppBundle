# TFSAppBundle

Symfony bundle for apps run by [TFSAppHub](https://github.com/ArnaudDelgerie/TFSAppHub), the host that installs and runs Symfony apps as local desktop apps. This README is the bundle's PHP-side reference: what each feature does, its configuration, its commands. The contract between the hub and an app lives in TFSAppHub's [`CONTRACT.md`](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/CONTRACT.md) — this README links to its clauses instead of restating them. To build your first app, start from the hub's README, ["Make your first app"](https://github.com/ArnaudDelgerie/TFSAppHub#make-your-first-app).

## Requirements

- PHP `>=8.2`
- Symfony `^7.4|^8.0`

## Install

```
composer require arnauddelgerie/tfs-app-bundle
```

Symfony Flex adds `ArnaudDelgerie\TFSAppBundle\TFSAppBundle` to `config/bundles.php` for all environments; without Flex, add that line by hand. The bundle registers its services and needs no configuration to work. Then run `bin/console tfsapp:init` (below) to generate the app's `tfsapp.config.json` — from there, the hub installs and opens the app; the rest of what an app must provide is the contract's [§1](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/1-what-an-app-must-provide.md).

## Reference

### `/healthz`

Registering the bundle is all it takes: `GET /healthz` (and `HEAD`) then answers `200` before routing and security run — no configuration, no route to declare. Any other path or method is left untouched and reaches the app's own routing as usual. This serves the hub's [`GET /healthz`](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/4-the-http-contract.md#get-healthz--200) clause.

### Kernel directories

Nothing to change in the app's kernel: Symfony's own `MicroKernelTrait` (^7.4) already makes `getCacheDir()`, `getBuildDir()` and `getLogDir()` honour the hub's `APP_CACHE_DIR`/`APP_BUILD_DIR`/`APP_LOG_DIR`, so the skeleton's `src/Kernel.php` stays as generated. Why the dirs must move at all is the contract's: [§3, `APP_CACHE_DIR` and `APP_BUILD_DIR` may be emptied at any launch](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md#app_cache_dir-and-app_build_dir-may-be-emptied-at-any-launch).

### `tfsapp:init`

Run `bin/console tfsapp:init` from the host app to generate `tfsapp.config.json` at the project root (the Symfony kernel's own project dir, per the contract's project layout). It prompts for the four required identity fields — `project_name`, `product_name`, `identifier`, `app_version` — each with a sensible derived default, then one yes/no `workers` question; pressing Enter accepts the default, invalid input is re-asked rather than aborting. It also creates a starter `CHANGELOG.md` and `TFSAPP_README.md` when absent.

The command only ever **creates** files: an existing `tfsapp.config.json`, `CHANGELOG.md` or `TFSAPP_README.md` is reported and left untouched — existing manifests get migration guidance from TFSAppHub's `CONTRACT.md`, not an automatic rewrite.

The scaffold writes only settings today's hub understands: the `actions` skeleton declares all five capability groups (`secrets`, `update`, `picker`, `close_guard`, `open_files`) with every transport false, and no `file_associations` — an app does not claim desktop MIME support before its author implements a receiver. `commands` carries only `pre-install`/`pre-update` when Doctrine Migrations is installed, and is omitted otherwise. The manifest's other optional fields aren't part of the prompt flow — add them by hand afterwards; their shapes are the contract's [§2](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/2-tfsapp-config-json.md).

### Hub context

`HubContextInterface` reads what the hub injects into the app's environment ([§3](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md)) once, and exposes it: `identifier()`, `version()`, `isAsyncWorker()`, `workerTransports()`, `isKeyringAvailable()`, `isBridgeEnabled()` and `isRunningUnderHub()`. Outside the hub every value is empty or false. How to use what it exposes — a capability is reported to be read, never a host to branch on — is the contract's rule: [capabilities are reported, not assumed](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md#capabilities-are-reported-not-assumed).

The same values are available in Twig as the `tfsapp` global
(`tfsapp.version`, `tfsapp.async_worker`, `tfsapp.worker_transports`,
`tfsapp.keyring_available`, `tfsapp.bridge_enabled`, `tfsapp.running_under_hub`) when Twig is installed.

### Bundle configuration

Both options default to `true`; set one to `false` in
`config/packages/tfs_app.yaml` to opt out:

```yaml
tfs_app:
    twig_globals: true    # register the `tfsapp` Twig global
    sqlite_pragmas: true  # assert the SQLite pragmas below on every connection
```

### SQLite pragmas

With `sqlite_pragmas` on, a DBAL middleware issues `PRAGMA journal_mode=WAL`, `PRAGMA synchronous=NORMAL` (only crash-safe under WAL, hence the pair) and `PRAGMA busy_timeout=5000` on every SQLite connection, and only on SQLite — any other platform is untouched. It runs per connection rather than once because an import or rescue can restore the database without its `-wal` file, so it can come back in rollback-journal mode, where any writer blocks every reader.

Why the database is SQLite at all is the contract's constraint on the app: [§3](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md#the-database-is-sqlite-and-that-is-a-constraint-on-the-app). `busy_timeout` does not cover `SQLITE_BUSY_SNAPSHOT`; that case is already retried upstream by Messenger's Doctrine transport, so nothing here retries it. Turn the option off if your project tunes its own connection.

### `tfsapp:doctor`

`bin/console tfsapp:doctor` prints what the app resolved at runtime: the hub context, whether secrets and update checks are reachable through the bridge, the effective `DATABASE_URL` and — for a SQLite file — whether it exists, holds tables, and which `journal_mode` and `busy_timeout` apply, plus the upload directory and whether it is writable. It also warns when the hub consumes Messenger transports the app does not configure, and about off-origin assets in `templates/` (the hot-reload block a Symfony scaffold appends to `base.html.twig` is the usual one; the hub's CSP allows only `'self'`, per [§4](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/4-the-http-contract.md#nothing-the-page-loads-may-come-from-off-origin)).

### `open_files`

The bundle ships no PHP API for `open_files`: a delivered path names something, and reading it stays the app backend's business. Both halves — the `ipc`/`directories` settings and `file_associations` advertising in the manifest, and the delivery wire in the webview — are the contract's: [§2, declaring file associations](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/2-tfsapp-config-json.md#declaring-file-associations) and [§7, `open_files`](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/7-native-capabilities-actions.md#open_files).

### Upload storage

Durable files an app keeps — avatars, attachments, invoices — go in `APP_UPLOAD_DIR`, never under `public/`. The hub guarantees the directory and its contents ([§3](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md#app_upload_dir-is-never-emptied-at-any-launch-under-any-circumstance), [§5](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/5-the-apps-own-state.md#an-installation-moves-between-machines--the-database-is-what-travels)); this bundle gives `UploadStorageInterface`, and the app writes the route and decides who may read what.

```php
#[Route('/invoices/{id}/file')]
public function file(Invoice $invoice, UploadStorageInterface $uploads): Response
{
    $this->denyAccessUnlessGranted('VIEW', $invoice);

    return $uploads->download($invoice->getFileKey(), $invoice->getOriginalName());
}
```

Writing is `$uploads->store('invoices/'.$invoice->getId().'.pdf', $uploadedFile)`; the key, its uniqueness and any validation are the app's. Every key is confined to the storage directory — absolute keys, `..`, empty segments, null bytes and symlinks pointing outside are all refused with `PathOutsideStorageException`. The responses are hardened — `download()` sends `Content-Disposition: attachment` and `nosniff`; `inline()` adds `Content-Security-Policy: default-src 'none'; sandbox`. A missing key throws `StorageException` rather than answering 404 — check `has()` first when a missing file is a normal case for the route.

No route ships here, on purpose: a download endpoint is entirely authorization policy.

Outside the hub (`symfony server:start`, PHPUnit, CI), the root falls back to `var/uploads` under the project; `tfsapp:doctor` prints which one is in use and whether it is writable.

Inside the hub's window, where a `download()` response lands is a documented guarantee of the hub's HTTP contract, not this bundle's behaviour — see [§4](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/4-the-http-contract.md#the-host-serves-the-apps-document-root-and-nothing-of-its-own). Choosing the destination (`save_path`) and picking files (`pick_path`) are webview IPC calls defined by [§7](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/7-native-capabilities-actions.md#picker); there is no PHP API for either here, and `UploadStorage` cannot write outside its root, so an app that offers them writes those paths itself.

### Backend close guards

`BackendCloseGuardInterface` marks background work — a Messenger handler, a long HTTP request — that a person should be warned about before the last window closes the shared backend. Declare `"close_guard": {"bridge": true}` in `tfsapp.config.json`'s `actions` to start the transport, and register **before the work starts**, not after: a guard registered late cannot warn about a close that already happened.

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

Only a `true` return means the hub acknowledged the guard; `false` is the unavailable result and never implies protection. Every other refusal stays distinguishable as a typed exception: an invalid id (`CloseGuardInvalidIdException`), a full namespace (`CloseGuardTooManyException`), shutdown committed (`CloseGuardClosingException`), plus the shared bridge 401/413/invalid-body errors.

The wire, the guard limits and their expiry, and the per-window unsaved-document guards a webview may hold, are the contract's: [§7, `close_guard`](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/7-native-capabilities-actions.md#close_guard). One rule binds the PHP side: one job's removal never touches another job's guard, which is why simultaneous jobs each own their own id.

## Contributing

```
composer install
vendor/bin/phpunit
```

To test a bundle change under the hub, run the app through the hub's `dev` mode ([§9](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/9-running-a-project-in-dev.md)) with [TFSAppTest](https://github.com/ArnaudDelgerie/TFSAppTest) — its README holds the procedure for working against a local bundle checkout.
