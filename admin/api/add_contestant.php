<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$eventId = (int)($input['event_id'] ?? 0);
$number = (int)($input['contestant_number'] ?? 0);
$name = cleanInput($input['full_name'] ?? '');

if (!$eventId || !$number || $name === '') {
    jsonResponse(['success' => false, 'message' => 'All fields are required.'], 422);
}

// Confirm event exists
$check = $pdo->prepare("SELECT COUNT(*) FROM events WHERE event_id = ?");
$check->execute([$eventId]);
if (!$check->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}

try {
    $stmt = $pdo->prepare("INSERT INTO contestants (event_id, contestant_number, full_name) VALUES (?, ?, ?)");
    $stmt->execute([$eventId, $number, $name]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonResponse(['success' => false, 'message' => 'That contestant number is already taken for this event.'], 409);
    }
    throw $e;
}

jsonResponse([
    'success' => true,
    'message' => 'Contestant added.',
    'contestant' => [
        'contestant_id' => $pdo->lastInsertId(),
        'contestant_number' => $number,
        'full_name' => $name
    ]
]);
