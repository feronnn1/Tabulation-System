<?php
/**
 * unlock_score.php
 * Admin override: removes the confirmation lock for one (judge, contestant)
 * pair, letting that judge edit their score/comment again. Used to correct
 * a mistaken confirmation without any database access.
 */
require_once __DIR__ . '/../../includes/init.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = readJsonBody();
$judgeId = (int)($input['judge_id'] ?? 0);
$contestantId = (int)($input['contestant_id'] ?? 0);

if (!$judgeId || !$contestantId) {
    jsonResponse(['success' => false, 'message' => 'Invalid judge or contestant.'], 422);
}

$stmt = $pdo->prepare("DELETE FROM score_locks WHERE judge_id = ? AND contestant_id = ?");
$stmt->execute([$judgeId, $contestantId]);

jsonResponse(['success' => true, 'message' => 'Unlocked. This judge can edit that contestant again.']);
