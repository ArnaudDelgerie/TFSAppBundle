# Picker: native file and directory choosers

Shows one native chooser owned by the calling app window. It is for
selecting an existing file or directory and keeping the local path, or
letting a person choose where a file the app is about to write should go.
**It is not the normal way to upload a file**: for an upload, prefer the
browser's `<input type="file">`, which provides a `File` object and owns
the upload flow — a paste or a drag from the file manager delivers no
file in this webview, which is why the picker exists at all.

## Declare

```json
{ "actions": { "picker": { "ipc": true } } }
```

This group has one transport and one exact shape. There is no `bridge`
member — a manifest naming `actions.picker.bridge` is invalid — no HTTP
route, no PHP permission: PHP processes, workers and lifecycle commands
cannot open a chooser.

## Front: `pick_path` — which existing file or directory?

```js
invoke("pick_path", { kind: "file" })
invoke("pick_path", { kind: "directory" })
invoke("pick_path", { kind: "file", filters: [{ name: "Markdown", extensions: ["md"] }] })
```

| Parameter | Meaning |
| --- | --- |
| `kind` | required: `"file"` or `"directory"`. Any other value is invalid input and returns an IPC error |
| `filters` | optional, `kind: "file"` only: entries `{ name, extensions }`, each extension without a leading dot. Omitted, `null` or an empty list shows every file. For `kind: "directory"` well-formed filters are ignored — folders are not selected by extension — so one options object can be shared between both kinds |

A selection resolves to its **absolute path as a string**; cancellation
resolves successfully to `null`. Filters restrict what the chooser
displays; the selected file and its contents remain the app's own to
validate.

## Front: `save_path` — where should this new file go?

```js
invoke("save_path", {
  filters:   [{ name: "Markdown", extensions: ["md"] }, { name: "Text", extensions: ["txt"] }],
  fileName:  "notes.md",
  directory: "/home/…/Documents"
})
```

| Parameter | Meaning |
| --- | --- |
| `filters` | optional: one filter per entry, in order, the first one active |
| `fileName` | optional: pre-fills the suggested name |
| `directory` | optional: the starting directory — **must be an absolute path**; a relative one is invalid input and returns an IPC error |

`invoke("save_path", {})` opens a bare dialog. The result shape is
exactly `pick_path`'s: the chosen absolute path as a string, or `null` on
cancellation — including GTK's own "replace?" confirmation when the
typed name already exists.

## What a returned path is, and is not

**The hub never writes.** The path `save_path` returns may name a file
that does not exist yet — the app's own PHP creates it, and no filesystem
scope is granted: the dialog is the person's consent to that one
selection, not a permission. A route that writes to a returned path does
so under the app's own responsibility, exactly like any other route —
the hub cannot even tell that a path handed to a route came from this
dialog. `UploadStorageInterface` ([files.md](files.md)) cannot write
outside its root, so an app offering "save anywhere" writes those paths
itself. The path is returned exactly as typed: the filter list is a view
filter, not an enforced extension, and the hub does not append one — an
app that appends an extension itself is writing to a name GTK's dialog
never asked "replace?" about.

A saved path is machine-local configuration — never exportable
application state, never a promise that it survives another computer, an
OS migration, a missing mount, or a permission change. A path is also
privacy-relevant information: an app that sends it to its backend or
stores it owns that choice.
