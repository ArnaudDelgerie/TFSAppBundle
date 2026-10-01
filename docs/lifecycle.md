# Lifecycle: install, update, and the app's data

An app declares, under `commands`, what has to run when it arrives on a
machine and when a newer version of it does. Both events belong to
`install` and `update`; a [dev](dev.md) session goes through neither.

```json
"commands": {
    "pre-install":  ["doctrine:migrations:migrate --no-interaction"],
    "post-install": ["app:seed-defaults"],
    "pre-update":   ["doctrine:migrations:migrate --no-interaction"],
    "post-update":  []
}
```

| Key | Means |
| --- | --- |
| `pre-install` | this app is arriving on this machine for the first time |
| `post-install` | same occasion, after the `pre-` list |
| `pre-update` | a newer version is replacing an older one, over the same data |
| `post-update` | same occasion, after the `pre-` list |

## The rules

- Each entry runs as `bin/console <args>` through the interpreter that
  serves the app, with the app's own directory as the working directory
  and the full [environment](environment.md). The strings are **argv,
  never a shell**: split on whitespace, no pipes, no redirection, no
  `&&`, and only `bin/console` commands can be declared — a manifest that
  runs on a user's machine is not a shell-injection vector.
- The commands of an event run once per install or update, in declared
  order, **before the user first sees the app**. The first non-zero exit
  stops the rest and fails the whole event, naming the command, its exit
  status and where its output was captured. Their output lands in
  `commands.log` under `APP_LOG_DIR`.
- **A `post-` command may not assume its own app is reachable over
  HTTP.** Nothing is serving yet when they run: reach the database, the
  filesystem, the container directly, exactly as any console command
  does.
- The version record is written only after the event's last command
  succeeds. A failure leaves the previous record — or none at all — and
  the next attempt replays the whole event; there is no partial state to
  reason about.

## When to bump `app_version`

The hub decides which event a moment is by comparing the manifest's
`app_version` against the version recorded in the app's data directory: no
record means install, a newer one means update, an equal one means
neither (a plain reinstall-after-`remove` runs no lifecycle command), a
newer recorded one is a downgrade and is refused — running old code
against data a newer version wrote is how a database gets corrupted
quietly. `app_version` must be canonical `MAJOR.MINOR.PATCH` and is
checked at install, update and [publish](publishing.md) time.

Bump it whenever the app changes in a way its data has to follow — above
all, whenever a migration is added. A source whose code moved but whose
version did not is, as far as the hub is concerned, the same version of
the app, and its update never runs.

## What an update and a rollback mean for the data

- **The database never sits between two versions.** The hub snapshots it
  before `pre-update` and reverts the snapshot, the code and the registry
  together the moment any step fails: an app either finishes the update
  it declared, or is left exactly where it started.
- **A successful update leaves a rollback anchor** — the outgoing source
  tree and the pre-update database — until `tfsapp-hub rollback <id>`
  consumes it: one step back, setting the database being left behind
  aside as a named rescue dump. A rollback is one generation; there is
  nothing behind it to roll back to a second time.
- **The anchor snapshots the database and nothing else** — not
  `uploads/`. A rollback therefore restores a database that may no longer
  agree with what is on disk: a row pointing at a file the newer version
  renamed or removed. An app that reorganises its files across a version
  bump handles that itself, in a `pre-update`
  command ([files.md](files.md)).
- **An interrupted update or import needs an explicit repair.** While a
  recovery journal or import intent sits in the data directory, `open`,
  `run`, `update`, `rollback`, `export`, `import` and removal refuse for
  that app; `tfsapp-hub repair <id>` either puts the pre-attempt state
  back or finishes the committed promotion. An interrupted rollback is
  finished, not repaired: `rollback <id>` resumes at its marker.

The full ordering, lease and repair guarantees are the contract's
[§6](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/6-lifecycle.md).
There are no build hooks: the developer builds on their machine, the hub
installs, serves and restarts — assets are built and declared under
`build_outputs` ([frontend.md](frontend.md)), never built at install
time.
