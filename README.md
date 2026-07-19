# TFSAppBundle

Symfony bundle that turns a Symfony app into a local-first desktop app (Tauri + FrankenPHP) — companion bundle to [TFSAppWorkstation](https://github.com/ArnaudDelgerie/TFSAppWorkstation).

## Status

Early stage, no release yet. **v0.1.0 scope**: relocate Symfony's cache/build/log directories to the paths the station's launcher provides (`APP_CACHE_DIR`/`APP_BUILD_DIR`/`APP_LOG_DIR`), and expose the `GET /healthz` route the launcher polls before opening its window. See TFSAppWorkstation's `CONTRACT.md` for the full station↔project contract this bundle implements.

## Usage

Not published yet. Once released: `composer require` this bundle into a Symfony project meant to run under TFSAppWorkstation.
