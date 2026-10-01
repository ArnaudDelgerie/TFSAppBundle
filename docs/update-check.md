# Update check

Lets the app ask its host whether a newer version of itself exists. The
*question* is the portable part; how an update is found and applied
belongs to the host, and the answer never contains a downloadable asset —
an app pointed at a download it cannot apply is worse off than one told
nothing. Showing a "new version available" line and a link to the release
notes is the intended use.

## Declare

```json
{ "actions": { "update": { "ipc": true, "bridge": true } } }
```

Either or both transports; `update` is the one group whose answers are
identical over both.

## The answer, and why it never fails

```json
{"status": "ok", "current": "1.1.0", "latest": "1.2.0",
 "update_available": true,
 "release_url": "https://…", "notes": "…"}
```

```json
{"status": "unavailable", "reason": "local_source"}
```

A check that cannot be made is a **result, not a failure**: both
transports answer successfully with `status: "unavailable"` rather than
throwing, so an app can call this on a timer without exception handling.
Whatever the host does to learn the answer, it does before the app asks —
polling costs nothing and cannot fail for a network reason, and a stale
answer is served rather than withheld.

`update_available` compares semver: a published version older than *or
equal to* the running one is `false`. `reason` is a stable machine-readable
token, so the app can hide its button on one and show a retry on another:

| token | when |
| --- | --- |
| `local_source` | installed from a local release archive, or a dev session — no release feed exists |
| `no_answer_yet` | a release-installed app whose cache holds nothing usable yet: the first launch after install, or every refresh so far failed |

Offline, rate-limited, a malformed release and a repository that 404s all
collapse into `no_answer_yet`; the detail goes to the host's own log.

The check is **pull, never push**: no automatic or startup check, no
notification badge. The app decides when to ask and how to render the
answer.

## Front: IPC

```js
const result = await invoke("update_check");
if (result.status === "ok" && result.update_available) { … }
```

## Back: the bundle's `UpdateCheckerInterface`

Inject `ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface`:

```php
$result = $updateChecker->check();
if ($result->wasReached() && $result->isUpdateAvailable()) {
    // $result->latest(), $result->releaseUrl(), $result->notes()
} else {
    // $result->reason(): 'local_source' or 'no_answer_yet'
}
```

| Method | Meaning |
| --- | --- |
| `isAvailable()` | never throws: probes `GET /update/check` — `200` for an enabled group, `404` for a disabled one |
| `check()` | an `UpdateCheckResult`, or `UpdateNotEnabledException` when the group is off |

`UpdateCheckResult` carries `wasReached()`, `isUpdateAvailable()`,
`current()`, `latest()`, `releaseUrl()`, `notes()` and `reason()`. Without
a bridge (`TFS_BRIDGE_URL` absent), `check()` throws
`BridgeUnavailableException` — probe with `isAvailable()` first when
silence is the right degradation.

## Back: the bridge route

`GET /update/check` — `200` with the result shape above, never an error
for a network condition; the usual `401 unauthorized` / `404 not_found`
(group off or unknown path) precede it.
