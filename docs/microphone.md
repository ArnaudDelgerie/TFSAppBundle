# Microphone

Lets the app's own page reach the microphone through the ordinary web
platform — `getUserMedia()` — rather than through `invoke()` or the
bridge. This group names a **device**, not a transport: `"ipc": true` or
`"bridge": true` spelled under `media` is refused at parse time, even
when set to `false`.

## Declare

```json
{ "actions": { "media": { "microphone": true } } }
```

Undeclared, the webview keeps its default: a platform with no capture at
all — `getUserMedia()` fails at the point of use, and there is no dialog
and nothing to retry. Declared, the app's own page may call
`getUserMedia({audio: true})` on its own origin and get a live audio
track, exactly as it would in a browser that had granted the permission.

Exactly **two** WebKit permission requests are ever allowed, both only
while `microphone` is declared and the requesting page is the app's own
origin:

- an **audio-only capture** request — `getUserMedia({audio: true})`;
- a **device-info** request — without it, `enumerateDevices()` returns
  no labels, and the app cannot let a person choose between a built-in
  microphone and a headset.

A combined audio+video or a video-only request is refused as a whole —
WebKit offers no partial grant — and every other permission type
(geolocation, notifications, pointer lock, and whatever WebKit adds
next) is denied unconditionally. Note that no CSP directive gates device
capture either ([frontend.md](frontend.md)): this manifest key is the
whole authorization.

## What the declaration means for the person

There is no runtime prompt and no indicator beyond the window's title
while a capture really runs. The declaration itself — readable before
install — is the whole of the consent story, which is why it is a
separate group with its own name. A user can revoke it per installation:
`"revoked": {"media": {"microphone": true}}` in the app's own
`data/config.json`, hand-edited and kept across updates, makes every
capture request denied, and `TFS_MEDIA_MICROPHONE` report `0`.

`TFS_MEDIA_MICROPHONE` reports the grant, never the hardware: `"1"` when
the manifest declares it **and** this launch installed the grant — a
machine with no microphone at all still reads `"1"` — and `"0"`
otherwise, including when the launch could not install it
([environment.md](environment.md)). Whether a device actually answers
is what `getUserMedia()` itself already fails on, the way it would on any
web page.

## Recording

The hub adds no codec, no format and no audio stack: whether
`MediaRecorder` works, and which `mimeType`s it accepts, depends on the
WebKitGTK build and the GStreamer encoders on the machine. An app that
wants to record must handle the constructor throwing
`NotSupportedError`:

```js
if (!MediaRecorder.isTypeSupported('audio/webm')) {
    // fall back rather than assume a working MediaRecorder
}
```

The fallback that does not depend on the platform's recorder at all:
feed the track into Web Audio (an `AudioWorkletNode`, not the deprecated
`ScriptProcessorNode`) and encode the raw samples yourself.

## Playing a recording back

A recording handed to an `<audio>`/`<video>` element as a `blob:` URL is
subject to `media-src`, which the hub's default response headers do not
set — so it falls back to `default-src 'self'`, and `'self'` does not
cover `blob:`. An app that plays a `blob:` recording back sets its own
`Content-Security-Policy` response header adding `media-src 'self'
blob:`, per the override rule in [frontend.md](frontend.md).
