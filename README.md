# TFSAppBundle

Symfony bundle that turns a Symfony app into a local-first desktop app (Tauri + FrankenPHP) — companion bundle to [TFSAppWorkstation](https://github.com/ArnaudDelgerie/TFSAppWorkstation).

## Status

Early stage, no release yet. **v0.1.0 scope**: relocate Symfony's cache/build/log directories to the paths the station's launcher provides (`APP_CACHE_DIR`/`APP_BUILD_DIR`/`APP_LOG_DIR`), and expose the `GET /healthz` route the launcher polls before opening its window. See TFSAppWorkstation's `CONTRACT.md` for the full station↔project contract this bundle implements.

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
