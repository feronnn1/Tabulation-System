<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$eventId = (int)($input['event_id'] ?? 0);
$judgeName = cleanInput($input['judge_name'] ?? '');

if (!$eventId || $judgeName === '') {
    jsonResponse(['success' => false, 'message' => 'Judge name is required.'], 422);
}

$check = $pdo->prepare("SELECT COUNT(*) FROM events WHERE event_id = ?");
$check->execute([$eventId]);
if (!$check->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}

$passcode = generatePasscode($pdo);

$stmt = $pdo->prepare("INSERT INTO judges (event_id, judge_name, passcode, is_active) VALUES (?, ?, ?, 1)");
$stmt->execute([$eventId, $judgeName, $passcode]);

jsonResponse([
    'success' => true,
    'message' => 'Judge created.',
    'judge' => [
        'judge_id' => $pdo->lastInsertId(),
        'judge_name' => $judgeName,
        'passcode' => $passcode,
        'is_active' => 1
    ]
]);
