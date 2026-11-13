<?php
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$eventId = (int)($input['event_id'] ?? 0);
$rows = $input['criteria'] ?? [];

if (!$eventId || !is_array($rows) || empty($rows)) {
    jsonResponse(['success' => false, 'message' => 'At least one criteria row is required.'], 422);
}

// Block edits once the event has left "upcoming" status, since scores
// already reference these criteria weights.
$stmt = $pdo->prepare("SELECT status FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}
if ($event['status'] !== 'upcoming') {
    jsonResponse(['success' => false, 'message' => 'Criteria are locked because this event is no longer "upcoming".'], 403);
}

$total = 0;
$clean = [];
foreach ($rows as $i => $row) {
    $name = cleanInput($row['criteria_name'] ?? '');
    $weight = (float)($row['weight_percentage'] ?? 0);

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Every criteria needs a name.'], 422);
    }
    if ($weight < 0 || $weight > 100) {
        jsonResponse(['success' => false, 'message' => 'Weights must be between 0 and 100.'], 422);
    }
    $total += $weight;
    $clean[] = ['name' => $name, 'weight' => $weight, 'order' => $i];
}

// Allow a tiny floating-point tolerance
if (abs($total - 100) > 0.01) {
    jsonResponse([
        'success' => false,
        'message' => "Weights must total exactly 100%. Current total: " . number_format($total, 2) . "%"
    ], 422);
}

try {
    $pdo->beginTransaction();

    // Existing criteria + their scores are removed and replaced fresh.
    // Safe here because we already blocked this unless status = upcoming
    // (meaning no real scoring has happened yet).
    $del = $pdo->prepare("DELETE FROM criteria WHERE event_id = ?");
    $del->execute([$eventId]);

    $ins = $pdo->prepare("INSERT INTO criteria (event_id, criteria_name, weight_percentage, display_order) VALUES (?, ?, ?, ?)");
    foreach ($clean as $row) {
        $ins->execute([$eventId, $row['name'], $row['weight'], $row['order']]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Failed to save criteria: ' . $e->getMessage()], 500);
}

jsonResponse(['success' => true, 'message' => 'Criteria saved successfully.', 'total' => $total]);
