<?php
/**
 * Compatibility shim: the four bare globals that ~170 call sites across the
 * estate get from an include of some spelling of mysql_config.php.
 *
 *   include '../mysql_config.php';   // -> $servername $username $password $database
 *
 * Those call sites are not worth rewriting one at a time, and rewriting them
 * is not what fixes anything. The duplication that hurts is the credentials
 * being *defined* in twenty places, not the variable names being read in a
 * hundred. So the names stay and the source of truth moves: a host migrates
 * by pointing its /web/mysql_config.php at this file - a symlink, or a
 * one-line include - and every caller keeps working untouched.
 */
require_once __DIR__ . '/../db_config.php';

$servername = $username = $password = $database = '';
try {
    $sc_cfg     = sc_db_config();
    $servername = $sc_cfg['host'];
    $username   = $sc_cfg['user'];
    $password   = $sc_cfg['password'];
    $database   = $sc_cfg['database'];
    unset($sc_cfg);
} catch (RuntimeException $e) {
    sc_config_fail($e);
}
