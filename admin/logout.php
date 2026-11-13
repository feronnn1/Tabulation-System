<?php
require_once __DIR__ . '/../includes/init.php';
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
session_destroy();
header('Location: login.php');
exit;
