# Realtime: Mercure

A Mercure hub is mounted at `/.well-known/mercure` **on the app's own
origin**, always, whether or not the app declares a worker — so
`MERCURE_URL` and `MERCURE_PUBLIC_URL` are the same loopback origin, and
no CORS, origin or certificate decision ever reaches the app. Publishing
goes through `MERCURE_URL`, signed with `MERCURE_JWT_SECRET` — a fresh
random value every launch, never persisted, provided by the hub. With
`symfony/mercure-bundle` installed, the recipe's `mercure.yaml` reads
exactly these three variables and nothing needs editing under the hub.

The part the app owns is the **subscriber side**, and it has one hard
rule.

## Every subscription requires a subscriber JWT

Because loopback is not user-restricted — any local process can reach
`http://127.0.0.1:<port>` — **every subscription requires a valid
subscriber JWT**; there is no anonymous fallback, and a request carrying
none gets `401`. All three of the protocol's transports are accepted:

| Transport | Accepted |
| --- | --- |
| `Authorization: Bearer <jwt>` header | yes |
| `mercureAuthorization` cookie | yes |
| `?authorization=<jwt>` query parameter | yes |

The cookie is the recommended default, for two reasons that follow from
the setup rather than from taste: app and hub share an origin by
construction here — exactly the condition a cookie needs — and the
browser's native `EventSource` cannot set request headers at all. The
query parameter works but puts a bearer token into URLs, hence into logs
and history — a last resort.

## Minting the cookie, and `withCredentials`

Choosing the cookie means minting it per topic and passing
`withCredentials`, which defaults to `false` per spec:

```php
$authorization->setCookie($request, ['https://example.com/some-topic']);
```

```js
new EventSource(url, {withCredentials: true});
```

`$authorization` is `Symfony\Component\Mercure\Authorization`, from
`symfony/mercure-bundle`. Call `setCookie()` from a controller that
renders the page that will subscribe — it adds the `mercureAuthorization`
cookie to that response.

Nothing checks that an app did this. One that does not gets a hub that
silently never delivers that topic: no error, no dialog, an
`EventSource` that simply never receives anything. As defence in depth,
prefer unguessable per-session topic names over predictable ones like
`/user/1` — not a substitute for the JWT, but it raises the cost of a
blind guess.

## Outside the hub

`setCookie()` derives the cookie's domain from the hub's public URL and
the current request's host, and throws when the two share no second-level
domain. Here they always match. A stock Flex project elsewhere still
holds the recipe's `https://example.com/.well-known/mercure` placeholder
and throws on every request that mints a cookie — the fix is
configuration, a `MERCURE_PUBLIC_URL` that matches how each environment
is served, not a runtime check for whether a host is present.

## Streams and the two-second close

Closing the app stops its server gracefully, and **two seconds** is how
long a request in flight gets before the connection is closed under it.
A Mercure subscription is a stream that never drains — it is the reason
the bound exists, and it means work that must outlive a window close
never belongs in a stream or a long-poll: it belongs in a
[worker](workers.md). A request that finishes within two seconds is
unaffected; the ordinary close is clean.
