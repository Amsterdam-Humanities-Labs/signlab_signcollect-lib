<?php
/**
 * SignCollect shared database configuration.
 *
 * One function, one job: tell a caller how to reach the database. The
 * credentials are NOT in this file and never will be - they are read at
 * runtime from an env file that is gitignored, never deployed, and denied by
 * apache. This library is the thing that knows where that file is and how to
 * read it; nothing else in the estate needs to.
 *
 *   require_once '/web/lib/db_config.php';
 *   $cfg  = sc_db_config();                 // host / user / password / database
 *   $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['database']);
 *
 * The env file is looked for in this order:
 *
 *   1. the SC_ENV_FILE constant, if the caller defined one before including
 *   2. the SC_ENV_FILE environment variable (SetEnv, or a CLI export)
 *   3. <parent of this directory>/.env  - /web/.env for the deployed /web/lib
 *   4. /web/.env
 *
 * (3) before (4) so a checkout can be exercised outside /web without
 * patching anything, and (4) so a copy of this library installed somewhere
 * other than the docroot still finds the docroot's file.
 *
 * Callers that cannot be changed keep working through compat/: see
 * compat/mysql_config.php (bare $servername/$username/$password/$database)
 * and compat/db_credentials.php (the DB_* constants signlab_patient-info uses). Those
 * are shims over this function, not second sources of truth.
 */

if (!function_exists('sc_db_config')) {

/**
 * Path of the env file this process will read. Exposed because a health
 * check that cannot say *which* file it failed to read is not much of a
 * health check.
 */
function sc_env_file(): string
{
    if (defined('SC_ENV_FILE') && SC_ENV_FILE !== '') {
        return (string) SC_ENV_FILE;
    }
    $fromEnv = getenv('SC_ENV_FILE');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }
    $beside = dirname(__DIR__) . '/.env';
    if (is_file($beside)) {
        return $beside;
    }
    return '/web/.env';
}

/**
 * The whole env file as key => value.
 *
 * Parsed once per request. The format is the deliberately boring subset
 * everything already writes: KEY=value per line, # comments, optional
 * surrounding quotes. No interpolation, no `export`, no multiline - a config
 * file that needs a parser with a grammar is a config file that will
 * eventually be parsed two different ways, which is the problem this library
 * exists to end.
 *
 * @throws RuntimeException when the file is missing or unreadable.
 */
function sc_env(): array
{
    static $cache = null;
    static $cachedFrom = null;

    $file = sc_env_file();
    if ($cache !== null && $cachedFrom === $file) {
        return $cache;
    }
    if (!is_readable($file)) {
        throw new RuntimeException("SignCollect config: $file is missing or unreadable");
    }

    $vars = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        $vars[trim($k)] = trim(trim($v), "\"'");
    }

    $cache = $vars;
    $cachedFrom = $file;
    return $vars;
}

/**
 * Connection settings for the SignCollect database.
 *
 * The key names are the ones the existing call sites already use in their
 * hand-rolled arrays - host / user / password / database - so migrating one
 * is a deletion rather than a rewrite:
 *
 *   -  $db_config = ['host' => 'localhost', 'user' => 'user', ...];
 *   +  $db_config = sc_db_config();
 *
 * @return array{host:string,user:string,password:string,database:string}
 * @throws RuntimeException when the env file is unreadable or incomplete.
 */
function sc_db_config(): array
{
    $env = sc_env();

    // DB_PASS may legitimately be empty (socket auth); the other three may
    // not, and an empty one of those produces a connection error that reads
    // like a database fault rather than a config fault.
    $missing = [];
    foreach (['DB_HOST', 'DB_USER', 'DB_NAME'] as $key) {
        if (!isset($env[$key]) || $env[$key] === '') {
            $missing[] = $key;
        }
    }
    if ($missing) {
        throw new RuntimeException(
            'SignCollect config: ' . implode(', ', $missing) . ' not set in ' . sc_env_file()
        );
    }

    return [
        'host'     => $env['DB_HOST'],
        'user'     => $env['DB_USER'],
        'password' => $env['DB_PASS'] ?? '',
        'database' => $env['DB_NAME'],
    ];
}

/**
 * Turn a config failure into the response the old per-repo credential files
 * produced: 500, a message that names nothing secret, and a line in the PHP
 * error log naming the file that could not be read. The compat shims use it
 * so a caller that never handled a missing config behaves exactly as before.
 */
function sc_config_fail(RuntimeException $e): void
{
    error_log($e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    die('Configuration error: the database configuration is missing or incomplete.');
}

}
