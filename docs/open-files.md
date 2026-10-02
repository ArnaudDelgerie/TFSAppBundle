# Open files: receiving paths from the desktop

Lets the app receive local paths a person or the desktop environment
hands it — through `tfsapp-hub open <id> -- <path>...`, or by choosing the
app in the file manager's "Open with" menu. The hub delivers **paths** —
regular files, and directories for a receiver that opted into them; how
the app reads or displays each one is entirely its own backend's business.
There is no generic file-reading service here, and no PHP API: PHP
participates through the app's own routes, once the webview has received
the paths and accepted them.

## Declare

```json
{
  "actions": { "open_files": { "ipc": true, "directories": true } },
  "file_associations": { "mime_types": ["text/markdown", "application/json"] }
}
```

- `ipc` is the transport (required for anything to be delivered) and the
  group's only one: no `bridge` member, no HTTP route.
- `directories` is a default-off option that lets the hub deliver
  existing local directories through any launch path. Without it, a
  directory handed to the app is refused with a diagnostic naming the
  path. `directories: true` without `ipc: true` is invalid.
- `file_associations` is the advertising half — what puts the app in the
  "Open with" menu. A nonempty `mime_types` without the
  `actions.open_files` receiver is refused: an app advertised in a menu
  whose selections it can never acknowledge would be a promise the
  manifest cannot keep. The reverse is fine — a receiver with no declared
  types stays out of the menus but still works through
  `tfsapp-hub open <id> -- <path>...`.

Each `mime_types` entry must be a `type/subtype` pair, each side 1–127
characters from letters, digits and `!#$&^_.+-` — syntax is checked, the
host's MIME database is not consulted: this is a statement of what the
*app* can open. `inode/directory` in `mime_types` requires both
`ipc: true` and `directories: true` on the receiver. A declaration is not
a permission: MIME types are never content sniffing and never a
filesystem authorization, and a delivered directory path grants no access
beyond what the app's backend already has.

## The wire

`invoke` is Tauri's global — see
[Where `invoke` comes from](frontend.md#where-invoke-comes-from).

One invocation — one `open` command, one "Open with" selection — creates
exactly one request: an opaque id plus the ordered list of paths. The
whole batch is validated **before** anything is enqueued: every path must
name a local, existing regular file (or directory, opted in); a batch
that fails validation is refused with a diagnostic naming the offending
path and nothing is enqueued. Symbolic links are followed. At most **64
pending requests** per app and **64 paths** per request; past either
bound the invocation is refused with a visible diagnostic, never a silent
eviction.

```js
invoke("open_files_pending")
// → { "requests": [ { "id": "…", "paths": ["/home/…/a.md", "…"] }, … ] },
//   this window's unacknowledged requests, oldest first. Reading removes nothing.
invoke("open_files_ack", { id: "…" })
// → null, once this window has accepted that request.
```

Errors: `invalid_id` (empty, or over 128 bytes of UTF-8),
`unknown_request` (the id names no request this window was ever
assigned), `unavailable` (the group is not declared, or no state backs
the calling window), and `closing` — this window's close or whole-app
shutdown has committed.

**Acknowledgement is removal, and it is idempotent.** Acking the same id
again succeeds harmlessly — which is what lets a reload replay survive.
Delivery is replayable until acknowledgement, not exactly-once: a reload
between acceptance and ack re-exposes the same id, so acceptance must be
idempotent by request id — on the webview side *and* in whatever PHP
work it triggers — before the ack is sent.

## The startup sequence: subscribe before you read, never poll

The hub notifies with the Tauri event `tfsapp://open-files-pending`,
emitted to one selected window — the most recently focused eligible one —
and the event carries no paths: it only says "call
`open_files_pending`". Register the listener on this window first
(a target-less `listen()` would also ring for the app's *other*
windows), await its registration, then read once:

```js
let running = false, again = false;
const unlisten = await getCurrentWebviewWindow()
  .listen("tfsapp://open-files-pending", () => drain());

async function drain() {
  if (running) { again = true; return; }
  running = true;
  try {
    const { requests } = await invoke("open_files_pending");
    for (const request of requests) {
      await acceptOnce(request);   // idempotent by request.id, PHP included
      await invoke("open_files_ack", { id: request.id });
    }
  } finally {
    running = false;
    if (again) { again = false; drain(); }  // a notification arrived mid-cycle
  }
}
await drain();
```

`acceptOnce` is the app's own idempotence boundary: it records the
request id as accepted — app-side, before any of its own work runs — so a
reload that replays an already-accepted id selects the already-open
document instead of duplicating it, and only then acks. After startup the
receiver reads only on notification; there is no polling. A file-bearing
invocation never forces navigation and never opens a second window for an
app that is already serving; an arrival during the splash waits for the
app's first real document.

The queue lives in the hub process's memory and is not durable: a hub
crash or ordinary exit ends its lifetime, and so does the app's. One race
this group does not close: an arrival in the narrow window where the
running instance is already shutting down can be lost with it. An app
that must not lose work reads on every notification and acknowledges
promptly.
