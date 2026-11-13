<?php
require_once __DIR__ . '/../includes/init.php';
requireAdminLogin();

$events = $pdo->query("
    SELECT e.*,
        (SELECT COUNT(*) FROM contestants c WHERE c.event_id = e.event_id) AS contestant_count,
        (SELECT COUNT(*) FROM judges j WHERE j.event_id = e.event_id) AS judge_count,
        (SELECT COUNT(*) FROM criteria cr WHERE cr.event_id = e.event_id) AS criteria_count
    FROM events e
    ORDER BY e.created_at DESC
")->fetchAll();

$totalEvents = count($events);
$totalContestants = array_sum(array_column($events, 'contestant_count'));
$totalJudges = array_sum(array_column($events, 'judge_count'));
$ongoingCount = count(array_filter($events, fn($e) => $e['status'] === 'ongoing'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard &mdash; Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="topbar glass">
    <div class="brand"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></span> Tabulation Admin</div>
    <div class="topbar-right">
        <span>Hi, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        <a href="logout.php" class="btn btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</div>

<div class="wrapper">
    <div class="flex-between" style="margin-bottom: 20px;">
        <div>
            <h1>Events</h1>
            <p>Create and manage your events</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Event
        </button>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
            <div><div class="stat-value"><?= $totalEvents ?></div><div class="stat-label">Total Events</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
            <div><div class="stat-value"><?= $totalContestants ?></div><div class="stat-label">Total Contestants</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
            <div><div class="stat-value"><?= $totalJudges ?></div><div class="stat-label">Total Judges</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div><div class="stat-value"><?= $ongoingCount ?></div><div class="stat-label">Ongoing Now</div></div>
        </div>
    </div>

    <div id="alertBox"></div>

    <div class="grid grid-auto" id="eventsGrid">
        <?php foreach ($events as $ev): ?>
        <div class="glass-card hoverable fade-in" data-event-id="<?= $ev['event_id'] ?>">
            <div class="flex-between">
                <h3><?= htmlspecialchars($ev['event_name']) ?></h3>
                <span class="badge badge-<?= $ev['status'] ?>"><?= $ev['status'] ?></span>
            </div>
            <p class="small"><?= htmlspecialchars($ev['description'] ?: 'No description') ?></p>
            <div class="flex-gap small muted" style="margin-bottom: 14px;">
                <span><?= $ev['contestant_count'] ?> contestants</span>
                <span>&middot;</span>
                <span><?= $ev['judge_count'] ?> judges</span>
                <span>&middot;</span>
                <span><?= $ev['criteria_count'] ?> criteria</span>
                <span>&middot;</span>
                <span>Max score: <?= $ev['max_score'] ?></span>
            </div>

            <label>Status</label>
            <select class="status-select" data-id="<?= $ev['event_id'] ?>">
                <option value="upcoming" <?= $ev['status']==='upcoming'?'selected':'' ?>>Upcoming</option>
                <option value="ongoing" <?= $ev['status']==='ongoing'?'selected':'' ?>>Ongoing</option>
                <option value="completed" <?= $ev['status']==='completed'?'selected':'' ?>>Completed</option>
            </select>

            <div class="flex-gap" style="margin-top: 16px;">
                <a href="event.php?id=<?= $ev['event_id'] ?>" class="btn btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Manage
                </a>
                <a href="leaderboard.php?id=<?= $ev['event_id'] ?>" class="btn btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Leaderboard
                </a>
                <button class="btn btn-sm btn-danger delete-event-btn" data-id="<?= $ev['event_id'] ?>" title="Delete event">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($events)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <p>No events yet. Click <strong>New Event</strong> above to create your first one.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Event Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box glass-card">
        <h2>New Event</h2>
        <form id="createEventForm">
            <label for="event_name">Event Name</label>
            <input type="text" id="event_name" name="event_name" required placeholder="e.g. Miss University 2026">

            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="2" placeholder="Short description"></textarea>

            <label for="max_score">Max Score per Criteria (e.g. 100)</label>
            <input type="number" id="max_score" name="max_score" value="100" min="1" required>

            <div class="flex-gap" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Create Event</button>
                <button type="button" class="btn" onclick="closeCreateModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
