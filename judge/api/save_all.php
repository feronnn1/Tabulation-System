<?php
/**
 * save_all.php
 * Backs the explicit "Save" button on the judge scoring page.
 * Re-submits every criteria score for one contestant plus the
 * comment, all in a single transaction. This is a confirmation/
 * safety-net action on top of the slider's own autosave - it
 * guarantees everything currently on screen is persisted even if
 * an earlier autosave call was missed (e.g. a brief network drop).
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
$scores = $input['scores'] ?? [];
$comment = trim((string)($input['comment'] ?? ''));

if (!$contestantId || !is_array($scores) || empty($scores)) {
    jsonResponse(['success' => false, 'message' => 'Nothing to save.'], 422);
}
if (strlen($comment) > 2000) {
    jsonResponse(['success' => false, 'message' => 'Comment is too long (2000 characters max).'], 422);
}

$stmt = $pdo->prepare("SELECT status, max_score FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}
if ($event['status'] === 'completed') {
    jsonResponse(['success' => false, 'message' => 'This event has concluded. Scoring is closed.'], 403);
}

$check = $pdo->prepare("SELECT COUNT(*) FROM contestants WHERE contestant_id = ? AND event_id = ?");
$check->execute([$contestantId, $eventId]);
if (!$check->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => 'Contestant does not belong to this event.'], 403);
}

if (isContestantLocked($pdo, $judgeId, $contestantId)) {
    jsonResponse(['success' => false, 'message' => 'You already confirmed your scores for this contestant - they are locked and cannot be changed.', 'locked' => true], 403);
}

// Validate every criteria id belongs to this event before writing anything.
$validCriteriaStmt = $pdo->prepare("SELECT criteria_id FROM criteria WHERE event_id = ?");
$validCriteriaStmt->execute([$eventId]);
$validCriteriaIds = array_column($validCriteriaStmt->fetchAll(), 'criteria_id');
$validCriteriaIds = array_map('intval', $validCriteriaIds);

$clean = [];
foreach ($scores as $row) {
    $criteriaId = (int)($row['criteria_id'] ?? 0);
    $rawScore = (float)($row['raw_score'] ?? -1);

    if (!in_array($criteriaId, $validCriteriaIds, true)) {
        jsonResponse(['success' => false, 'message' => 'One of the criteria does not belong to this event.'], 403);
    }
    if ($rawScore < 0 || $rawScore > (float)$event['max_score']) {
        jsonResponse(['success' => false, 'message' => 'A score is out of the allowed range (0-' . $event['max_score'] . ').'], 422);
    }
    $clean[] = ['criteria_id' => $criteriaId, 'raw_score' => $rawScore];
}

try {
    $pdo->beginTransaction();

    $scoreStmt = $pdo->prepare("
        INSERT INTO scores (event_id, judge_id, contestant_id, criteria_id, raw_score)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE raw_score = VALUES(raw_score), updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($clean as $row) {
        $scoreStmt->execute([$eventId, $judgeId, $contestantId, $row['criteria_id'], $row['raw_score']]);
    }

    $cleanComment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
    $remarkStmt = $pdo->prepare("
        INSERT INTO remarks (event_id, judge_id, contestant_id, comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP
    ");
    $remarkStmt->execute([$eventId, $judgeId, $contestantId, $cleanComment]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()], 500);
}

jsonResponse(['success' => true, 'message' => 'All scores and comment saved.']);
