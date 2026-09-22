# signlab_signcollect-lib
One place for the estate's database credentials and install root (`/web`).

## What it does
- `db_config.php`: reads the host's `.env` and returns the DB settings.
- `paths.php`: resolves the install root and paths below it.
- `compat/`: shims for older credential conventions. `consumer/sc_paths.php` / `sc_paths.py`: the path resolvers that other repos vendor.
- It is a set of PHP files that other code `require_once`s. It serves no URL; `.htaccess` denies everything.

## Where it runs
`<root>/lib` on every host: `/web/lib` (production core server, dev2), `/srv/signcollect/web/lib` (dev-1).

## Status
Production. The migration onto it is still in progress, so every consumer also works without it.

## How to run / deploy
No build step and no Composer; the deploy runs no install step. The stack's `repos.tsv` deploys it as `lib` from `main`, listed first so it is on disk before its consumers. See
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack).

## Configuration
- `<root>/.env` (mode 640, deploy user : `www-data`, gitignored, never served). Template: `.env.example`.
- Override its location with the `SC_ENV_FILE` constant or env var (tests, checkouts outside `/web`).

## Dependencies
None. It is the bottom of the stack.

## API
```php
require_once '/web/lib/db_config.php';
$cfg = sc_db_config();   // ['host','user','password','database']
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['database']);
```
| Function | Returns |
|---|---|
| `sc_db_config()` | the 4 connection settings; throws `RuntimeException` if `.env` is missing/incomplete |
| `sc_env()` | the whole env file as `key => value` |
| `sc_env_file()` | the path being read (for health checks) |

There is deliberately no connection helper.

```php
require_once '/web/lib/paths.php';
sc_path('uploads', 'lsm')         // '/web/uploads/lsm'
sc_dir('media_raw')               // '/web/gebarenoverleg_media/studioFilesMini/raw/'
sc_url('/web/zin/eaf/zin/x.srt')  // '/zin/eaf/zin/x.srt'
```
| Function | Returns |
|---|---|
| `sc_root()` | install root, no trailing slash |
| `sc_path(...$parts)` | absolute path below the root, no trailing slash |
| `sc_dir(...$parts)` | same, with one trailing slash |
| `sc_url($diskPath)` | browser URL for a file below the root, `''` otherwise |
| `sc_locations()` | the 4 named media directories (`gebarenoverleg_media`, `studioFilesMini/raw`, `studioFilesMini/post`, `gebarenoverleg_media/fbx`) |

Ask for a location by its path below the root (`sc_path('signbank_data')`); only the 4 names above are registered. `paths.php` never throws.

**Root resolution**, first hit wins:
1. `SC_WEB_ROOT` constant
2. `SC_WEB_ROOT` environment variable
3. `SC_WEB_ROOT=` in the env file
4. parent of this directory, if it is named `lib`
5. `/web`

## Compatibility shims
- `consumer/sc_paths.php`, vendored into consumers. It loads the first of `__DIR__/lib`, `../lib`, `../../lib` or `/web/lib` `paths.php`. If none exists, it defines the same five path functions using the `/web` default (no env file). Edit it here and re-copy; never edit a copy.
  Copies: signCollect-v2, zin, annotation-editors, hh, studio_beta, viconDashboard, sC-Animation-PP, blendAnims, mocap, mocapStudio, mocap_lab, and the stack's `interface_deploy/web_extra/`. All are identical to this one.
  **Exception:** `signlab_sCAPI/sc_paths.php` has drifted (it lacks the `__DIR__/lib` candidate). sCAPI is not in `repos.tsv`, so the stack's `path-test.sh`, which checksums only deployed copies, does not catch it.
- `consumer/sc_paths.py`, the Python twin: `sc_root()`, `sc_path()`, `sc_dir()`, same 4 names, and `sc_setting(key, default)`. Root: `SC_WEB_ROOT` env, then `SC_WEB_ROOT=` in `$SC_ENV_FILE` or `/web/.env`, then `/web`. Vendored beside the scripts that import it; edit it here and re-copy.
  Copies: mocap, pythonCron, viconSync, zin, mocapDataPackage.
- Also vendored (PHP) into the non-deployed s3b_server, s3b_glb, s3b_viewer, client_monitor_dashboard, mocapDataPackage.
- `compat/mysql_config.php` sets `$servername`, `$username`, `$password`, `$database`. Point `<root>/mysql_config.php` at it.
- `compat/db_credentials.php` defines `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PASSWORD` (used by signlab_hh).
