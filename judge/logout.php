<?php
require_once __DIR__ . '/../includes/init.php';
unset($_SESSION['judge_id'], $_SESSION['judge_name'], $_SESSION['event_id'], $_SESSION['event_name']);
session_destroy();
header('Location: login.php');
exit;
