# Durable files

The project directory is read-only from the app's point of view, and
`public/` is replaced wholesale on an update. The one directory in the
data dir the app owns outright is `APP_UPLOAD_DIR` — never emptied at any
launch, not on update, not on rollback. Avatars, attachments, exports:
durable files go there, never `public/` or `var/`.

The bundle's `UploadStorageInterface` (service
`ArnaudDelgerie\TFSAppBundle\Storage\UploadStorageInterface`) is a thin,
confined wrapper over it:

| Method | Meaning |
| --- | --- |
| `root()` | the configured root — `APP_UPLOAD_DIR`, or `var/uploads` under the project when the variable is absent (`symfony server:start`, PHPUnit) |
| `has($key)` | whether a file is stored at the key |
| `path($key)` | the absolute path the key resolves to, whether or not a file exists there |
| `write($key, $contents)` | writes a string, creating intermediate directories |
| `store($key, $uploadedFile)` | moves an `UploadedFile` to the key, creating intermediate directories |
| `delete($key)` | removes the file; a no-op when nothing is stored at the key |
| `download($key, $filename = null)` | a `BinaryFileResponse` with `Content-Disposition: attachment` and `nosniff` |
| `inline($key, $filename = null)` | `Content-Disposition: inline`, `nosniff`, plus `Content-Security-Policy: default-src 'none'; sandbox` |

```php
#[Route('/invoices/{id}/file')]
public function file(Invoice $invoice, UploadStorageInterface $uploads): Response
{
    $this->denyAccessUnlessGranted('VIEW', $invoice);

    return $uploads->download($invoice->getFileKey(), $invoice->getOriginalName());
}
```

Writing is `$uploads->store('invoices/'.$invoice->getId().'.pdf',
$uploadedFile)`; the key, its uniqueness and any validation are the app's,
and no route ships here on purpose — a download endpoint is entirely
authorization policy.

## Keys and their refusals

A key is a relative path the caller chooses (`invoices/2026/42.pdf`).
Every method refuses one that would reach outside the root with
`PathOutsideStorageException`: absolute keys, empty or `.`/`..` segments,
null bytes, and symlinks whose target resolves outside — the check
follows the deepest existing ancestor's real path, so a symlink planted
inside the root does not pass either.

`download()` and `inline()` throw `StorageException` when nothing is
stored at the key rather than answering 404 — check `has()` first when a
missing file is a normal case for the route. They also refuse a filename
containing `/` or `\` with `\InvalidArgumentException`.

Why `inline()` carries its sandboxing CSP: the hub's page policy is
`default-src 'self'`, which permits exactly what the app itself serves —
so an uploaded file handed back inline without it is same-origin content
with the same reach as the app's own scripts. `download()`'s
`attachment` disposition is the default worth reaching for.

## What a download does in the hub's window

A `Content-Disposition: attachment` response is saved straight into the
OS download directory, under the name the response gives it — de-duplicated
if a file of that name is already there — with no Save-As prompt, and one
line per download in `hub.log`. An app that wants the person to choose the
destination instead pairs `save_path` ([picker.md](picker.md)) with a route
of its own that writes the file there — `UploadStorage` cannot write
outside its root.

## What export and import carry

`tfsapp-hub export <id> <path>` / `import <id> <path>` carry exactly two
things: **the database and `uploads/`**. Nothing else in the data
directory travels — not `cache/`, `build/`, `log/`, `sessions/`, not the
keyring fallback files, and not the destination's `APP_SECRET`: an
imported app keeps its own, so every session and remember-me token in the
imported database stops validating there. A login prompt right after an
import is expected. Declared [secrets](secrets.md) live in the
destination's own keyring and must be re-provisioned there.

`export` walks `uploads/` recursively and writes every regular file;
a symlink, socket or fifo is skipped and named. `import` replaces rather
than merges: a forced import moves the destination's database and
non-empty `uploads/` aside as named rescue copies before the staged files
take their places, and a failed import changes nothing. An archive older
than the installed app is migrated forward inside the import (the
installed version's `pre-update`/`post-update` run over the imported
database); a newer one is refused.

One asymmetry to design around: the update rollback anchor snapshots the
database and nothing else, so rolling back restores a database that may
no longer agree with what is on disk in `uploads/` — a row pointing at a
file a newer version renamed or removed. An app that reorganises its
files across a version bump handles that itself, in a `pre-update`
command ([lifecycle.md](lifecycle.md)). The guarantees are the
contract's [§5](https://github.com/ArnaudDelgerie/TFSAppHub/blob/main/contract/5-the-apps-own-state.md).
