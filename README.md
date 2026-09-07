# signcollect-lib

The SignCollect estate's database credentials, in one place.

Before this, roughly 170 PHP files reached the database through some spelling
of `mysql_config.php` (21 of them, at four different relative depths, plus 24
hardcoded `/web/mysql_config.php` paths), and four mutually incompatible
credential conventions coexisted: bare globals, a `$db_config` array, `DB_*`
constants, and a config file that returns an array. `signlab_hh` used two of
them *inside the same repository*, for the same database, and they had
drifted apart far enough that one of the two could no longer connect.

This library is the single source. It is small on purpose: it is a place for
credentials to live, not a framework.

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

Two, in `compat/`, for call sites that are not worth touching:

- `compat/mysql_config.php` — sets `$servername`, `$username`, `$password`,
  `$database`. A host migrates the ~170 bare-globals callers by pointing
  `/web/mysql_config.php` at this file; not one of them changes.
- `compat/db_credentials.php` — defines `DB_HOST`, `DB_USER`, `DB_PASS`,
  `DB_NAME` and `DB_PASSWORD`, which is `signlab_hh`'s two conventions
  reconciled onto one source.

## Deployment

Not Composer. The deploy is `rsync -a --delete` with no build step anywhere,
and the one repo in the estate that already has a `composer.json` ships
without its `vendor/` because that directory is gitignored — so a Composer
dependency would be a deploy that silently 500s until someone remembers to
run an install. Instead this repo is a component like any other: the demo
repo's `scripts/repos.tsv` maps it to `/web/lib`, `clone.sh` fetches it and
`deploy.sh` ships it, in the same pass as everything else.

Consumers include it by path. `/web/lib/db_config.php` is the deployed
location; a consumer one level below the docroot can also resolve it
relatively as `__DIR__ . '/../lib/db_config.php'`.
