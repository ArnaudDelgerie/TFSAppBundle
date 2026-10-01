# `run`: the app's own commands, in the foreground

`run` aliases in `tfsapp.config.json` are `bin/console` commands a user
runs directly and interactively — `tfsapp-hub run <id> <alias>
[args...]` — as opposed to the four launch-time
[`commands`](lifecycle.md) hooks, which the hub runs unattended around an
install or an update.

```json
"run": {
    "mcp-serve": { "command": "app:run:mcp-serve", "concurrent": true },
    "cleanup":   { "command": "app:run:cleanup" }
}
```

| Key | Type | Meaning |
| --- | --- | --- |
| `command` | string, required | a `bin/console` argument string, split on whitespace and passed as `argv` — no shell interpretation, same rule as `commands` |
| `concurrent` | boolean, optional | default `false`. Whether this alias tolerates siblings — other instances of itself, other active `run` commands, an already-open window — rather than requiring to run alone |

Each top-level key is the alias name a user types. `tfsapp-hub run <id>`
with no alias lists them back, naming each one's command and whether it
is `concurrent`. Typical use: a long-lived command such as an MCP server,
or a maintenance task the app exposes to its own user.

Everything after the alias belongs to the app's command — `run myapp
mcp-serve --help` asks the app's console for its help, never the hub.

## The three rules

**1 — the app layer must be current.** A `run` command refuses unless the
app's data directory records the version it is actually running: a
missing record, an older one or a newer one (a downgrade) each refuse,
naming the way out — open the app once, or run `tfsapp-hub update <id>`.
`run --stop` is exempt: recovering an installation stuck on a stale app
layer is one of its own jobs.

**2 — an app runs as many commands at once as its aliases permit.** A
`concurrent` alias stacks with itself and with other `concurrent`
entries; a non-`concurrent` one refuses beside anything active at all —
window included. Two apps running commands at once never interfere:
the state is keyed on the app's identifier. Whether an alias tolerates
siblings is the author's to declare: only the author knows whether two
instances may overlap, and the hub takes the declaration at face value.

**3 — the window's refusal is narrowed to its motive.** A `run` command
does not by itself keep a window from opening: a launch refuses only over
a non-`concurrent` command, or when it has a lifecycle event to perform —
and either refusal names the blocking alias, its pid, and the way out.
A `concurrent` command started beside a window survives that window's
closing: its lifetime belongs to whoever started it.

## `run --stop` and `run --replace`

- `tfsapp-hub run --stop <id>` stops every active command for that app;
  `run --stop <id> <alias>` narrows it to that alias's instances.
- `tfsapp-hub run --stop` with no id lists — and `run --replace` — every
  active command across every installed app.
- `tfsapp-hub run --replace <id> <alias> [args...]` stops the active
  instance first, then starts a fresh one — the way to release a
  non-`concurrent` alias without a manual `kill`. On a `concurrent`
  alias it refuses outright: there is nothing for it to replace when
  instances stack; start another instance instead.

What `--stop`/`--replace` do not promise: the `run` command's own
process is never force-killed, only what it started — a `--stop` against
one wedged somewhere other than waiting on its child reports that the
lock did not release, and a multi-target `--stop` reports every target's
own outcome rather than one combined verdict.

## What a `run` command gets

The full [environment](environment.md) — `DATABASE_URL`, `APP_SECRET`,
the writable directories — re-resolved fresh for each invocation. What
it does not get is the live app's own HTTP endpoint: its `APP_PORT` need
not be a running window's, so a command that needs to reach the app over
HTTP is outside the contract. `tfsapp-hub` never runs migrations on the
app's behalf here either: a `run` alias that needs the schema migrated
migrates itself first, or the app's `pre-install`/`pre-update` already
did ([database.md](database.md)).
