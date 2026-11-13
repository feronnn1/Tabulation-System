<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$eventId = (int)($input['event_id'] ?? 0);

if (!$eventId) {
    jsonResponse(['success' => false, 'message' => 'Invalid event id.'], 422);
}

$stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);

jsonResponse(['success' => true, 'message' => 'Event deleted.']);
