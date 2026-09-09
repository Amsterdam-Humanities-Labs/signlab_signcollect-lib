# signcollect-lib

The SignCollect estate's database credentials, and the directory it is
installed in, each in one place.

## Where it runs

Everywhere the rest of the estate runs, as `<root>/lib`: the **signcollect
core server** (production VPS) at `/web/lib`, dev2 at `/web/lib`, dev-1 at
`/srv/signcollect/web/lib`. It is not a page and serves no URL — it is two PHP
files other components `require_once`.

`.htaccess` denies everything: nothing in here is ever served to a browser.

## Status

**Production**, and new — the migration onto it is still in progress, which is
why every consumer still works without it (see *Compatibility shims*).

## How to deploy it

Not Composer, and no build step. Deployment is by
`interface_deploy/scripts/repos.tsv` in `signlab_signcollect-stack`, which maps
`lib` → this repo on branch `main`; `host-bootstrap.sh` clones it onto the host
like every other component. It is listed **first** in that file so it is on
disk before the repos that include it.

## Dependencies

None. It is the bottom of the stack — it requires no other repo and no service.
What it needs is one file the host supplies, `<root>/.env`; see *Where the
credentials actually are*.

Twelve repositories currently carry a vendored copy of `consumer/sc_paths.php`
and go through it: `signlab_signCollect-v2`, `signlab_zin`,
`signlab_annotation-editors`, `signlab_hh`, `signlab_sCAPI`,
`signlab_studio_beta`, `signlab_viconDashboard`, `signlab_sC-Animation-PP`,
`signlab_blendAnims`, `signlab_mocap`, `signlab_mocapStudio` and
`signlab_mocap_lab`. Everything else reaches the credentials through the
`compat/` shims.

## Why it exists

Before this, roughly 170 PHP files reached the database through some spelling
of `mysql_config.php` (21 of them, at four different relative depths, plus 24
hardcoded `/web/mysql_config.php` paths), and four mutually incompatible
credential conventions coexisted: bare globals, a `$db_config` array, `DB_*`
constants, and a config file that returns an array. `signlab_hh` used two of
them *inside the same repository*, for the same database, and they had
drifted apart far enough that one of the two could no longer connect.

The same story is true of the filesystem. 232 string literals across thirteen
repositories spell `/web` - the media tree, the uploads directory, the
Signbank dump, the credential file - and each one is a promise that the stack
will never be installed anywhere else. `paths.php` is where that promise is
now made once.

This library is the single source for both. It is small on purpose: it is a
place for the two facts every consumer needs - who the database is, and where
the disk is - to live, not a framework.

## API

```php
require_once '/web/lib/db_config.php';

$cfg = sc_db_config();
// ['host' => …, 'user' => …, 'password' => …, 'database' => …]

$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['database']);
```

Three functions, and only the first is normally interesting:

| function | returns |
| --- | --- |
| `sc_db_config()` | the four connection settings, as an array |
| `sc_env()` | the whole env file as `key => value`, for callers that need a non-database setting |
| `sc_env_file()` | the path being read, so a health check can say which file it could not open |

There is no connection helper. Every consumer already builds its own `mysqli`
with its own error handling, and a fourth way to open a connection is exactly
the kind of thing this library exists to remove.

### Paths

```php
require_once '/web/lib/paths.php';

sc_root()                          // '/web'
sc_path('signbank_data')           // '/web/signbank_data'
sc_path('uploads', 'lsm')          // '/web/uploads/lsm'
sc_path('mysql_config.php')        // '/web/mysql_config.php'
sc_dir('media_raw')                // '/web/gebarenoverleg_media/studioFilesMini/raw/'
sc_url('/web/zin/eaf/zin/x.srt')   // '/zin/eaf/zin/x.srt'
```

| function | returns |
| --- | --- |
| `sc_root()` | the install root, no trailing slash |
| `sc_path(...$parts)` | an absolute path below the root, no trailing slash |
| `sc_dir(...$parts)` | the same, with exactly one trailing slash |
| `sc_url($diskPath)` | the browser URL for a file below the root, `''` if it is not below it |
| `sc_locations()` | the few named directories, as name => path relative to the root |

**A caller asks for a location, and a location is spelled as its own path
below the root.** `sc_path('signbank_data')`, `sc_path('zin/eaf/zin')`. There
is deliberately no registry of every directory in the estate: the thing that
is about to move is the root, and everything under it moves with it by
construction. A registry would only be a second place to keep in step with
the disk, and it would be wrong within a month.

