# User directories

Lets the app read where the person's OS user directories are, without
hardcoding a guess. Like `media`, this group names a **resource**, not a
transport — there is nothing to `invoke()` and no bridge route, so
`"ipc"` or `"bridge"` spelled under `paths` is refused at parse time.

## Declare

```json
{
  "actions": {
    "paths": { "downloads": true, "pictures": true }
  }
}
```

The member set is fixed and matches GLib's eight special directories
exactly: `desktop`, `documents`, `downloads`, `music`, `pictures`,
`public_share`, `templates`, `videos`. `$HOME` is deliberately not a
ninth member — it already reaches PHP through the ordinary process
environment.

## What it buys

A declared member is resolved through GLib's own reading of
`~/.config/user-dirs.dirs` and reported as an environment variable —
`downloads` becomes `TFS_USER_DOWNLOADS_DIR`, `documents`
`TFS_USER_DOCUMENTS_DIR`, and so on. Read it in PHP the usual way — the variable is present only when the
member is declared **and** GLib resolves it, so absent means "ask the
person", never "assume `~/Downloads`":

```php
$downloads = $_SERVER['TFS_USER_DOWNLOADS_DIR'] ?? null;
```

**A declared member GLib cannot resolve reports as an absent variable** —
never an empty string, never a guessed `$HOME`-based path — so a missing
variable means "ask the person" or "fall back", never "assume
`~/Downloads`". The guess this removes is silent when it breaks: a
hardcoded `$HOME/Downloads` is simply wrong the moment
`~/.config/user-dirs.dirs` has moved the directory — a non-English
locale, a manually customised layout.

## What it does not buy

Declaring buys a legible line in the manifest, not a filesystem grant or
a sandbox boundary: PHP runs with the user's full rights regardless,
exactly like a `picker` path ([picker.md](picker.md)) or the microphone
([microphone.md](microphone.md)). The variable reports GLib's answer
as-is: a path GLib resolves that no longer exists, or is not writable,
is not caught here either.
