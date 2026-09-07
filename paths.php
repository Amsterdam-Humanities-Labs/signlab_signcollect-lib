<?php
/**
 * SignCollect install-root resolver.
 *
 * The estate is nailed to /web. Two hundred-odd string literals across
 * thirteen repositories name it, and every one of them is a promise that the
 * stack will never be installed anywhere else - not in a container, not in a
 * home directory, not twice on one host. This file is where that promise gets
 * made once instead of two hundred times.
 *
 *   require_once '/web/lib/paths.php';
 *
 *   sc_root()                              // '/web'
 *   sc_path('signbank_data')               // '/web/signbank_data'
 *   sc_path('uploads', 'lsm')              // '/web/uploads/lsm'
 *   sc_path('mysql_config.php')            // '/web/mysql_config.php'
 *   sc_dir('media_raw')                    // '/web/gebarenoverleg_media/studioFilesMini/raw/'
 *   sc_url('/web/zin/eaf/zin/x.srt')       // '/zin/eaf/zin/x.srt'
 *
 * THE DEFAULT IS /web AND MUST STAY /web. This file is a change of shape, not
 * of behaviour: on a host that sets nothing, every call above returns the
 * exact byte sequence the literal it replaced contained. That is what makes
 * the migration verifiable - the test suites are supposed to notice nothing.
 *
 * ## What a caller asks for
 *
 * A location, not a string, but "a location" is spelled as its own path below
 * the root: sc_path('signbank_data'), sc_path('zin/eaf/zin'). There is no
 * registry of every directory in the estate to keep in step with reality,
 * because the thing that is about to move is the root, and everything under
 * it moves with it by construction.
 *
 * The exception is the handful of names in sc_locations(), which exist for
 * directories whose spelling on disk is an accident rather than a fact -
 * "gebarenoverleg_media", "studioFilesMini". Those are worth a name. A
 * directory whose name is already obvious is not, and adding one would only
 * create a second place to look something up.
 *
 * ## Where the root comes from
 *
 * In order, first hit wins:
 *
 *   1. the SC_WEB_ROOT constant, if the caller defined one before including
 *   2. the SC_WEB_ROOT environment variable (SetEnv, or a CLI export)
 *   3. SC_WEB_ROOT= in the env file sc_env_file() names - normally /web/.env
 *   4. the parent of this directory, when this library is installed under the
 *      name "lib". /web/lib means the root is /web, by definition; a checkout
 *      at /srv/site/lib means the root is /srv/site, with nothing configured
 *   5. the compiled default, /web
 *
 * (1) and (2) mirror sc_env_file() exactly, so there is one idiom to learn.
 * (3) puts the setting next to the database credentials, in the one file a
 * host already has to write. (4) is the same trick sc_env_file() uses to find
 * a .env beside itself: it makes an install somewhere else work with no
 * configuration at all, and it cannot change the answer on a host where the
 * library is at /web/lib. (5) is the promise that this file changes nothing.
 *
 * ## When this library is absent
 *
 * Several consumers also deploy to production, which has no /web/lib. They
 * reach the resolver through a vendored consumer/sc_paths.php, which prefers
 * this file and falls back to defining the same functions from the compiled
 * default. A host without the library therefore keeps resolving to /web -
 * which is where it was resolving before, hardcoded.
 */

