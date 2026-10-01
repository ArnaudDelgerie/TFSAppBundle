# The manifest: `tfsapp.config.json`

A JSON file at the project root. It is the single source of truth for the
app's identity and for what it declares — the hub reads it at install,
update and launch time. **PHP never does**: an app learns its own identity
from the [environment](environment.md), not by parsing its own manifest, so
the same code runs unchanged under any deployment that sets those
variables. `bin/console tfsapp:init` generates a starting manifest; this
page is the field-by-field reference.

## Required fields

| Field | Type | Meaning |
| --- | --- | --- |
| `product_name` | string | The human-readable name. Feeds the window title and the desktop entry's `Name=`. |
| `identifier` | string | Reverse-domain technical identity, e.g. `dev.local.myapp`. Everything the OS keys per app derives from it — data directory, keyring namespace, cookie store, window class. |
| `project_name` | string | Machine-friendly slug. Not an identity key: the handle a user types on the command line is assigned at install time by the hub. |
| `app_version` | string | The app's release version. Must be canonical `MAJOR.MINOR.PATCH` — no leading zeros, no suffix, no `v`. |

`app_version` is load-bearing: the hub compares it to decide whether a
source is an install, an update or a downgrade to refuse, and it refuses a
non-canonical value at install, update and `publish` time. Bump it whenever
the app changes in a way its data must follow — a source whose code moved
but whose version did not is, as far as the hub is concerned, the same
version. See [lifecycle.md](lifecycle.md).

Two constraints on `identifier` and the hub-local id: the identifier may
not be exactly `hub`, `TFSApp` or `applications` (the hub's own directory
names), and the install handle may not end in `.previous` (the update
rollback anchor's suffix). Unknown top-level keys only warn, never refuse —
including `releases_repo`, a retired key the hub accepts and ignores.

## One identity, several surfaces

Each surface the OS shows the user is fed by exactly one field:

| Surface | Fed by |
| --- | --- |
| Window title | `product_name` |
| Desktop entry `Name=` | `product_name` |
| Window class, GTK application id, single-instance key | `identifier` |
| Data directory, keyring namespace, cookie store | `identifier` |
| The handle typed on the command line | assigned at install, in the hub's registry |

An installed app gets a `.desktop` entry written by the hub from
`product_name` and `icon_path` — an app must not ship a `.desktop` file of
its own. Without one (a [dev](dev.md) session, or `install
--no-desktop-entry`), the window falls back to a label derived from the
window class, so the switcher shows `dev.local.myapp` rather than its
product name.

## Optional fields

| Field | Type | What it does | Detailed in |
| --- | --- | --- | --- |
| `app_port` | integer or null | Pins the app's loopback port instead of a fresh free one per launch. Leave it out unless something outside the app must know the port in advance; a pinned port already taken stops the launch (per-install override: `port_override` in the app's `data/config.json`). | — |
| `icon_path` | string | Project-root-relative path to one square source PNG (1024×1024 RGBA recommended). Feeds launcher, switcher and window icon. | [frontend.md](frontend.md) |
| `splash_path` | string | Project-root-relative path to one self-contained HTML file (inline CSS and JS only) shown while the app cold-starts. Missing or unreadable falls back to the hub's own cold-start page. | — |
| `splash_bg` / `splash_text` | string (`#rgb` or `#rrggbb`) | Recolour the cold-start page's background and text without authoring one. | — |
| `commands` | object | Lifecycle commands the hub runs around an install or an update: `pre-install`, `post-install`, `pre-update`, `post-update`. | [lifecycle.md](lifecycle.md) |
| `run` | object | Named `bin/console` aliases a user runs directly: `run <id> <alias> [args...]`. | [run.md](run.md) |
| `actions` | object | Which native capabilities the app's own code may reach, and over which transport (`ipc` for the webview, `bridge` for PHP). | [secrets.md](secrets.md), [update-check.md](update-check.md), [picker.md](picker.md), [open-files.md](open-files.md), [close-guard.md](close-guard.md), [microphone.md](microphone.md), [user-directories.md](user-directories.md) |
| `file_associations` | object | The MIME types this app declares it can open — puts it in the desktop's "Open with" menu. | [open-files.md](open-files.md) |
| `workers` | array of objects | Declares background Messenger consumers, each an ordered transport list plus an optional `count` (1–4). | [workers.md](workers.md) |
| `async_worker` | boolean | Sugar for one worker consuming `async`. Refused together with `workers`. | [workers.md](workers.md) |
| `build_outputs` | array of strings | Project-relative directories the app's frontend build produces, gitignored in the project; `publish` embeds them in the release archive. | [frontend.md](frontend.md), [publishing.md](publishing.md) |

## A minimal manifest

```json
{
  "product_name": "LabelBoard",
  "identifier": "dev.local.labelboard",
  "project_name": "labelboard",
  "app_version": "1.4.0"
}
```

`tfsapp:init` scaffolds more than this — the four fields above, a `workers`
question, `commands` when Doctrine Migrations is installed, and an
`actions` skeleton with all seven groups present and every member `false` —
so an app turns a capability on by flipping its member to `true`. The depth
behind every field is the contract's
[§2](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/2-tfsapp-config-json.md).
