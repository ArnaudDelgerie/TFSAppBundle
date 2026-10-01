# Secrets

Read and write the app's own secrets in the OS keyring, from the
webview's JavaScript and from PHP, against the same store. The store is
namespaced per app — the keyring service name is the app's `identifier` —
which keeps two apps from colliding. **It does not keep them from reading
each other**: the Secret Service authorises per login session, so any
process running as this user can list and read any service's entries. Read
the namespace as tidiness, not secrecy, and do not store in it something
whose disclosure to another application on the same machine would be a
breach.

## Declare

```json
{
  "actions": {
    "secrets": { "ipc": true, "bridge": true, "keys": ["openai", "anthropic"] }
  }
}
```

Either or both transports may be on. `keys` is required the moment either
is: it is a manifest, not a permission grant — what it buys is
typo-catching (a `get`/`set` on an undeclared key fails loudly), the only
reliable way to render a settings screen, and a cap on how many secrets
one app can ever touch. Reserved keys (`app-secret`, which signs CSRF
tokens and remember-me cookies) are refused even when declared. Values
are capped at 8 KiB, checked before the store is touched.

## Front: IPC

Five commands, each answering for the calling window's own app only:

```js
await invoke("secret_list");                                    // → [{ "key": "openai", "set": true }, …], declared order
await invoke("secret_has",    { key: "openai" });               // → true or false
await invoke("secret_get",    { key: "openai" });               // → "sk-…", or null when never set
await invoke("secret_set",    { key: "openai", value: "sk-…" }); // → null
await invoke("secret_delete", { key: "openai" });               // → true when a value existed, else false
```

A refused call rejects with one of these codes: `key_not_declared`
(reserved, or not in `keys`), `value_too_large` (`secret_set` only),
`storage_failed` (the store failed, or did not answer within 5 s), and
`unavailable` when no store backs the calling window at all.

## Back: the bundle's `SecretStoreInterface`

Inject `ArnaudDelgerie\TFSAppBundle\Bridge\SecretStoreInterface`. It
talks to the bridge (`TFS_BRIDGE_URL` + `TFS_BRIDGE_TOKEN`, present
because the group declares `"bridge": true`):

| Method | Meaning |
| --- | --- |
| `isAvailable()` | never throws: `false` covers "no bridge", "group not enabled" and any probe failure |
| `keys()` | `SecretKey[]`, each with `key()` and `isSet()` — the settings screen renders itself from this |
| `has($key)` | bool |
| `get($key)` | the value, or `null` for a declared-but-unset key — not an error |
| `set($key, $value)` | void |
| `delete($key)` | `false` when no value existed — not an error |

Every method refuses an undeclared key with
`SecretKeyNotDeclaredException`; a hub-side store failure answers
`SecretStorageFailedException`; no bridge at all is
`BridgeUnavailableException`; a running bridge whose secrets group is off
(`ipc`-only manifest, or another group started the bridge) is
`SecretsNotEnabledException`. Degrading rather than throwing on
availability is the pattern: probe with `isAvailable()`, then use it.

## Back: the bridge routes

The same five operations over plain HTTP, every route requiring
`Authorization: Bearer <token>`:

| Route | Body | Success |
| --- | --- | --- |
| `GET /secrets/keys` | — | `200 {"keys": [{"key": "…", "set": bool}, …]}` |
| `POST /secrets/has` | `{"key": "…"}` | `200 {"has": bool}` |
| `POST /secrets/get` | `{"key": "…"}` | `200 {"value": "…"}`, or `404 {"error": "not_found"}` when never set |
| `POST /secrets/set` | `{"key": "…", "value": "…"}` | `200 {"ok": true}` |
| `POST /secrets/delete` | `{"key": "…"}` | `200 {"ok": bool}` — whether a value existed |

Errors, in the order checked: `401 unauthorized` (bad token) → `404
not_found` (group off or unknown path) → `413 payload_too_large` (body
over 16 KiB) → `400 invalid_body` → `403 key_not_declared` → `413
value_too_large` → `500 {"error": "storage_failed"}`. The bridge never
logs the token or a secret value.

## Choosing a transport is a real trade-off

| | protects | exposes |
| --- | --- | --- |
| IPC | confidentiality toward the PHP process — the value never touches it; the app's own JavaScript can still read it | an XSS in the app can read or overwrite a declared key |
| Bridge | integrity — a bearer token only PHP holds | confidentiality to PHP and anything that logs it |

An app building an API-key entry form has a legitimate reason to want
`ipc`. Publishing a release whose manifest turns `ipc` on answers an
explicit confirmation at the terminal — see
[publishing.md](publishing.md).
