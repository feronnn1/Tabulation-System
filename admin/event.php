<?php
require_once __DIR__ . '/../includes/init.php';
requireAdminLogin();

$eventId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    die('Event not found. <a href="dashboard.php">Back to dashboard</a>');
}

$stmt = $pdo->prepare("SELECT * FROM contestants WHERE event_id = ? ORDER BY contestant_number ASC");
$stmt->execute([$eventId]);
$contestants = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM criteria WHERE event_id = ? ORDER BY display_order ASC, criteria_id ASC");
$stmt->execute([$eventId]);
$criteria = $stmt->fetchAll();
$totalWeight = array_sum(array_column($criteria, 'weight_percentage'));

$stmt = $pdo->prepare("SELECT * FROM judges WHERE event_id = ? ORDER BY judge_id ASC");
$stmt->execute([$eventId]);
$judges = $stmt->fetchAll();

$criteriaLocked = ($event['status'] !== 'upcoming');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($event['event_name']) ?> &mdash; Manage Event</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="topbar glass">
    <div class="brand"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></span> Tabulation Admin</div>
    <div class="topbar-right">
        <a href="dashboard.php" class="btn btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Dashboard
        </a>
        <a href="logout.php" class="btn btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</div>

<div class="wrapper">
    <div class="flex-between" style="margin-bottom: 10px;">
        <div>
            <h1><?= htmlspecialchars($event['event_name']) ?></h1>
            <p>Max score per criteria: <?= $event['max_score'] ?> &middot; Status:
                <span class="badge badge-<?= $event['status'] ?>"><?= $event['status'] ?></span>
            </p>
        </div>
        <div class="flex-gap">
            <a href="scores.php?id=<?= $eventId ?>" class="btn btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Scoring Details
            </a>
            <a href="leaderboard.php?id=<?= $eventId ?>" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                View Leaderboard
            </a>
        </div>
    </div>

    <div id="alertBox"></div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="contestants">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Contestants (<?= count($contestants) ?>)
        </button>
        <button class="tab-btn" data-tab="criteria">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>
            Criteria (<?= count($criteria) ?>)
        </button>
        <button class="tab-btn" data-tab="judges">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Judges (<?= count($judges) ?>)
        </button>
    </div>

    <!-- ===================== CONTESTANTS TAB ===================== -->
    <div class="tab-panel active" id="tab-contestants">
        <div class="glass-card" style="margin-bottom: 18px;">
            <h3>Add Contestant</h3>
            <form id="addContestantForm" class="flex-gap" style="align-items:flex-end;">
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                <div style="flex:1; min-width:120px;">
                    <label for="contestant_number">Number</label>
                    <input type="number" id="contestant_number" name="contestant_number" min="1" required>
                </div>
                <div style="flex:3; min-width:200px;">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required placeholder="e.g. Juan Dela Cruz">
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add
                </button>
            </form>
        </div>

        <div class="glass-card">
            <table>
                <thead><tr><th>#</th><th>Name</th><th></th></tr></thead>
                <tbody id="contestantsTableBody">
                    <?php foreach ($contestants as $c): ?>
                    <tr data-id="<?= $c['contestant_id'] ?>">
                        <td>#<?= $c['contestant_number'] ?></td>
                        <td><?= htmlspecialchars($c['full_name']) ?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-sm btn-danger delete-contestant-btn" data-id="<?= $c['contestant_id'] ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                Remove
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($contestants)): ?>
                    <tr><td colspan="3" class="muted">No contestants yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== CRITERIA TAB ===================== -->
    <div class="tab-panel" id="tab-criteria">
        <?php if ($criteriaLocked): ?>
            <div class="alert alert-info">
                Criteria are locked because this event is <strong><?= $event['status'] ?></strong>.
                Scores already depend on these weights. Set the event back to <strong>upcoming</strong> to edit criteria.
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="flex-between">
                <h3>Scoring Criteria</h3>
                <span id="weightTotalDisplay" class="badge <?= abs($totalWeight-100) < 0.01 ? 'badge-ongoing' : 'badge-upcoming' ?>">
                    Total: <?= number_format($totalWeight, 2) ?>%
                </span>
            </div>
            <p class="small">Weights across all criteria must total exactly 100%.</p>

            <div class="weight-bar" id="weightBarContainer">
                <?php
                $palette = ['#7c5cff', '#22d3ee', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#38bdf8', '#fb923c'];
                foreach ($criteria as $i => $cr):
                    $pct = max(0, (float)$cr['weight_percentage']);
                    $color = $palette[$i % count($palette)];
                ?>
                <div class="weight-bar-segment" style="width:<?= $pct ?>%; background:<?= $color ?>;" title="<?= htmlspecialchars($cr['criteria_name']) ?>: <?= $pct ?>%"></div>
                <?php endforeach; ?>
            </div>

            <div id="criteriaRows" class="stack">
                <?php foreach ($criteria as $i => $cr): ?>
                <div class="flex-gap criteria-row" data-id="<?= $cr['criteria_id'] ?>" style="align-items:flex-end;">
                    <div style="flex:3; min-width:180px;">
                        <label>Criteria Name</label>
                        <input type="text" class="crit-name" value="<?= htmlspecialchars($cr['criteria_name']) ?>" <?= $criteriaLocked ? 'disabled' : '' ?>>
                    </div>
                    <div style="flex:1; min-width:110px;">
                        <label>Weight (%)</label>
                        <input type="number" class="crit-weight" value="<?= $cr['weight_percentage'] ?>" min="0" max="100" step="0.01" <?= $criteriaLocked ? 'disabled' : '' ?>>
                    </div>
                    <?php if (!$criteriaLocked): ?>
                    <button type="button" class="btn btn-sm btn-danger remove-criteria-row">&times;</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$criteriaLocked): ?>
            <div class="flex-gap" style="margin-top: 16px;">
                <button type="button" class="btn btn-sm" id="addCriteriaRowBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Criteria
                </button>
                <button type="button" class="btn btn-primary" id="saveCriteriaBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Criteria
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== JUDGES TAB ===================== -->
    <div class="tab-panel" id="tab-judges">
        <div class="glass-card" style="margin-bottom: 18px;">
            <h3>Add Judge</h3>
            <form id="addJudgeForm" class="flex-gap" style="align-items:flex-end;">
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                <div style="flex:1; min-width:220px;">
                    <label for="judge_name">Judge Name</label>
                    <input type="text" id="judge_name" name="judge_name" required placeholder="e.g. Judge One">
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    Generate Passcode
                </button>
            </form>
        </div>

        <div class="glass-card">
            <table>
                <thead><tr><th>Name</th><th>Passcode</th><th>Status</th><th></th></tr></thead>
                <tbody id="judgesTableBody">
                    <?php foreach ($judges as $j): ?>
                    <tr data-id="<?= $j['judge_id'] ?>">
                        <td><?= htmlspecialchars($j['judge_name']) ?></td>
                        <td>
                            <span class="passcode-box"><?= htmlspecialchars($j['passcode']) ?></span>
                            <button type="button" class="copy-btn" data-passcode="<?= htmlspecialchars($j['passcode']) ?>" onclick="copyPasscode(this)">Copy</button>
                        </td>
                        <td><?= $j['is_active'] ? '<span class="badge badge-ongoing">active</span>' : '<span class="badge badge-upcoming">inactive</span>' ?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-sm btn-danger delete-judge-btn" data-id="<?= $j['judge_id'] ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                Remove
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($judges)): ?>
                    <tr><td colspan="4" class="muted">No judges yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const EVENT_ID = <?= $eventId ?>;
    const EVENT_MAX_SCORE = <?= $event['max_score'] ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
