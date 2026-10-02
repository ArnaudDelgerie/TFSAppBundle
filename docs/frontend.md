# The frontend

The hub serves the app's `public/` directory and nothing of its own — no
second mount, no reserved path prefix. Everything a page loads comes from
the app itself: the hub never builds assets, so **the frontend ships
built**.

## Built assets and `build_outputs`

`build_outputs` names the project-relative directories the author's own
build step produces, gitignored in the project:

```json
{ "build_outputs": ["public/assets"] }
```

- With AssetMapper (the `webapp` recipe's default): run
  `APP_ENV=prod bin/console asset-map:compile`, which writes
  `public/assets/`, and declare `"public/assets"`.
- With Webpack Encore: declare `"public/build"` (its default output).

Only `publish` reads this key — `install`, `update` and `dev` ignore it —
and it embeds the declared directories in the release archive **as they
stand on the author's machine**. `publish` refuses a declared path that
escapes the project, sits under an excluded component (`vendor/`, `var/`,
`node_modules/`, `.git/`), is absent or empty, holds a tracked file, is
not gitignored, or holds anything but regular files and directories.

The trap is that `tfsapp-hub dev` runs with `APP_DEBUG=1` and serves
AssetMapper's output on the fly, so everything works in dev — and
`/assets/…` answers `404` once installed, because `public/assets/` is
gitignored and only ships once declared and compiled. `tfsapp:doctor`
warns for both halves.

## The CSP: nothing off-origin

Every response carries these headers unless the app sets its own — a
response that already carries one of these field names keeps its own:

```
Cache-Control: no-store          (except /assets/*, see below)
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
X-Frame-Options: DENY
Content-Security-Policy: default-src 'self'; connect-src 'self' ipc: http://ipc.localhost;
                         img-src 'self' data:; style-src 'self' 'unsafe-inline';
                         script-src 'self' 'unsafe-inline'; object-src 'none';
                         base-uri 'self'; frame-ancestors 'none'; form-action 'self'
```

`default-src 'self'` is the rule that bites: a CDN `<script>`, a web font,
an off-origin stylesheet is refused by the browser with nothing but a
console message — no dialog, no error page, a feature that silently is not
there. Bring dependencies in:

- AssetMapper: `bin/console importmap:require <package>` vendors the
  package and serves it from the app's own origin.
- Encore or your own build: bundle everything into the build output.

The `symfony/webapp` recipe ends `templates/base.html.twig` with a
FrankenPHP hot-reload block holding two CDN `<script>` tags. It renders
nothing under the hub (the hub never sets `FRANKENPHP_HOT_RELOAD`) but it
is still off-origin markup to lint tooling; remove it.

`/assets/*` is the exception to the override rule: every response under it
is sent `Cache-Control: public, max-age=31536000, immutable`, whatever the
app set — AssetMapper and Encore both serve content-hashed filenames
there, so a change is a different URL. Serving non-hashed content under
`/assets/` is the one way to get this wrong.

`'unsafe-inline'` stays in `script-src` and `style-src` because
AssetMapper renders its importmap inline — the policy is containment, not
XSS prevention: an injected script can still run, but it cannot reach the
network, read another origin, or navigate the top frame. An app wanting
nonce- or hash-based hardening emits its own policy, per the override rule
above. One subtlety: no directive here gates device capture — the
microphone is a separate authorization ([microphone.md](microphone.md)).

## External links open in the browser

A navigation the app's own window makes to its own origin happens in the
window; anything off-origin (`https://…`) is handed to the system browser
instead — the window never navigates away from the app. A plain
`<a href="https://…">` therefore behaves like a "open in browser" action.
Non-HTTP schemes are refused outright.

## Where `invoke` comes from

The webview's IPC channel is reached through Tauri's global — no package
to install, no build step:

```js
const invoke = window.__TAURI__?.core?.invoke;
```

It returns a promise that resolves to the command's answer, or rejects
with the command's error code when the command itself refused, or with
Tauri's own message when the group is not declared and the call never
reached the handler. Outside the hub — a plain browser on the same port,
a test runner — the global is `undefined`: treat every IPC capability as
unavailable. The same global carries the window API the
[open-files](open-files.md) receiver listens on:
`window.__TAURI__.webviewWindow.getCurrentWebviewWindow()`.

## Loops the webview cannot close

- **Same-host requests are not authenticated by the loopback.** Any local
  process can reach `http://127.0.0.1:<port>`; Symfony's CSRF protection
  on state-changing routes is the standard answer.

The other behaviours once listed here belong to the webview platform
itself — text antialiasing, missing scroll anchoring, paste and
drag-in delivering no file — and are collected in
[webview.md](webview.md).
