<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$judgeId = (int)($input['judge_id'] ?? 0);

if (!$judgeId) {
    jsonResponse(['success' => false, 'message' => 'Invalid judge id.'], 422);
}

$stmt = $pdo->prepare("DELETE FROM judges WHERE judge_id = ?");
$stmt->execute([$judgeId]);

jsonResponse(['success' => true, 'message' => 'Judge removed.']);
