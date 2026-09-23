# signlab_signcollect-lib
One place for the database credentials and the install root (`/web`) of every SignCollect host.

## What it does
- `db_config.php` reads the host's `.env` and returns the DB settings.
- `paths.php` finds the install root and paths below it.
- `compat/` holds shims for older credential conventions.
- `consumer/sc_paths.php` and `consumer/sc_paths.py` are the path resolvers that other repos copy in.
- Other code loads these files with `require_once`. The repo serves no URL; `.htaccess` denies everything.

## Where it runs
`<root>/lib` on every host: `/web/lib` (core server, dev2) and `/srv/signcollect/web/lib` (dev-1).

## Status
Production. Not every repo uses it yet, so every consumer also works without it.

## How to run / deploy
There is no build step, no Composer and no install step. The stack deploys `main` as `lib` (`repos.tsv`).
It is listed first, so it is on disk before the repos that use it. See
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack).

## Configuration
- `<root>/.env`: mode 640, owner deploy user, group `www-data`. Gitignored and never served. Template: `.env.example`.
- Set the `SC_ENV_FILE` constant or env var to read another file (tests, checkouts outside `/web`).

## Dependencies
None. Everything else builds on this repo.

## API
```php
require_once '/web/lib/db_config.php';
$cfg = sc_db_config();   // ['host','user','password','database']
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['database']);

require_once '/web/lib/paths.php';
sc_path('uploads', 'lsm')         // '/web/uploads/lsm'
sc_dir('media_raw')               // '/web/gebarenoverleg_media/studioFilesMini/raw/'
sc_url('/web/zin/eaf/zin/x.srt')  // '/zin/eaf/zin/x.srt'
```
| Function | Returns |
|---|---|
| `sc_db_config()` | the 4 connection settings. Throws `RuntimeException` if `.env` is missing or incomplete. There is no connection helper, on purpose |
| `sc_env()`, `sc_env_file()` | the whole env file as `key => value`; the path it reads (for health checks) |
| `sc_root()` | install root, no trailing slash |
| `sc_path(...$parts)`, `sc_dir(...$parts)` | absolute path below the root; `sc_dir` adds one trailing slash |
| `sc_url($diskPath)` | browser URL for a file below the root, `''` otherwise |
| `sc_locations()` | the 4 named media directories: `gebarenoverleg_media`, `studioFilesMini/raw`, `studioFilesMini/post`, `gebarenoverleg_media/fbx` |

Only those 4 names are registered. Ask for anything else by its path below the root (`sc_path('signbank_data')`). `paths.php` never throws.

The root is the first of: the `SC_WEB_ROOT` constant, the `SC_WEB_ROOT` env var, `SC_WEB_ROOT=` in the env file, the parent of this directory if it is named `lib`, and `/web`.

## Compatibility shims
- `consumer/sc_paths.php` loads the first `paths.php` it finds in `__DIR__/lib`, `../lib`, `../../lib` or `/web/lib`. If there is none, it defines the same five path functions with the `/web` default. Edit it here and copy it out again. Never edit a copy.
  Copies: signCollect-v2, zinnen-annotation, patient-info, camera-control, crop-fix-manager, annotation-tool, viconDashboard, mocap-postprocessing, blendbaking, mocap, mocapStudio (root and `lab/`) and the stack's `interface_deploy/web_extra/`. All match this one.
  Exception: `signlab_signCollect-API-TYD/sc_paths.php` has drifted; it lacks the `__DIR__/lib` candidate. The stack deploys it at `zin/api/`, and `path-test.sh` only checks copies one level below the root, so the test misses it.
- `consumer/sc_paths.py` is the Python twin: `sc_root()`, `sc_path()`, `sc_dir()`, the same 4 names, `sc_env()`, `sc_env_file()` and `sc_setting(key, default)`. Root: `SC_WEB_ROOT` env var, then `SC_WEB_ROOT=` in `$SC_ENV_FILE` or `/web/.env`, then `/web`. Copies: mocap, pythonCron, viconSync, zinnen-annotation, mocapDataPackage.
- The PHP resolver is also copied into sam3d-body-queue (root and `viewer/`), body-animation-viewer, s3b_viewer, client_monitor_dashboard, mocapDataPackage and mocap_lab, which the stack does not deploy.
- `compat/mysql_config.php` sets `$servername`, `$username`, `$password` and `$database`. Point `<root>/mysql_config.php` at it.
- `compat/db_credentials.php` defines `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` and `DB_PASSWORD` (used by signlab_patient-info).