`sc_locations()` is the exception, and it is four entries long. It names the
directories whose spelling on disk is an accident rather than a fact -
`gebarenoverleg_media`, `studioFilesMini/raw`, `studioFilesMini/post`,
`gebarenoverleg_media/fbx`. Sixty-odd call sites type the first of those out
in full, one of them misspells it, and part of that tree is an rclone mount
onto `/mnt/bigstorage`, so it is also the one directory likely to move
independently of the root. A directory whose name is already obvious does not
get an entry.

`sc_dir()` exists because most of the literals being replaced ended in a
slash and had a filename concatenated onto them. Keeping the slash makes each
of those edits a substitution instead of a rewrite, and a rewrite is where an
off-by-one slash gets introduced.

`sc_url()` is the other half of the same hardcoding. Several call sites built
a path from a literal `/web` and then unbuilt it with
`str_replace('/web/', '/', $file)` to hand the browser a URL. Both halves have
to move together or the page 404s.

Nothing in `paths.php` throws. A missing env file is not a reason for a path
lookup to fail, because the answer without it is the compiled default - which
is the answer every caller hardcoded before this file existed.

### Where the root comes from

In order, first hit wins:

1. the `SC_WEB_ROOT` constant, if the caller defined one before including
2. the `SC_WEB_ROOT` environment variable (`SetEnv`, or a CLI export)
3. `SC_WEB_ROOT=` in the env file, normally `/web/.env`
4. the parent of the library's own directory, when it is installed under the
   name `lib` - `/web/lib` means the root is `/web`, by definition, and a
   checkout at `/srv/site/lib` means `/srv/site`, with nothing configured
5. the compiled default, `/web`

(1) and (2) mirror `SC_ENV_FILE` exactly, so there is one idiom to learn. (3)
puts the setting in the one file a host already has to write. (4) is the
trick `sc_env_file()` already uses to find a `.env` beside itself; it cannot
change the answer on a host where the library is at `/web/lib`. (5) is the
promise that adding this file changed nothing: on a host that sets none of
the above, every call returns the exact byte sequence the literal it replaced
contained.

`sc_db_config()` throws `RuntimeException` when the env file is missing or
incomplete. Callers that never handled that case go through the compat shims,
which turn it into the 500 the old credential files produced.

## Where the credentials actually are

`/web/.env`, mode 640, owned by the deploy user and group `www-data`. It is
gitignored, excluded from every rsync, and denied by apache. `.env.example`
in this repo is the template; it contains placeholders and nothing else.

Override the location with the `SC_ENV_FILE` constant or environment variable
— useful for tests, and for exercising a checkout outside `/web`.

## Compatibility shims

### `consumer/sc_paths.php`

Several consumers also deploy to production, which has no `/web/lib`. They
include a vendored, byte-identical copy of `consumer/sc_paths.php`, which
looks for `../lib/paths.php`, `../../lib/paths.php` and `/web/lib/paths.php` -
the same three-step search `hh/db_config.php` and `studio_beta/db.php`
already use for credentials - and, failing all three, defines the same four
functions from the compiled default.

A host without this library therefore keeps resolving to `/web`: exactly
where it was resolving before, when the paths were literals. A missing
library must not turn a path lookup into a 500, because the path was never in
doubt - that is the difference between this shim and the credential ones,
which have nothing sensible to fall back to and correctly fail loudly.

The fallback is the smaller half of the API on purpose: no env file, no
`SC_WEB_ROOT` from anywhere but the process environment. A host that wants to
move the root installs the library; a host that has not moved it needs none
of that machinery.

The copies are byte-identical, and `signlab_signcollect-stack`'s
`interface_deploy/tests/path-test.sh`
checksums the deployed ones against each other. Edit the one in this
repository and re-copy; never edit a copy.

### Credentials

Two more, in `compat/`, for call sites that are not worth touching:

- `compat/mysql_config.php` — sets `$servername`, `$username`, `$password`,
  `$database`. A host migrates the ~170 bare-globals callers by pointing
  `/web/mysql_config.php` at this file; not one of them changes.
- `compat/db_credentials.php` — defines `DB_HOST`, `DB_USER`, `DB_PASS`,
  `DB_NAME` and `DB_PASSWORD`, which is `signlab_hh`'s two conventions
  reconciled onto one source.

## Why not Composer

There is no build step anywhere in the deploy — `host-bootstrap.sh` clones each
component straight into its docroot directory and that is the whole of it. The
one repo in the estate that already has a `composer.json`, `signlab_sC-Animation-PP`,
ships without its `vendor/` because that directory is gitignored. A Composer
dependency here would therefore be a deploy that silently 500s until someone
remembers to run an install that no script performs. So this repo is a
component like any other, in the same pass as everything else.

Consumers include it by path. `<root>/lib/db_config.php` is the deployed
location; a consumer one level below the docroot can also resolve it
relatively as `__DIR__ . '/../lib/db_config.php'`.
