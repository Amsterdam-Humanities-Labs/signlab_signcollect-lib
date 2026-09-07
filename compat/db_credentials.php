<?php
/**
 * Compatibility shim for signlab_hh, which spells its credentials two
 * different ways inside one repository:
 *
 *   $db_config = ['host' => 'localhost', 'password' => DB_PASSWORD, ...]
 *       api.php, getGlosses.php, get_begrippen.php - only the password came
 *       from the credential file; host, user and database were hardcoded,
 *       and the hardcoded user ("user") is not the user any host actually
 *       has, so this shape could only ever have worked by accident.
 *
 *   DB_HOST / DB_USER / DB_PASS / DB_NAME constants
 *       save_subtitle.php, which redefines all four from DB_PASSWORD.
 *
 * Both shapes are defined here from the one source, so the two accessors
 * reach the same database. That drift is the actual bug this replaces.
 *
 * HH_API_TOKEN is deliberately not defined here: it is a per-host shared
 * secret for segment_api.php, not a database credential, and hh/auth.php
 * already degrades safely when it is absent. A host that carries a real
 * hh/db_credentials.php keeps getting the token from it.
 */
require_once __DIR__ . '/../db_config.php';

$sc_db = ['host' => '', 'user' => '', 'password' => '', 'database' => ''];
try {
    $sc_db = sc_db_config();
} catch (RuntimeException $e) {
    sc_config_fail($e);
}

defined('DB_HOST')     || define('DB_HOST',     $sc_db['host']);
defined('DB_USER')     || define('DB_USER',     $sc_db['user']);
defined('DB_PASS')     || define('DB_PASS',     $sc_db['password']);
defined('DB_PASSWORD') || define('DB_PASSWORD', $sc_db['password']);
defined('DB_NAME')     || define('DB_NAME',     $sc_db['database']);
