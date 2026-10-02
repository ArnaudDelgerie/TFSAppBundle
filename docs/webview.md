# The webview: WebKitGTK, not Chromium

The hub's window is WebKitGTK through Tauri. That is the trade the whole
stack rests on: lighter than bundling a Chromium, and less complete,
with gaps that can affect rendering quality. This page states them in
general terms and collects the ones verified on this stack, with their
mechanism and the way out; nothing here is a guess at a cause. It is
the reference for "why does it look/behave different from Chrome".

## Text renders greyscale everywhere

Every window the hub creates renders text with greyscale antialiasing
rather than subpixel, for the whole app — GTK chrome included — and
there is no option. The reason: WebKitGTK renders subpixel antialiasing
only on the root layer. A scrolling, composited column
(`overflow-y: auto`) that overflows gets promoted to a non-root
composited layer, where rendering falls back to greyscale, and Skia's
mask-gamma preblend — designed to correct per channel against an LCD
mask — lands "in the nearest gray instead of the nearest colour"
against a greyscale mask: dark text visibly fattens the moment a column
overflows. Measured on one machine: **+16.1%** ink per text pixel
between a column below the fold and the same column scrolled, down to
**+0.2%** (noise) once the whole app renders greyscale uniformly —
which is why the hub forces it everywhere instead of leaving the
mid-screen switch to be met as a bug.

The cost is real, not zero: subpixel antialiasing is a genuine
horizontal-resolution gain around 96 dpi, and this removes it from
every app, including ones that never showed the defect. On HiDPI it is
a non-event. The ecosystem took the same direction anyway — GTK4
dropped subpixel text rendering outright, and macOS has shipped
greyscale-only since Mojave.

## No scroll anchoring

WebKitGTK has no `overflow-anchor`. A library that relies on CSS scroll
anchoring to keep its content in place during data inserts (virtual
lists, chat logs) cannot: it compensates on its own — adjusting the
scroll position in its own code — and can visibly shift the page doing
so.

## Paste and drag-in deliver no file

A pasted image or a drag from the file manager does not produce a `File`
object in this webview:

- a **paste** carries only a `blob:` `<img>`, which the hub's CSP
  blocks (`img-src 'self' data:` — `blob:` is not covered, see
  [frontend.md](frontend.md)), and `clipboardData.files` is empty;
- a **drop** inserts the `file:///…` path as text, and nothing else.

The way out is one of the two paths that do work: `<input type="file">`
for uploads — the browser's own dialog, a real `File` object — or the
native [picker](picker.md) when a local path is what the app actually
wants.

## Microphone codecs are the platform's

`MediaRecorder` and the `mimeType`s it accepts depend on the WebKitGTK
build and the GStreamer encoders on the machine — the detail is
[microphone.md](microphone.md)'s; nothing here restates it.

## A blank or broken window: is it the GPU?

If a window opens blank or renders broken, set
`WEBKIT_DISABLE_DMABUF_RENDERER=1` in the environment and retry: a
window that now works points at the graphics stack rather than the
app. The hub's own README documents the same check for the hub window.
