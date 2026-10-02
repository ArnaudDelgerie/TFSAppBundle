# TFSAppBundle

A Symfony bundle for apps run by [TFSAppHub](https://github.com/ArnaudDelgerie/TFSAppHub),
the host that installs and runs Symfony apps as local desktop apps. The bundle is
optional — an app can honour the hub's
[`CONTRACT.md`](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/CONTRACT.md)
without it — but it wraps the parts every app reimplements: the `/healthz` route,
the hub's injected environment, SQLite pragmas, upload storage and the bridge
services for the hub's native capabilities.

This README is the getting started. One page per app-side feature — front and
back — lives under [`docs/`](docs/) (the table of contents is at the bottom).

## Requirements

- PHP `>=8.2`, Composer, and the hub: install it from
  [TFSAppHub's README](https://github.com/ArnaudDelgerie/TFSAppHub#install) —
  one AppImage, no Rust, no Tauri CLI, no GTK development libraries.

## Make your first app

An app is a Symfony project. From nothing to an installed app:

```sh
composer create-project symfony/skeleton myapp
cd myapp
composer require arnauddelgerie/tfs-app-bundle
bin/console tfsapp:init
```

`tfsapp:init` generates `tfsapp.config.json` at the project root — it prompts
for the four identity fields (`project_name`, `product_name`, `identifier`,
`app_version`), each with a sensible default, then one yes/no `workers`
question — plus a starter `CHANGELOG.md` and `TFSAPP_README.md`. The bundle
registers `/healthz` on its own; no route to declare.

A fresh skeleton has no route in production, and an installed app runs in
production — give the app one page of its own:

```php
<?php
// src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    #[Route('/')]
    public function home(): Response
    {
        return new Response('It runs.');
    }
}
```

Run it under the hub, live:

```sh
tfsapp-hub dev .
```

A window opens on your app, served from this directory as it is. `dev` watches
nothing, compiles nothing and builds no assets — your build tool already has a
`--watch`; the hub serves and restarts. Ctrl-C stops the session. If your app
uses Doctrine, run the migrations yourself: `dev` never runs them
(`bin/console doctrine:migrations:migrate --no-interaction`).

Then see it the way your users will, as an installed app — a release pins a
commit, so commit everything first:

```sh
git init && git add -A && git commit -m "First app"
cd ..
mkdir -p out
tfsapp-hub publish myapp --local out/
```

`publish` prints the exact `tfsapp-hub install` command for the archive it
wrote — run it, then:

```sh
tfsapp-hub open myapp
```

The installed app also gets a `.desktop` entry, named from `product_name`
with `icon_path`'s icon, so it shows up in the desktop's application grid
(in principle — that is the desktop environment's call); `install
--no-desktop-entry` skips it. See
[docs/manifest.md](docs/manifest.md#one-identity-several-surfaces).

## The ten pitfalls

Every one of these was met on a fresh project; the first and third come
with the `webapp` pack most apps want (`composer require webapp`).
`bin/console tfsapp:doctor` catches the first three (below) and names the
fix; all ten are detailed in [`docs/`](docs/).

1. **`DATABASE_URL` is PostgreSQL in the `webapp` recipe's `.env`.** The
   installed app's database is SQLite, and migrations are generated for the
   server the console sees. Set
   `DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/app.db"` — the same
   file `tfsapp-hub dev` uses, so your console and the dev window share one
   database. See [docs/database.md](docs/database.md).
2. **Sessions land in `/tmp`.** The bundled PHP's `session.save_path` is
   empty, so sessions are shared by every app on the machine and lost at
   reboot. Set `save_path: '%env(default::APP_SESSION_DIR)%'` under
   `framework.session`. See [docs/environment.md](docs/environment.md).
3. **Assets work in `dev` and 404 once installed.** `dev` serves AssetMapper's
   output on the fly; an installed app serves only what shipped. Run
   `APP_ENV=prod bin/console asset-map:compile` and add
   `"public/assets"` to `build_outputs` (`"public/build"` with Encore).
   See [docs/frontend.md](docs/frontend.md).
4. **The skeleton has no route in prod.** Add a page of your own — the
   getting started above already does.
5. **`tfsapp-hub dev` never runs migrations.** Run them yourself; pitfall 1
   makes it a plain `bin/console doctrine:migrations:migrate`.
   See [docs/database.md](docs/database.md).
6. **Anything off-origin is blocked by the CSP** — CDN scripts, web fonts —
   with only a console message. Use `importmap:require` or your own build.
   See [docs/frontend.md](docs/frontend.md).
7. **Pasting an image or dragging a file in from the file manager delivers no
   file** in the webview. Use `<input type="file">` or the native `picker`.
   See [docs/webview.md](docs/webview.md) and [docs/picker.md](docs/picker.md).
8. **Durable files go to `APP_UPLOAD_DIR`** (`UploadStorageInterface`), never
   `public/` or `var/` — an update replaces those. See
   [docs/files.md](docs/files.md).
9. **Every transport named in `workers` must exist in `messenger.yaml`**;
   without `workers`, Messenger runs synchronously. See
   [docs/workers.md](docs/workers.md).
10. **To publish:** commit everything, bump `app_version` (strict semver),
    add a `## <version>` entry to `CHANGELOG.md`. See
    [docs/publishing.md](docs/publishing.md).

## Security

For the app developer, one point each; the linked page holds the
detail.

1. **Loopback is not authentication.** Any local process reaches the
   app's port, so CSRF protection on state-changing routes is the
   answer. See [docs/frontend.md](docs/frontend.md).
2. **An XSS has the reach of the app's own scripts**, declared `ipc`
   secrets included. See [docs/secrets.md](docs/secrets.md).
3. **Secrets over IPC or the bridge is a real trade-off**; never log the
   bridge token or a secret value. See [docs/secrets.md](docs/secrets.md).
4. **An uploaded file served back inline goes through `inline()`** and
   its sandboxing CSP, else `download()`. See
   [docs/files.md](docs/files.md).
5. **With no reachable keyring, `APP_SECRET` and declared secrets fall
   back to a plaintext `0600` file** (deliberately not encrypted);
   `TFS_KEYRING_AVAILABLE` / `HubContextInterface::isKeyringAvailable()`
   tells the app so it can warn the user. See
   [docs/secrets.md](docs/secrets.md).

## The check: `tfsapp:doctor`

```sh
bin/console tfsapp:doctor
```

prints what the app resolved at runtime — the hub context, the bridge, the
effective `DATABASE_URL` (and, for SQLite, whether it exists, holds tables,
and which `journal_mode` and `busy_timeout` apply), the upload directory —
and warns about every pitfall it can see, each naming its one-line fix. A
silent doctor is a project ready for the hub.

## Documentation

Each page covers one topic: what it lets the app do, the front (IPC) and the
back (the bundle's service, or the bridge route), the parameters and the
errors.

Building the app:

- [`docs/manifest.md`](docs/manifest.md) — every `tfsapp.config.json` field
- [`docs/environment.md`](docs/environment.md) — the injected variables,
  `HubContextInterface`, the `tfsapp` Twig global, `tfsapp:doctor`
- [`docs/database.md`](docs/database.md) — SQLite, migrations, the pragmas
- [`docs/frontend.md`](docs/frontend.md) — built assets, the CSP, external
  links, where `invoke` comes from
- [`docs/webview.md`](docs/webview.md) — the WebKitGTK webview platform:
  greyscale text, no scroll anchoring, file paste and drag-in, GPU
  diagnostics
- [`docs/files.md`](docs/files.md) — `UploadStorageInterface`, downloads,
  what export and import carry

Lifecycle:

- [`docs/lifecycle.md`](docs/lifecycle.md) — `commands`, `app_version`,
  what an update and a rollback mean for the app's data
- [`docs/run.md`](docs/run.md) — `run` aliases, `concurrent`, `run --stop`,
  `run --replace`
- [`docs/workers.md`](docs/workers.md) — `workers`, Messenger transports,
  the supervisor's limits
- [`docs/realtime.md`](docs/realtime.md) — Mercure, the subscriber JWT, the
  cookie, `withCredentials`

Native capabilities:

- [`docs/secrets.md`](docs/secrets.md) — the OS keyring, from the webview
  and from PHP
- [`docs/update-check.md`](docs/update-check.md) — asking the host whether a
  newer version exists
- [`docs/picker.md`](docs/picker.md) — native file and directory choosers
- [`docs/open-files.md`](docs/open-files.md) — receiving files from the
  desktop, `file_associations`
- [`docs/close-guard.md`](docs/close-guard.md) — warning before a close
  loses work
- [`docs/microphone.md`](docs/microphone.md) — capturing audio
- [`docs/user-directories.md`](docs/user-directories.md) — the OS user
  directories

Developing and publishing:

- [`docs/dev.md`](docs/dev.md) — `tfsapp-hub dev`, what differs from an
  installed app
- [`docs/publishing.md`](docs/publishing.md) — `publish`, `--local`,
  `--repo`, the changelog, the `secrets.ipc` confirmation

The hub's own
[`CONTRACT.md`](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/CONTRACT.md)
is the reference for hub contributors; each page links the clause it
summarises.

## License

MIT — see [LICENSE](LICENSE).
