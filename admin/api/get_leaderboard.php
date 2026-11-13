<?php
/**
 * get_leaderboard.php
 *
 * The math engine. For each contestant:
 *   1. For each judge who has scored them, sum across criteria:
 *        (raw_score / event.max_score) * criteria.weight_percentage
 *      -> this gives that judge's weighted total out of 100 for that contestant.
 *   2. Average that weighted total across every judge who actually scored
 *      the contestant (judges who haven't scored yet are not counted,
 *      so a late judge doesn't drag an early leader's average down to 0).
 *
 * Polled every few seconds by the leaderboard page for a near-real-time feed.
 */
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    jsonResponse(['success' => false, 'message' => 'Invalid event id.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}

$judgeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM judges WHERE event_id = ?");
$judgeCountStmt->execute([$eventId]);
$totalJudges = (int)$judgeCountStmt->fetchColumn();

$sql = "
    SELECT
        c.contestant_id,
        c.contestant_number,
        c.full_name,
        ROUND(AVG(jt.judge_weighted_total), 2) AS final_score,
        COUNT(DISTINCT jt.judge_id) AS judges_scored
    FROM contestants c
    LEFT JOIN (
        SELECT
            s.contestant_id,
            s.judge_id,
            SUM( (s.raw_score / e.max_score) * cr.weight_percentage ) AS judge_weighted_total
        FROM scores s
        JOIN criteria cr ON cr.criteria_id = s.criteria_id
        JOIN events e ON e.event_id = s.event_id
        WHERE s.event_id = :event_id1
        GROUP BY s.contestant_id, s.judge_id
    ) jt ON jt.contestant_id = c.contestant_id
    WHERE c.event_id = :event_id2
    GROUP BY c.contestant_id, c.contestant_number, c.full_name
    ORDER BY final_score DESC, c.contestant_number ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['event_id1' => $eventId, 'event_id2' => $eventId]);
$rows = $stmt->fetchAll();

// Pull every non-empty judge comment for this event, grouped by contestant,
// so the leaderboard can show them without a second round-trip.
$remarkStmt = $pdo->prepare("
    SELECT r.contestant_id, j.judge_name, r.comment, r.updated_at
    FROM remarks r
    JOIN judges j ON j.judge_id = r.judge_id
    WHERE r.event_id = ? AND r.comment IS NOT NULL AND r.comment != ''
    ORDER BY r.contestant_id ASC, j.judge_name ASC
");
$remarkStmt->execute([$eventId]);
$remarksByContestant = [];
foreach ($remarkStmt->fetchAll() as $rem) {
    $remarksByContestant[$rem['contestant_id']][] = [
        'judge_name' => $rem['judge_name'],
        'comment' => $rem['comment'],
        'updated_at' => $rem['updated_at']
    ];
}

$leaderboard = [];
$rank = 1;
foreach ($rows as $r) {
    $leaderboard[] = [
        'rank' => $rank++,
        'contestant_id' => (int)$r['contestant_id'],
        'contestant_number' => (int)$r['contestant_number'],
        'full_name' => $r['full_name'],
        'final_score' => $r['final_score'] !== null ? (float)$r['final_score'] : 0.0,
        'judges_scored' => (int)$r['judges_scored'],
        'total_judges' => $totalJudges,
        'comments' => $remarksByContestant[$r['contestant_id']] ?? []
    ];
}

jsonResponse([
    'success' => true,
    'event_status' => $event['status'],
    'total_judges' => $totalJudges,
    'leaderboard' => $leaderboard
]);
