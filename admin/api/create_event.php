<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
if (empty($input)) {
    $input = $_POST; // fallback for regular form posts
}

$eventName = cleanInput($input['event_name'] ?? '');
$description = cleanInput($input['description'] ?? '');
$maxScore = (int)($input['max_score'] ?? 100);

if ($eventName === '') {
    jsonResponse(['success' => false, 'message' => 'Event name is required.'], 422);
}
if ($maxScore < 1) {
    jsonResponse(['success' => false, 'message' => 'Max score must be at least 1.'], 422);
}

$stmt = $pdo->prepare("INSERT INTO events (event_name, description, max_score, status) VALUES (?, ?, ?, 'upcoming')");
$stmt->execute([$eventName, $description, $maxScore]);

jsonResponse([
    'success' => true,
    'message' => 'Event created successfully.',
    'event_id' => $pdo->lastInsertId()
]);
