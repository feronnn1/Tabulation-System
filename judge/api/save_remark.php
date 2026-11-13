<?php
/**
 * save_remark.php
 * Upserts one judge's free-text comment for a contestant.
 * Mirrors the same (judge_id, contestant_id) upsert pattern as scores.
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
$comment = trim((string)($input['comment'] ?? ''));

if (!$contestantId) {
    jsonResponse(['success' => false, 'message' => 'Invalid contestant.'], 422);
}
if (strlen($comment) > 2000) {
    jsonResponse(['success' => false, 'message' => 'Comment is too long (2000 characters max).'], 422);
}

// Verify event is still open and the contestant belongs to this judge's event.
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

if (isContestantLocked($pdo, $judgeId, $contestantId)) {
    jsonResponse(['success' => false, 'message' => 'You already confirmed this contestant - your comment can no longer be changed.', 'locked' => true], 403);
}

// Sanitize for storage (plain text comment; escaped again on output wherever it's shown)
$cleanComment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

$stmt = $pdo->prepare("
    INSERT INTO remarks (event_id, judge_id, contestant_id, comment)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$eventId, $judgeId, $contestantId, $cleanComment]);

jsonResponse(['success' => true, 'message' => 'Comment saved.']);