if (!function_exists('sc_path')) {

require_once __DIR__ . '/db_config.php';   // sc_env(), sc_env_file()

/**
 * Directories whose name on disk is worth hiding behind a better one.
 *
 * Values are relative to the root and must stay relative; an absolute value
 * here would reintroduce the literal this file exists to remove. Kept short
 * on purpose - see the note above about not building a registry.
 *
 * @return array<string,string>
 */
function sc_locations(): array
{
    return [
        // The media tree. Roughly sixty call sites spell it out in full, at
        // least one of them misspells it, and parts of it are an rclone mount
        // and a symlink onto /mnt/bigstorage - so it is both the most-typed
        // name in the estate and the one most likely to move on its own.
        'media'      => 'gebarenoverleg_media',

        // studioFilesMini is the downscaled tree; studioFiles is the
        // full-resolution one. The difference is four characters in the
        // middle of a long path, and getting it wrong reads the wrong videos
        // rather than failing.
        'media_raw'  => 'gebarenoverleg_media/studioFilesMini/raw',
        'media_post' => 'gebarenoverleg_media/studioFilesMini/post',

        // Motion-capture exports: mocapFiles.php, getZinnen.php, the mocap
        // repo and animMIDI all reach into this one.
        'media_fbx'  => 'gebarenoverleg_media/fbx',
    ];
}

/**
 * The install root: the directory the whole stack lives under. No trailing
 * slash, so sc_root() . '/x' is always right.
 *
 * Resolved once per request. Nothing here throws: a missing or unreadable
 * env file is not a reason for a path lookup to fail, because the answer
 * without it is the compiled default, which is the answer every caller
 * hardcoded before this file existed.
 */
function sc_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $candidate = '';

    if (defined('SC_WEB_ROOT') && SC_WEB_ROOT !== '') {
        $candidate = (string) SC_WEB_ROOT;
    }
    if ($candidate === '') {
        $fromEnv = getenv('SC_WEB_ROOT');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidate = $fromEnv;
        }
    }
    if ($candidate === '') {
        try {
            $vars = sc_env();
            if (isset($vars['SC_WEB_ROOT']) && $vars['SC_WEB_ROOT'] !== '') {
                $candidate = $vars['SC_WEB_ROOT'];
            }
        } catch (RuntimeException $e) {
            // No env file is not a path error. Fall through.
        }
    }
    if ($candidate === '' && basename(__DIR__) === 'lib') {
        $candidate = dirname(__DIR__);
    }
    if ($candidate === '') {
        $candidate = '/web';
    }

    // An install root must be absolute; a relative one would resolve against
    // whatever directory a cron job happened to start in.
    if ($candidate[0] !== '/') {
        error_log("SignCollect config: SC_WEB_ROOT '$candidate' is not absolute; using /web");
        $candidate = '/web';
    }

    return $root = ($candidate === '/' ? '' : rtrim($candidate, '/'));
}

/**
 * An absolute path below the install root, with no trailing slash.
 *
 * The first segment is looked up in sc_locations() when it is a bare name -
 * no slash, no dot - and used as-is otherwise, so 'media_raw' resolves
 * through the table while 'zin/eaf/zin' and 'mysql_config.php' do not.
 * Remaining segments are joined verbatim; they are filenames, not names.
 *
 * sc_path() with no arguments is sc_root().
 */
function sc_path(string ...$parts): string
{
    if ($parts) {
        $first = $parts[0];
        if (strpos($first, '/') === false && strpos($first, '.') === false) {
            $locations = sc_locations();
            if (isset($locations[$first])) {
                $parts[0] = $locations[$first];
            }
        }
    }

    $path = sc_root();
    foreach ($parts as $part) {
        $part = trim($part, '/');
        if ($part !== '' && $part !== '.') {
            $path .= '/' . $part;
        }
    }
    return $path;
}

/**
 * sc_path(), with exactly one trailing slash.
 *
 * Most of the call sites this replaces wrote their literal with a trailing
 * slash and then concatenated a filename onto it. Giving them a function that
 * keeps the slash makes each of those edits a substitution rather than a
 * rewrite - and a rewrite is where an off-by-one slash gets introduced.
 */
function sc_dir(string ...$parts): string
{
    return sc_path(...$parts) . '/';
}

/**
 * The browser URL for a file on disk below the root, or '' if it is not
 * below the root and therefore cannot be served.
 *
 * The docroot is the root, so this is a prefix strip - which is what the call
 * sites already did, with str_replace('/web/', '/', $file) and friends. Those
 * were the second half of the same hardcoding: a path built from a literal
 * root and then unbuilt with the same literal. Both halves move together.
 */
function sc_url(string $diskPath): string
{
    $root = sc_root();
    if ($root === '') {
        return $diskPath;
    }
    if (strpos($diskPath, $root . '/') === 0) {
        return substr($diskPath, strlen($root));
    }
    return $diskPath === $root ? '/' : '';
}

}
