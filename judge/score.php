<?php
require_once __DIR__ . '/../includes/init.php';
requireJudgeLogin();

$judgeId = (int)$_SESSION['judge_id'];
$eventId = (int)$_SESSION['event_id'];

$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    die('Event not found.');
}

$stmt = $pdo->prepare("SELECT * FROM contestants WHERE event_id = ? ORDER BY contestant_number ASC");
$stmt->execute([$eventId]);
$contestants = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM criteria WHERE event_id = ? ORDER BY display_order ASC, criteria_id ASC");
$stmt->execute([$eventId]);
$criteria = $stmt->fetchAll();

// Preload this judge's existing scores so switching contestants needs no re-fetch.
$stmt = $pdo->prepare("SELECT contestant_id, criteria_id, raw_score FROM scores WHERE judge_id = ? AND event_id = ?");
$stmt->execute([$judgeId, $eventId]);
$existingScores = [];
foreach ($stmt->fetchAll() as $row) {
    $existingScores[$row['contestant_id']][$row['criteria_id']] = (float)$row['raw_score'];
}

// Preload this judge's existing comments too.
$stmt = $pdo->prepare("SELECT contestant_id, comment FROM remarks WHERE judge_id = ? AND event_id = ?");
$stmt->execute([$judgeId, $eventId]);
$existingRemarks = [];
foreach ($stmt->fetchAll() as $row) {
    $existingRemarks[$row['contestant_id']] = $row['comment'];
}

// Which contestants this judge has already confirmed (and therefore locked).
$stmt = $pdo->prepare("SELECT contestant_id FROM score_locks WHERE judge_id = ? AND event_id = ?");
$stmt->execute([$judgeId, $eventId]);
$lockedContestantIds = array_map('intval', array_column($stmt->fetchAll(), 'contestant_id'));

$eventLocked = ($event['status'] === 'completed');
$noCriteria = empty($criteria);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scoring &mdash; <?= htmlspecialchars($event['event_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="topbar glass">
    <div class="brand"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></span> <?= htmlspecialchars($_SESSION['judge_name']) ?></div>
    <div class="topbar-right">
        <span class="badge badge-<?= $event['status'] ?>"><?= $event['status'] ?></span>
        <a href="logout.php" class="btn btn-sm">Logout</a>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<div class="wrapper" style="max-width: 640px; padding-bottom: 110px;">
    <h1><?= htmlspecialchars($event['event_name']) ?></h1>
    <p>Tap a contestant, adjust the sliders, add a comment if you like &mdash; everything saves automatically, and you can hit Save any time for confirmation.</p>

    <?php if ($eventLocked): ?>
        <div class="alert alert-info">This event has concluded. Scores and comments are now read-only.</div>
    <?php elseif ($noCriteria): ?>
        <div class="alert alert-error">No criteria have been set up for this event yet. Please wait for the admin.</div>
    <?php elseif (empty($contestants)): ?>
        <div class="alert alert-error">No contestants have been added yet. Please wait for the admin.</div>
    <?php else: ?>

    <div class="flex-between" style="margin-bottom: 12px;">
        <span class="small muted" id="progressSummary">0 of <?= count($contestants) ?> contestants fully scored</span>
    </div>
    <div class="progress-track" style="margin-bottom:18px;">
        <div class="progress-fill" id="progressFill" style="width:0%;"></div>
    </div>

    <div class="contestant-chip-row" id="contestantChips">
        <?php foreach ($contestants as $i => $c): ?>
        <div class="contestant-chip <?= $i === 0 ? 'active' : '' ?> <?= in_array($c['contestant_id'], $lockedContestantIds, true) ? 'locked' : '' ?>" data-id="<?= $c['contestant_id'] ?>">
            #<?= $c['contestant_number'] ?> <?= htmlspecialchars($c['full_name']) ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="lockedBanner" class="alert alert-info" style="display:none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        You confirmed this contestant&rsquo;s score. It&rsquo;s locked and can no longer be changed.
    </div>

    <div class="glass-card live-total-card">
        <span class="muted small">Current weighted total</span>
        <span class="rank-score" id="liveTotalDisplay">0.00 <span class="muted small">/ 100</span></span>
    </div>

    <div id="criteriaContainer"></div>

    <div class="glass-card" style="margin-top: 16px;">
        <div class="flex-between">
            <label for="commentBox" style="margin:0;">Comments / Remarks <span class="muted">(optional)</span></label>
            <span class="small muted" id="commentCharCount">0 / 2000</span>
        </div>
        <textarea id="commentBox" rows="3" maxlength="2000"
                  placeholder="Any notes about this contestant's performance..."
                  <?= $eventLocked ? 'disabled' : '' ?>></textarea>
    </div>

    <div class="save-bar glass" id="saveBar">
        <div id="savingStatus" class="save-indicator-inline">All changes saved</div>
        <div class="flex-gap">
            <button type="button" class="btn btn-primary" id="saveAllBtn" <?= $eventLocked ? 'disabled' : '' ?>>Save</button>
            <button type="button" class="btn btn-success" id="confirmScoreBtn" <?= $eventLocked ? 'disabled' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Confirm Score
            </button>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Confirm Score warning modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box glass-card">
        <h2>Confirm this score?</h2>
        <p>Please review every slider and your comment carefully. <strong>Once confirmed, you will not be able to change this contestant's score or comment again.</strong> Make sure you're happy with it before continuing.</p>
        <div class="flex-gap" style="margin-top: 20px;">
            <button type="button" class="btn btn-success" id="confirmModalYesBtn">Yes, confirm &amp; lock</button>
            <button type="button" class="btn" id="confirmModalCancelBtn">Cancel, let me review</button>
        </div>
    </div>
</div>

<script>
    const JUDGE_ID = <?= $judgeId ?>;
    const EVENT_ID = <?= $eventId ?>;
    const MAX_SCORE = <?= (int)$event['max_score'] ?>;
    const EVENT_LOCKED = <?= $eventLocked ? 'true' : 'false' ?>;
    const BASE_URL = "<?= BASE_URL ?>";

    const CRITERIA = <?= json_encode(array_map(function ($c) {
        return [
            'criteria_id' => (int)$c['criteria_id'],
            'criteria_name' => $c['criteria_name'],
            'weight_percentage' => (float)$c['weight_percentage']
        ];
    }, $criteria)) ?>;

    const CONTESTANTS = <?= json_encode(array_map(function ($c) {
        return ['contestant_id' => (int)$c['contestant_id'], 'full_name' => $c['full_name']];
    }, $contestants)) ?>;

    // existingScores[contestant_id][criteria_id] = raw_score
    const existingScores = <?= json_encode($existingScores, JSON_FORCE_OBJECT) ?>;

    // existingRemarks[contestant_id] = "comment text"
    const existingRemarks = <?= json_encode($existingRemarks, JSON_FORCE_OBJECT) ?>;

    // contestant_ids this judge has already confirmed and locked
    const lockedContestantIds = <?= json_encode($lockedContestantIds) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/judge.js"></script>
</body>
</html>
