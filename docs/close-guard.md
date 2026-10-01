# Close guards: warning before a close loses work

Lets the app mark work that a person should be warned about before a
window closes, from either side of itself: the webview for a document's
unsaved changes, PHP for app-wide background work. Closing a guarded
window shows one native confirmation — host-authored, `Cancel` as the
safe default — before the window is hidden or the backend stopped;
cancelling keeps the running app exactly as it was. A close nothing
guards closes the way it always did.

## Declare

```json
{ "actions": { "close_guard": { "ipc": true, "bridge": true } } }
```

Two namespaces, one rule each: `ipc` is the webview's, for guards that
belong to one document in the calling window; `bridge` is PHP's, for
guards that belong to this running app instance. Neither transport can
reach the other's namespace, and no request can name another window,
another document or another app. Both switches default to off and
neither implies the other; declaring only `"bridge": true` still starts
the bridge and injects `TFS_BRIDGE_URL`/`TFS_BRIDGE_TOKEN`.

## Front: the document's unsaved changes

Guards are identifiers, not messages: an id is an app-chosen non-empty
string of at most 128 bytes of UTF-8 (`editor:<document-id>`). Every
committed load of a window's main frame gives the page a fresh opaque
context, fetched once at startup and presented back with every call:

```js
// once per document, at startup:
const { context } = await invoke("close_guard_context");
// the document becomes dirty:
await invoke("close_guard_register", { context, id: `editor:${docId}` });
// saved, or the dirty state discarded:
await invoke("close_guard_remove", { context, id: `editor:${docId}` });
```

Registration is idempotent per owner and id; removing an absent id
succeeds harmlessly. The hub caps each namespace at **16 frontend guards
per window and 16 backend guards per app instance**; exhaustion is an
explicit error, never a silent eviction. A document's guards are erased
when its successor first fetches the context (a reload or navigation),
and a call presenting anything but the current context is refused with
`stale_document`. **Unavailable is a result, not an exception** — a hub
without the group refuses the invoke outright, so degrade:

```js
try {
    const { context } = await invoke("close_guard_context");
    await invoke("close_guard_register", { context, id: `editor:${docId}` });
} catch {
    // No guard installed; the app closes the way it always did.
}
```

Error codes shared by both transports where they have them:
`stale_document` (frontend only), `invalid_id`, `too_many_guards`,
`closing` — shutdown has committed, or this window's close already has —
and `unavailable` when no state backs the calling window at all.

## Back: the bundle's `BackendCloseGuardInterface`

PHP registers before starting vulnerable work and removes its own guard
in `finally` — inject
`ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuardInterface`:

```php
public function __invoke(ExportJob $job): void
{
    $id = 'export:' . $job->getId(); // distinct ids for simultaneous jobs

    $guarded = $this->guards->register($id);

    if (!$guarded) {
        // No bridge at all, or the close_guard group's routes are gated —
        // never a claim of protection. Run unguarded, or refuse the work.
    }

    try {
        // the vulnerable work
    } finally {
        if ($guarded) {
            $this->guards->remove($id); // the owner removes its own guard
        }
    }
}
```

| Method | Returns |
| --- | --- |
| `register($id)` | `true` only after the hub acknowledged it (HTTP 200). `false` means no guard was installed — no bridge, or the group's routes gated with `404` — and never implies protection |
| `remove($id)` | the same result and error table; idempotent |

Every refusal with its own meaning stays a typed exception:
`CloseGuardInvalidIdException` (400 `invalid_id`),
`CloseGuardTooManyException` (429 `too_many_guards`),
`CloseGuardClosingException` (503 `closing`), plus the shared bridge
errors — `BridgeProtocolException` (401, or a 400 that is not
`invalid_id`), `BridgePayloadTooLargeException` (413) and
`TransportExceptionInterface` when the bridge cannot be reached. This
call is the probe: never test availability by registering a sacrificial
guard.

Guards have **no automatic expiry** — a long job must not lose protection
because it cannot send a heartbeat, and only the code that registered it
or this process's exit clears one. A task that crashes may leave its
warning behind for the rest of the launch; the person can always choose
to close anyway, and the next launch starts clean.

## What the confirmation does

The dialog names the stakes by category — unsaved changes, background
work, or both — with no app-supplied content. The backend is consulted
only when this close would stop the shared backend (the last window); a
clean secondary window never prompts for another window's work. Cancel,
dismiss or a failed dialog never grants permission. Confirmation is
permission for this one close attempt — not a persistent bypass, not an
instruction to save. Once shutdown commits, registrations and removals on
either transport answer `closing`. Work that must survive a close belongs
in a [worker](workers.md), not under a guard.
