# `tfsapp-hub dev`: running the live project

```sh
tfsapp-hub dev path/to/project
```

runs the same app against its **live source** — served in place, never
snapshotted — for a developer who has not installed it. A window opens on
the app, and Ctrl-C stops the session.

The guardrail first: the hub does not watch the project, does not compile
anything, and does not build any asset. It serves the source as it finds
it on each request and relaunches the server on demand — nothing more.
An app whose frontend needs a build step builds it with its own tooling,
exactly as an installed app requires ([frontend.md](frontend.md)); when
the answer to "can the hub watch and rebuild for me?" comes up, it is no
— the developer's own build tool already has a `--watch`.

The one directory the hub writes into is the project's own `var/` —
nothing outside it is ever touched.

## What differs from an installed app

A closed list:

- `APP_ENV=dev` and `APP_DEBUG=1`, not `prod` and `0`.
- Every hub-injected path — `APP_CACHE_DIR`, `APP_BUILD_DIR`,
  `APP_LOG_DIR`, `APP_SESSION_DIR` — is rooted at the project's own
  `var/` instead of an OS data directory.
- `DATABASE_URL` points at `var/data/app.db` inside the project — still
  SQLite, still the host's to set, so migrations must run on SQLite in
  dev exactly as they do once installed ([database.md](database.md)).
- `APP_SECRET` is a fixed, throwaway constant — it buys the dev loop
  (a fresh secret per relaunch would log the developer out each time),
  and nothing signed with it is meant to outlive the session.
- The runtime identity is its own, `dev.<identifier>` — the window class,
  the GTK application id, the single-instance key, the cookie store, the
  data directory, the keyring namespace, all of it. This is what lets the
  same project be open in dev and installed at once, in two windows, on
  two databases, neither able to see the other's.
- No install, so no desktop entry: the window falls back to a
  class-derived label, so the switcher shows `dev.<identifier>` rather
  than the product name — a cost paid by the one person who can be told
  why.

## What does not differ

The [environment](environment.md) is the same variable list, in the same
names. The HTTP contract holds exactly. The isolation guarantees hold — a
dev session gets its own data directory, cookie store and liveness lock
by the same identifier-keyed rules; it is simply a different identifier.
The `actions` groups use the same transports and the same ACL boundaries
as when installed — including `picker`'s IPC-only boundary — and an
update check already answers `unavailable` / `local_source` in both: a
dev session has no release feed to compare against
([update-check.md](update-check.md)).

## No install, so no install lifecycle

The four [`commands`](lifecycle.md) events never run for a dev session —
the project was never installed, and nothing will ever "update" it out
from under a developer editing it live. Whatever `pre-install` would have
set up — typically `doctrine:migrations:migrate` — is the developer's own
to run, against the injected `DATABASE_URL`. With the `.env` line from
[database.md](database.md) in place, that is a plain

```sh
bin/console doctrine:migrations:migrate --no-interaction
```

`tfsapp:doctor` works in dev too, and its database checks read the same
file: a missing file, or one that holds no tables, is the doctor's cue to
remind you that dev never migrates by itself.

`app_version`'s install/update bookkeeping plays no part either: nothing
compares it, nothing is refused for a downgrade, nothing is recorded —
an author is free to edit it while iterating. The contract's dev
guarantees are its
[§9](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/9-running-a-project-in-dev.md).
