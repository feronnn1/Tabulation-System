<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$eventId = (int)($input['event_id'] ?? 0);
$status = $input['status'] ?? '';

$allowedStatuses = ['upcoming', 'ongoing', 'completed'];
if (!$eventId || !in_array($status, $allowedStatuses, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid event or status.'], 422);
}

$stmt = $pdo->prepare("UPDATE events SET status = ? WHERE event_id = ?");
$stmt->execute([$status, $eventId]);

jsonResponse(['success' => true, 'message' => 'Event status updated.']);
