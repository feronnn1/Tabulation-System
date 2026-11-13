<?php
/**
 * config.php
 * Global configuration constants.
 *
 * IMPORTANT: If you place this project in a folder other than
 * "tabulation_system" inside htdocs, update BASE_URL below to match.
 * Example: if your folder is C:\xampp\htdocs\my_pageant, set it to "/my_pageant".
 */
define('BASE_URL', '/tabulation_system');

// Error display: turn OFF in production, ON while developing locally.
error_reporting(E_ALL);
ini_set('display_errors', 1);
