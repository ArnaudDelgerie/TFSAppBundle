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

Add `use TFSAppKernelTrait;` to the host app's `Kernel`:

```php
use ArnaudDelgerie\TFSAppBundle\Kernel\TFSAppKernelTrait;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    use TFSAppKernelTrait;
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
`tfsapp.config.json` at the project root (one level above the `app/`
kernel dir, per the station contract's project layout). It prompts for
the four required identity fields — `project_name`, `product_name`,
`identifier`, `app_version` — each with a sensible derived default;
pressing Enter accepts the default, invalid input is re-asked rather
than aborting.

The command only ever **creates** the file: if `tfsapp.config.json`
already exists, it reports the path and exits without prompting or
overwriting anything. The contract's optional fields (`app_port`,
`icon_path`, `commands`) aren't part of this prompt flow — add them by
hand afterwards; their absence keeps the documented defaults.

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
