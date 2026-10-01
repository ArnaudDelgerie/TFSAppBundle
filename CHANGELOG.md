# Changelog

## 0.2.0

`TFSAppKernel` is removed. Symfony's own `MicroKernelTrait` already honours
`APP_CACHE_DIR`, `APP_BUILD_DIR` and `APP_LOG_DIR` on every version this
bundle supports, so the class duplicated it — and an app that also composed
`MicroKernelTrait`, as the skeleton's kernel does, shadowed it anyway. Extend
Symfony's `Kernel` with `MicroKernelTrait`, as `symfony/skeleton` generates
it; nothing else changes.

## 0.1.0

First release: what an app needs to run under TFSAppHub. Extend `TFSAppKernel`
and the app's cache, build and log directories move to the ones the hub
injects (`APP_CACHE_DIR`, `APP_BUILD_DIR`, `APP_LOG_DIR`), so the installed
app never writes to its read-only snapshot; `GET /healthz` answers `200`
before routing and security run; `bin/console tfsapp:init` scaffolds a
`tfsapp.config.json` that declares only what today's hub understands; and
`bin/console tfsapp:doctor` prints what the app resolved at runtime — the hub
context, the bridge, the effective `DATABASE_URL` and the upload directory.

The hub context (`HubContextInterface`, exposed to Twig as the `tfsapp`
global) reads what the hub injects into the environment once, and exposes
capabilities, never the host. SQLite connections opened through `doctrine/dbal`
get `journal_mode=WAL`, `synchronous=NORMAL` and `busy_timeout=5000` asserted
on every connection, so a Messenger worker's transactions no longer stall the
web process serving the window. The bridge services talk to the hub over the
`actions` HTTP bridge — the secret store, the update check, and backend close
guards — each with typed refusals for every failure the hub distinguishes.
Upload storage confines every key to `APP_UPLOAD_DIR` and hardens the
responses it returns.

Requires PHP >= 8.2 and Symfony ^7.4|^8.0.
