# The environment the hub injects

Everything the hub wants the app to know arrives as **environment
variables** — the manifest is read by the hub, never by PHP. Every process
the hub starts on the app's behalf gets the same list: the web server, a
worker, a lifecycle command, a `run` command. This page is the list, and
the bundle services that read it.

## The variables

| Variable | Value |
| --- | --- |
| `TFS_APP_IDENTIFIER` | `identifier` from the manifest, prefixed `dev.` in a dev session |
| `TFS_APP_VERSION` | `app_version` from the manifest |
| `APP_ENV` | `prod` (`dev` in a dev session) |
| `APP_DEBUG` | `0` (`1` in a dev session) |
| `APP_SECRET` | a per-app secret, generated once and kept — signed values survive restarts |
| `APP_PORT` | `app_port` if pinned, else a free loopback port for this launch |
| `APP_ORIGIN` | `http://127.0.0.1:<APP_PORT>` |
| `APP_PUBLIC_DIR` | the app's `public/` — nothing durable survives here; an update replaces it |
| `APP_CACHE_DIR` | writable cache directory — may be emptied at any launch; store nothing durable there |
| `APP_BUILD_DIR` | writable build directory — same lifetime note |
| `APP_LOG_DIR` | writable log directory — persists. The hub also writes `sidecar.log`, `worker-<n>.log`, `commands.log` and `hub.log` there: give your own log files other names |
| `APP_SESSION_DIR` | this app's own writable session directory — persists |
| `APP_UPLOAD_DIR` | the app's own durable files — never emptied at any launch ([files.md](files.md)) |
| `DATABASE_URL` | `sqlite:///<app data>/data/app.db` ([database.md](database.md)) |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` with `workers`, `doctrine://default?queue_name=async` with legacy `async_worker`, `sync://` otherwise ([workers.md](workers.md)) |
| `MERCURE_URL` / `MERCURE_PUBLIC_URL` | `<APP_ORIGIN>/.well-known/mercure` — same origin, loopback ([realtime.md](realtime.md)) |
| `MERCURE_JWT_SECRET` | fresh random value every launch, never persisted |
| `TFS_ASYNC_WORKER` | `"1"` when at least one worker declaration survived to launch |
| `TFS_WORKER_TRANSPORTS` | the transports the hub set out to run, after fallbacks, comma-separated; empty when none |
| `TFS_KEYRING_AVAILABLE` | `"1"` when the OS keyring answered, `"0"` when secrets fell back to a file |
| `TFS_MEDIA_MICROPHONE` | `"1"` when the microphone is declared **and** granted on this machine ([microphone.md](microphone.md)) |
| `PHP_BINARY` | the interpreter actually running the app; `PATH` is prefixed so `php` resolves to it |
| `TFS_BRIDGE_URL` / `TFS_BRIDGE_TOKEN` | the loopback bridge's address and bearer token — **present only when an `actions` group declares `"bridge": true`** |
| `TFS_USER_<NAME>_DIR` | one per declared `actions.paths` member — **present only when declared and GLib resolves it** ([user-directories.md](user-directories.md)) |

The capability flags (`TFS_ASYNC_WORKER`, `TFS_WORKER_TRANSPORTS`,
`TFS_KEYRING_AVAILABLE`, `TFS_MEDIA_MICROPHONE`) exist so an app can tell
its user something true about this machine. They report what the launch
actually achieved — never what the manifest asked for — and they are meant
to be read and shown, not branched on. The full reasoning is the
contract's [§3, "capabilities are reported, not
assumed"](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/3-the-environment-the-app-runs-in.md#capabilities-are-reported-not-assumed).

## Sessions

`APP_SESSION_DIR` is the app's own, and using it is opt-in — the hub never
patches the app's configuration:

```yaml
# config/packages/framework.yaml
framework:
    session:
        save_path: '%env(default::APP_SESSION_DIR)%'
```

The `default::` keeps the app booting outside the hub, where the variable
is absent. Without this, the bundled PHP's `session.save_path` is empty
and sessions land in `/tmp` — shared by every app on the machine and lost
at reboot.

## `HubContextInterface`

The bundle reads the identity and capability variables once and exposes
them — inject `ArnaudDelgerie\TFSAppBundle\HubContext\HubContextInterface`:

| Method | Meaning |
| --- | --- |
| `identifier()` | `TFS_APP_IDENTIFIER`, empty outside the hub |
| `version()` | `TFS_APP_VERSION`, empty outside the hub |
| `isAsyncWorker()` | whether at least one worker declaration survived to launch |
| `workerTransports()` | `list<string>` — the transports the hub is consuming |
| `isKeyringAvailable()` | whether the secret store is backed by the OS keyring |
| `isBridgeEnabled()` | whether `TFS_BRIDGE_URL` is present |
| `isRunningUnderHub()` | true only when `TFS_APP_VERSION` is set |

Outside the hub every value is empty or false — the same code runs under
`symfony server:start`, PHPUnit or CI unchanged.

## The `tfsapp` Twig global

When Twig is installed (and unless the bundle's `twig_globals` option is
off), the same values are the `tfsapp` global:

```twig
{% if tfsapp.running_under_hub %}
    {{ product }} {{ tfsapp.version }}
    {% if not tfsapp.async_worker %}<p>Scheduled work pauses when the window closes.</p>{% endif %}
{% endif %}
```

`tfsapp.version`, `tfsapp.running_under_hub`, `tfsapp.async_worker`,
`tfsapp.worker_transports`, `tfsapp.keyring_available`,
`tfsapp.bridge_enabled` — snake_case, mirroring the environment's own
names.

## Kernel directories

Symfony's own `MicroKernelTrait` (^7.4) already honours `APP_CACHE_DIR`,
`APP_BUILD_DIR` and `APP_LOG_DIR`, so the skeleton's `src/Kernel.php`
needs no change. Only an app that overrides `getCacheDir()`,
`getBuildDir()` or `getLogDir()` itself must keep honouring the variable —
a kernel that ignores them would write into the installed snapshot, which
an update replaces.

## `tfsapp:doctor`

`bin/console tfsapp:doctor` prints what the app resolved at runtime: the
hub context, whether secrets and update checks are reachable through the
bridge, the effective `DATABASE_URL` (and, for a SQLite file, whether it
exists, holds tables, and which `journal_mode` and `busy_timeout` apply),
plus the upload directory and whether it is writable. It also warns when
it can see a pitfall — a `DATABASE_URL` that is not SQLite, a session save
path that does not come from `APP_SESSION_DIR`, AssetMapper output that is
uncompiled or missing from `build_outputs`, transports the hub consumes
that the app does not configure, off-origin assets under `templates/` —
each naming its one-line fix. A silent doctor is a project ready for the
hub.
