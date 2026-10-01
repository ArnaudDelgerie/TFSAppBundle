# Publishing a release

A release is the only thing the hub installs. It consists of
`<project_name>-<app_version>.tar.gz` plus a matching `SHA256SUMS.txt`
beside it — on a forge, or in a local folder — and `tfsapp-hub publish
path/to/project` automates building it from the committed tree. An app
that never publishes one stays a project the hub can only run in
[dev](dev.md), for as long as its author wants.

## The checklist before every release

1. **Commit everything.** A release archive is built from the tree that is
   committed — whatever is committed is what the hub installs. `publish`
   refuses a dirty tree; for a forge release the project must also be
   pushed, and the release lands on the repository the branch's upstream
   remote names.
2. **Bump `app_version`.** Canonical `MAJOR.MINOR.PATCH` — no leading
   zeros, no suffix, no `v` — checked on the author's machine before
   anything is built or uploaded. The hub compares this value to decide
   whether a source is an install, an update, or a downgrade to refuse
   ([lifecycle.md](lifecycle.md)).
3. **Add a `## <version>` entry to `CHANGELOG.md`** naming what changed.
   `publish` refuses without it. `tfsapp:init` wrote the first one.

Nothing else: there are no build hooks and nothing runs on the author's
machine beyond reading the tree. The archive holds the permitted
Git-tracked source at the pushed commit — no `vendor/`, `var/`,
`node_modules/` or `.git/` — plus one exception: the directories declared
under `build_outputs`, embedded **as they stand at publish time**,
gitignored in the project and covered by the same checksum
([frontend.md](frontend.md)). `publish` refuses a declared path that is
absent or empty, holds a tracked file, is not gitignored, or escapes the
project — compile your assets first.

## The `secrets.ipc` confirmation

When the manifest turns on `actions.secrets.ipc`, `publish` stops and
says what that means: the declared secrets become reachable from the
app's own JavaScript, so an XSS in the app can read or overwrite them
([secrets.md](secrets.md)). It waits for an explicit **yes at a
terminal** — `--yes` does not answer it, and a run with no terminal is
refused. The release ships that setting to everyone who installs it, so
its author confirms it in person, every release.

## `publish --local <dir>`: a release without a forge

```sh
mkdir -p out
tfsapp-hub publish path/to/project --local out/
```

writes `out/<project_name>-<app_version>/` holding the archive,
`SHA256SUMS.txt` and `NOTES.md`. The destination directory must exist,
and the release folder must not already exist. This mode keeps every gate
above — clean committed tree, canonical version, changelog entry,
`secrets.ipc` confirmation — and needs `git` installed, but no upstream,
no remote, no `gh` and no unused forge tag. Installing from it:

```sh
tfsapp-hub install out/<name>-<version>/<name>-<version>.tar.gz
```

The checksum is checked before extraction, including for a local
archive: the archive alone cannot be installed.

## Publishing to a forge

```sh
tfsapp-hub publish path/to/project                # the project's own git remote
tfsapp-hub publish path/to/project --repo owner/repo
```

Forge publishing needs `git` installed and `gh` installed and
authenticated on the **author's own machine**, and the project committed
and pushed — `publish` proves it rather than trusting an asserted tag.
The release lands as: a tag `v<app_version>` (`--ref` selects a release
this way, never a branch or a commit), an asset named
`<project_name>-<app_version>.tar.gz`, and the `SHA256SUMS.txt` beside
it. Anyone can then install it with:

```sh
tfsapp-hub install github:owner/repo
```

A plain `git:` source is recognised and refused with a message saying
so, rather than pretended.

## What a release does not steer

The manifest never names where an app's updates come from:
`update <id>` resolves what the hub's own registry recorded at install
or the last update, and `publish` reads its repository from `--repo` or
the project's git remote — never from the manifest. A stale
`releases_repo` key in an old manifest is inert. An app *asks* about its
updates through the [update check](update-check.md); how one is applied
belongs to the host.
