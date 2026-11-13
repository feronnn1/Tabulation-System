<?php
/**
 * get_score_details.php
 *
 * Returns a per-judge, per-contestant breakdown of raw scores (one value
 * per criteria), the resulting weighted total, and that judge's comment
 * for that contestant - optionally filtered down to one contestant and/or
 * one judge. This is the data behind the admin "Scoring Details" page,
 * which lets an admin inspect exactly what any individual judge gave
 * any individual contestant, rather than only the aggregated leaderboard.
 */
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

$eventId = (int)($_GET['event_id'] ?? 0);
$contestantFilter = (int)($_GET['contestant_id'] ?? 0); // 0 = all contestants
$judgeFilter = (int)($_GET['judge_id'] ?? 0);            // 0 = all judges

if (!$eventId) {
    jsonResponse(['success' => false, 'message' => 'Invalid event id.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
}
$maxScore = (float)$event['max_score'];

// Filter option lists (always unfiltered, so the dropdowns stay complete).
$stmt = $pdo->prepare("SELECT contestant_id, contestant_number, full_name FROM contestants WHERE event_id = ? ORDER BY contestant_number ASC");
$stmt->execute([$eventId]);
$allContestants = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT judge_id, judge_name FROM judges WHERE event_id = ? ORDER BY judge_name ASC");
$stmt->execute([$eventId]);
$allJudges = $stmt->fetchAll();

// Build the filtered score rows.
$sql = "
    SELECT
        s.judge_id, j.judge_name,
        s.contestant_id, c.contestant_number, c.full_name AS contestant_name,
        s.criteria_id, cr.criteria_name, cr.weight_percentage, cr.display_order,
        s.raw_score, s.updated_at
    FROM scores s
    JOIN judges j ON j.judge_id = s.judge_id
    JOIN contestants c ON c.contestant_id = s.contestant_id
    JOIN criteria cr ON cr.criteria_id = s.criteria_id
    WHERE s.event_id = :event_id1
";
$params = ['event_id1' => $eventId];

if ($contestantFilter) {
    $sql .= " AND s.contestant_id = :contestant_id";
    $params['contestant_id'] = $contestantFilter;
}
if ($judgeFilter) {
    $sql .= " AND s.judge_id = :judge_id";
    $params['judge_id'] = $judgeFilter;
}
$sql .= " ORDER BY c.contestant_number ASC, j.judge_name ASC, cr.display_order ASC, cr.criteria_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Pull matching comments too (same filters).
$remarkSql = "
    SELECT r.judge_id, r.contestant_id, r.comment, r.updated_at
    FROM remarks r
    WHERE r.event_id = :event_id2 AND r.comment IS NOT NULL AND r.comment != ''
";
$remarkParams = ['event_id2' => $eventId];
if ($contestantFilter) {
    $remarkSql .= " AND r.contestant_id = :contestant_id";
    $remarkParams['contestant_id'] = $contestantFilter;
}
if ($judgeFilter) {
    $remarkSql .= " AND r.judge_id = :judge_id";
    $remarkParams['judge_id'] = $judgeFilter;
}
$stmt = $pdo->prepare($remarkSql);
$stmt->execute($remarkParams);
$remarksMap = [];
foreach ($stmt->fetchAll() as $r) {
    $remarksMap[$r['judge_id'] . '-' . $r['contestant_id']] = $r['comment'];
}

// Pull lock status for the same filtered set, so the admin can see which
// judge-contestant pairs are confirmed/locked and when.
$lockSql = "SELECT judge_id, contestant_id, confirmed_at FROM score_locks WHERE event_id = :event_id3";
$lockParams = ['event_id3' => $eventId];
if ($contestantFilter) {
    $lockSql .= " AND contestant_id = :contestant_id";
    $lockParams['contestant_id'] = $contestantFilter;
}
if ($judgeFilter) {
    $lockSql .= " AND judge_id = :judge_id";
    $lockParams['judge_id'] = $judgeFilter;
}
$stmt = $pdo->prepare($lockSql);
$stmt->execute($lockParams);
$locksMap = [];
foreach ($stmt->fetchAll() as $l) {
    $locksMap[$l['judge_id'] . '-' . $l['contestant_id']] = $l['confirmed_at'];
}

// Group rows into one entry per (judge, contestant) pair.
$entries = [];
foreach ($rows as $row) {
    $key = $row['judge_id'] . '-' . $row['contestant_id'];
    if (!isset($entries[$key])) {
        $entries[$key] = [
            'judge_id' => (int)$row['judge_id'],
            'judge_name' => $row['judge_name'],
            'contestant_id' => (int)$row['contestant_id'],
            'contestant_number' => (int)$row['contestant_number'],
            'contestant_name' => $row['contestant_name'],
            'criteria_scores' => [],
            'weighted_total' => 0.0,
            'comment' => $remarksMap[$key] ?? null,
            'locked' => isset($locksMap[$key]),
            'confirmed_at' => $locksMap[$key] ?? null,
            'updated_at' => $row['updated_at']
        ];
    }
    $weightedContribution = ($row['raw_score'] / $maxScore) * $row['weight_percentage'];
    $entries[$key]['criteria_scores'][] = [
        'criteria_name' => $row['criteria_name'],
        'weight_percentage' => (float)$row['weight_percentage'],
        'raw_score' => (float)$row['raw_score'],
        'weighted_contribution' => round($weightedContribution, 2)
    ];
    $entries[$key]['weighted_total'] += $weightedContribution;
    if ($row['updated_at'] > $entries[$key]['updated_at']) {
        $entries[$key]['updated_at'] = $row['updated_at'];
    }
}

$entries = array_values($entries);
foreach ($entries as &$e) {
    $e['weighted_total'] = round($e['weighted_total'], 2);
}
unset($e);

// Sort entries by contestant number, then judge name, for a stable display order.
usort($entries, function ($a, $b) {
    return $a['contestant_number'] <=> $b['contestant_number']
        ?: strcmp($a['judge_name'], $b['judge_name']);
});

jsonResponse([
    'success' => true,
    'max_score' => $maxScore,
    'contestants' => $allContestants,
    'judges' => $allJudges,
    'entries' => $entries
]);
