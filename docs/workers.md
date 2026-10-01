# Workers: off-window Messenger consumers

`workers` declares the app's background consumers. Declaring at least one
gets the app real Messenger transports it can dispatch to; declaring none
leaves `MESSENGER_TRANSPORT_DSN` at `sync://`, where handlers run inline
inside the request. Either way the app's own code is the same code —
which is the point.

```json
{
  "workers": [
    { "transports": ["courant", "planifie", "fond"] },
    { "transports": ["urgent"], "count": 2 }
  ]
}
```

| Key | Meaning |
| --- | --- |
| `transports` | an ordered, non-empty list of Messenger transport names. The order is its priority — `messenger:consume a b c` rescans from the first transport after each envelope — and there is no separate priority key. Several transports on **one** worker interleave in that order; several **workers** are for latency, never for throughput or routing |
| `count` | copies of this declaration, default `1`, capped at `4` (a higher value falls back to `4`, with a printed reason). A declaration naming a `scheduler_*` transport falls back to `count: 1` — a Scheduler transport consumed twice fires every task twice |

`async_worker: true` is kept as sugar for one declaration consuming
`async` with the DSN it always had
(`doctrine://default?queue_name=async`). A manifest spelling both keys is
refused.

## The transports must exist in the app

Every transport named here must be configured in the app's
`config/packages/messenger.yaml` — the hub runs
`bin/console messenger:consume <transports…>`, and a consumer handed a
transport that does not exist exits immediately. The hub gives up on a
copy after a few failed starts and tells the user which transports
stopped being consumed; `tfsapp:doctor` warns about it beforehand.
Conversely, the app's own `framework.messenger.routing` decides what
lands on each transport — the manifest declares transports, never
routings.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            urgent:  'doctrine://default?queue_name=urgent'
            courant: 'doctrine://default?queue_name=courant'
```

## How the hub supervises

Each copy is one `bin/console messenger:consume <transports…>
--time-limit=3600 --memory-limit=256M`, started with the app's window and
stopped with it:

- A copy recycles at least every hour, and whenever it passes 256 MB —
  **a handler must not count on state living in the consumer process**.
- A copy that exits after at least 10 seconds is a recycle: restarted at
  once. One that exits sooner is a failed start: restarted after 1, 2, 4,
  then 8 seconds, and the fifth consecutive failure gives that copy up
  for the rest of the launch. Every other copy carries on.
- Each copy writes its own `worker-<n>.log` under `APP_LOG_DIR` —
  restarts and give-up included — so do not give an app log that name
  ([environment.md](environment.md)).

Nothing outlives the window today: closing the app stops its workers, so
work that must survive a window close is the user's decision, asked for
outside the manifest, and does not exist yet.

## Reading what actually runs

`TFS_ASYNC_WORKER` and `TFS_WORKER_TRANSPORTS` are computed once at
launch, from the declaration after fallbacks, and never change — they are
not a live report. A slot's later fate — a restart, a give-up — reaches
no environment variable; it is written to that slot's `worker-<n>.log`.
Both are meant for telling the user something true ("your 8 a.m. task
will not fire with the window closed"), through
[`HubContextInterface`](environment.md):

```php
if ($hub->isRunningUnderHub() && !$hub->isAsyncWorker()) {
    // show the user that queued work pauses when the window closes
}
```

An uncaught constraint: `DATABASE_URL` is always SQLite, which
serializes writers regardless of `count` — extra copies pay off only for
handlers that spend time outside SQLite ([database.md](database.md)).
The declaration's full rules are the contract's
[§2, "declaring off-window work"](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/2-tfsapp-config-json.md#declaring-off-window-work).
