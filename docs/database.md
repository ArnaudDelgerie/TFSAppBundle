# The database

The hub's database is SQLite, in every mode, and `DATABASE_URL` is the
host's to set: a process environment variable wins over Symfony's dotenv
files, so an app's own `.env` value is not overridden so much as unheard
once the hub starts the process. In dev it points at
`var/data/app.db` inside the project; installed, at `<app data>/data/app.db`.

## Develop against SQLite

The constraint is on the app's migrations more than its queries:
`doctrine:migrations:diff` emits DDL for the platform it was generated
against, so a migration produced on MySQL or Postgres passes review and
fails **at install time on the end user's machine**, inside a `pre-install`
hook. Develop against SQLite and the whole class of problem is gone by
construction. The `webapp` recipe's `.env` names PostgreSQL — replace it:

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/app.db"
```

That is also the file `tfsapp-hub dev` injects, so the console outside the
hub and the dev window share one database, and migrations are generated
for SQLite. `tfsapp:doctor` warns when `DATABASE_URL` resolves to anything
else.

## Who runs the migrations

The database is empty on a fresh install; creating the schema is the
app's job, declared as a `pre-install` lifecycle command — the hub runs it
at install time, before any window exists ([lifecycle.md](lifecycle.md)):

```json
"commands": {
    "pre-install": ["doctrine:migrations:migrate --no-interaction"],
    "pre-update":  ["doctrine:migrations:migrate --no-interaction"]
}
```

`tfsapp:init` writes exactly this pair when Doctrine Migrations is
installed. A dev session installs nothing and never runs them — the
developer's own console does:

```console
$ DATABASE_URL="sqlite:///$(pwd)/var/data/app.db" \
    php bin/console doctrine:migrations:migrate --no-interaction
```

With the `.env` line above, a plain `bin/console
doctrine:migrations:migrate` is enough; the explicit form is for pointing
at a database other than `.env`'s. One first-time detail: the hub creates
`var/data/` at launch, but a console run before the first one finds no
directory to create the file in — `mkdir -p var/data` first, or launch
`tfsapp-hub dev` once.

## The SQLite pragmas

SQLite's defaults punish a desktop app: a writer blocks every reader, and
the disk is fsynced on every commit. The bundle ships a Doctrine DBAL
middleware (on by default, only on SQLite connections — any other
platform is untouched) that issues on every connection:

| Pragma | Why |
| --- | --- |
| `journal_mode=WAL` | writers stop blocking readers |
| `synchronous=NORMAL` | fewer fsyncs — crash-safe only under WAL, hence the pair |
| `busy_timeout=5000` | a locked database waits 5 s instead of failing at once |

It runs per connection rather than once because an import or rescue can
restore the database without its `-wal` file, which puts it back in
rollback-journal mode — the middleware puts it right on the next connect.
`busy_timeout` does not cover `SQLITE_BUSY_SNAPSHOT`; that case is
retried upstream by Messenger's Doctrine transport. Turn the middleware
off if your project tunes its own connection:

```yaml
# config/packages/tfs_app.yaml
tfs_app:
    sqlite_pragmas: false
```

`tfsapp:doctor` reports which `journal_mode` and `busy_timeout` the
database file actually holds, and warns when the file was never opened
through Doctrine (still in `delete` journal mode).

## What the database is worth to the hub

The database is the one thing that survives an update unchanged — the
rollback anchor snapshots it, and `export`/`import` carry it
([files.md](files.md)). Nothing else in the app's data directory holds
app state on the app's behalf, so nothing durable belongs in `cache/`,
`build/`, `sessions/` or the project's own `var/`. The contract's
constraints live in [§3](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md#the-database-is-sqlite-and-that-is-a-constraint-on-the-app)
and [§5](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/5-the-apps-own-state.md).
