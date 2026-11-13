<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$contestantId = (int)($input['contestant_id'] ?? 0);

if (!$contestantId) {
    jsonResponse(['success' => false, 'message' => 'Invalid contestant id.'], 422);
}

$stmt = $pdo->prepare("DELETE FROM contestants WHERE contestant_id = ?");
$stmt->execute([$contestantId]);

jsonResponse(['success' => true, 'message' => 'Contestant removed.']);
