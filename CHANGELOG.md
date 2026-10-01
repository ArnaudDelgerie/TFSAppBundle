# Changelog

## 0.2.0

The README is now the app developer's getting started: install the hub, build
the minimal app, the ten pitfalls a fresh `symfony/skeleton` meets — the first
three caught by `tfsapp:doctor` — and publish and install it. `docs/` holds
one page per topic: five for building the app, four for the lifecycle, seven
for the native capabilities, and two for developing and publishing. Each page
shows what the feature lets the app do, its front and back (the IPC call and
the bundle service or bridge route), the parameters and the errors, and links
the hub's contract for depth. `docs/` is `export-ignore`d — a `composer require`
does not ship it.

`TFSAppKernel` is removed. Symfony's own `MicroKernelTrait` already honours
`APP_CACHE_DIR`, `APP_BUILD_DIR` and `APP_LOG_DIR` on every version this
bundle supports, so the class duplicated it — and an app that also composed
`MicroKernelTrait`, as the skeleton's kernel does, shadowed it anyway. Extend
Symfony's `Kernel` with `MicroKernelTrait`, as `symfony/skeleton` generates
it; nothing else changes.

`tfsapp:init`'s `actions` skeleton now covers all seven of the hub's groups:
`media` and `paths` are added, all members `false` like the rest.

The generated `TFSAPP_README.md` now lists `tfsapp-hub repair`, the command
to run after an interrupted update or import.

`tfsapp:doctor` no longer flags the FrankenPHP hot-reload block Flex scaffolds
in `templates/`: the hub never sets `FRANKENPHP_HOT_RELOAD`, so the block renders
nothing. The off-origin scan keeps running on everything outside that gate.

`tfsapp:doctor` now catches the three pitfalls a fresh `symfony/skeleton` +
`webapp` project meets, each naming its one-line fix: a `DATABASE_URL` that is
not SQLite (migrations are generated against another server and fail at install
time), a session save path that does not come from `APP_SESSION_DIR` (sessions
land in `/tmp`), and AssetMapper output that is uncompiled or missing from
`tfsapp.config.json`'s `build_outputs` (`public/assets/` is gitignored, so it
only ships once declared).

SQLite connections now really do get WAL under DoctrineBundle — they never
had. DoctrineBundle wires `doctrine.middleware`-tagged services sorted by
priority, so ours came last and `SqlitePragmaMiddleware::wrap()` received
another middleware's wrapper, not an `AbstractSQLiteDriver`, and returned it
unwrapped; and the compiler pass registering ours ran after `MiddlewaresPass`
had collected the tagged services, so on a fresh app it never reached the
connection at all. `wrap()` now wraps every driver and `SqlitePragmaDriver`
issues the pragmas only when the connection parameters name a SQLite driver
(`pdo_sqlite`, `sqlite3`, or an `AbstractSQLiteDriver` `driverClass`), and the
pass runs before `MiddlewaresPass` — after one Doctrine query,
`tfsapp:doctor` reports `sqlite_journal_mode: wal`.

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
