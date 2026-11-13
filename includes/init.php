<?php
/**
 * init.php
 * Bootstrap file — include this at the top of every page.
 * Starts the session and loads config, db connection, and helpers.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
