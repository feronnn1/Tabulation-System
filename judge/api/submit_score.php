<?php
/**
 * submit_score.php
 * Called via Fetch API every time a judge moves a slider.
 * Upserts one (judge, contestant, criteria) score row.
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
$criteriaId = (int)($input['criteria_id'] ?? 0);
$rawScore = (float)($input['raw_score'] ?? -1);

if (!$contestantId || !$criteriaId || $rawScore < 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid score data.'], 422);
}

// Verify the event is still open for scoring.
$stmt = $pdo->prepare("SELECT status, max_score FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}
if ($event['status'] === 'completed') {
    jsonResponse(['success' => false, 'message' => 'This event has concluded. Scoring is closed.'], 403);
}
if ($rawScore > (float)$event['max_score']) {
    jsonResponse(['success' => false, 'message' => 'Score exceeds the maximum allowed (' . $event['max_score'] . ').'], 422);
}

// Verify the contestant and criteria actually belong to this judge's event
// (prevents a tampered request from writing scores into another event).
$check = $pdo->prepare("SELECT COUNT(*) FROM contestants WHERE contestant_id = ? AND event_id = ?");
$check->execute([$contestantId, $eventId]);
if (!$check->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => 'Contestant does not belong to this event.'], 403);
}

$check = $pdo->prepare("SELECT COUNT(*) FROM criteria WHERE criteria_id = ? AND event_id = ?");
$check->execute([$criteriaId, $eventId]);
if (!$check->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => 'Criteria does not belong to this event.'], 403);
}

if (isContestantLocked($pdo, $judgeId, $contestantId)) {
    jsonResponse(['success' => false, 'message' => 'You already confirmed your scores for this contestant - they are locked and cannot be changed.', 'locked' => true], 403);
}

$stmt = $pdo->prepare("
    INSERT INTO scores (event_id, judge_id, contestant_id, criteria_id, raw_score)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE raw_score = VALUES(raw_score), updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$eventId, $judgeId, $contestantId, $criteriaId, $rawScore]);

jsonResponse(['success' => true, 'message' => 'Score saved.']);
