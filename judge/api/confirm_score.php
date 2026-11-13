<?php
/**
 * confirm_score.php
 * Permanently locks this judge's scores + comment for one contestant.
 * Once locked, submit_score.php / save_remark.php / save_all.php all
 * refuse further changes for this (judge, contestant) pair - the lock
 * is enforced here server-side, not just hidden/disabled in the UI.
 */
require_once __DIR__ . '/../../includes/init.php';
requireJudgeLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$judgeId = (int)$_SESSION['judge_id'];
$eventId = (int)$_SESSION['event_id'];

$input = readJsonBody();
$contestantId = (int)($input['contestant_id'] ?? 0);

if (!$contestantId) {
    jsonResponse(['success' => false, 'message' => 'Invalid contestant.'], 422);
}

$stmt = $pdo->prepare("SELECT status FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}
if ($event['status'] === 'completed') {
    jsonResponse(['success' => false, 'message' => 'This event has concluded.'], 403);
}

$check = $pdo->prepare("SELECT COUNT(*) FROM contestants WHERE contestant_id = ? AND event_id = ?");
$check->execute([$contestantId, $eventId]);
if (!$check->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => 'Contestant does not belong to this event.'], 403);
}

// Already locked? Treat as a no-op success rather than an error.
$check = $pdo->prepare("SELECT COUNT(*) FROM score_locks WHERE judge_id = ? AND contestant_id = ?");
$check->execute([$judgeId, $contestantId]);
if ($check->fetchColumn() > 0) {
    jsonResponse(['success' => true, 'message' => 'Already confirmed.', 'already_locked' => true]);
}

// Require every criteria to have a score before allowing confirmation,
// so a judge can't accidentally lock in an incomplete scorecard.
$criteriaCountStmt = $pdo->prepare("SELECT COUNT(*) FROM criteria WHERE event_id = ?");
$criteriaCountStmt->execute([$eventId]);
$totalCriteria = (int)$criteriaCountStmt->fetchColumn();

$scoredCountStmt = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE judge_id = ? AND contestant_id = ?");
$scoredCountStmt->execute([$judgeId, $contestantId]);
$scoredCount = (int)$scoredCountStmt->fetchColumn();

if ($totalCriteria === 0) {
    jsonResponse(['success' => false, 'message' => 'No criteria are set up for this event yet.'], 422);
}
if ($scoredCount < $totalCriteria) {
    jsonResponse(['success' => false, 'message' => 'Please score every criteria before confirming.'], 422);
}

try {
    $stmt = $pdo->prepare("INSERT INTO score_locks (event_id, judge_id, contestant_id) VALUES (?, ?, ?)");
    $stmt->execute([$eventId, $judgeId, $contestantId]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        // Race condition: locked by a near-simultaneous request. Still a success state.
        jsonResponse(['success' => true, 'message' => 'Already confirmed.', 'already_locked' => true]);
    }
    throw $e;
}

jsonResponse(['success' => true, 'message' => 'Score confirmed and locked.']);
