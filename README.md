# TFSAppBundle

Symfony bundle that turns a Symfony app into a local-first desktop app (Tauri + FrankenPHP) — companion bundle to [TFSAppWorkstation](https://github.com/ArnaudDelgerie/TFSAppWorkstation).

## Status

Early stage, no release yet. **v0.1.0 scope**: relocate Symfony's cache/build/log directories to the paths the station's launcher provides (`APP_CACHE_DIR`/`APP_BUILD_DIR`/`APP_LOG_DIR`), expose the `GET /healthz` route the launcher polls before opening its window, and provide a `tfsapp:init` console command to generate the project's `tfsapp.config.json`. See TFSAppWorkstation's `CONTRACT.md` for the full station↔project contract this bundle implements.

## Requirements

- PHP `>=8.2`
- Symfony `^7.4|^8.0`

## Usage

Not published yet. Once released: `composer require` this bundle into a Symfony project meant to run under TFSAppWorkstation.

Registering the bundle is all it takes: `GET /healthz` (and `HEAD`) then
answers `200` before routing and security run, per the station contract —
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
station launcher's `APP_CACHE_DIR`/`APP_BUILD_DIR`/`APP_LOG_DIR` env vars
when they're set, so the packaged app's read-only mount is never written to.
When the vars are absent — dev mode, or any environment the launcher doesn't
control — behavior is unchanged: each dir falls back to Symfony's own
default under the project's `var/`.

### `tfsapp:init`

Run `bin/console tfsapp:init` from the host app to generate
`tfsapp.config.json` at the project root (the Symfony kernel's own
project dir, per the station contract's project layout). It prompts for
the four required identity fields — `project_name`, `product_name`,
`identifier`, `app_version` — each with a sensible derived default;
pressing Enter accepts the default, invalid input is re-asked rather
than aborting.

The command only ever **creates** the file: if `tfsapp.config.json`
already exists, it reports the path and exits without prompting or
overwriting anything. The contract's optional fields (`app_port`,
`icon_path`, `commands`) aren't part of this prompt flow — add them by
hand afterwards; their absence keeps the documented defaults.

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

Inside the hub's window today, a `download()` response is saved straight into
the OS download directory (the name de-duplicated if taken), with no Save-As
prompt. That is the hub's current, provisional behaviour, not this bundle's —
see its `contract/4-the-http-contract.md` — and it may change.

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
