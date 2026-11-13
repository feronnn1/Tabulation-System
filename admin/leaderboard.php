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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leaderboard &mdash; <?= htmlspecialchars($event['event_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="topbar glass">
    <div class="brand"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></span> Tabulation Admin</div>
    <div class="topbar-right">
        <a href="scores.php?id=<?= $eventId ?>" class="btn btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Scoring Details
        </a>
        <a href="event.php?id=<?= $eventId ?>" class="btn btn-sm">&larr; Manage Event</a>
        <a href="dashboard.php" class="btn btn-sm">Dashboard</a>
    </div>
</div>

<div class="wrapper">
    <div class="flex-between">
        <div>
            <h1><?= htmlspecialchars($event['event_name']) ?> &mdash; Leaderboard</h1>
            <p>Live, weighted ranking &mdash; updates automatically every 4 seconds.</p>
        </div>
        <div class="small muted" id="lastUpdated">Loading...</div>
    </div>

    <div class="glass-card" id="leaderboardContainer">
        <div class="muted">Loading leaderboard...</div>
    </div>
</div>

<script>
    const EVENT_ID = <?= $eventId ?>;
    const BASE_URL = "<?= BASE_URL ?>";
    const expandedContestants = new Set(); // persists across auto-refreshes

    async function fetchLeaderboard() {
        try {
            const res = await fetch(`${BASE_URL}/admin/api/get_leaderboard.php?event_id=${EVENT_ID}`);
            const data = await res.json();
            if (!data.success) return;
            renderLeaderboard(data.leaderboard, data.total_judges);
            document.getElementById('lastUpdated').textContent =
                'Last updated: ' + new Date().toLocaleTimeString();
        } catch (err) {
            console.error('Leaderboard fetch failed', err);
        }
    }

    function renderLeaderboard(rows, totalJudges) {
        const container = document.getElementById('leaderboardContainer');
        if (!rows.length) {
            container.innerHTML = '<p class="muted">No contestants yet.</p>';
            return;
        }

        const medals = { 1: '&#129351;', 2: '&#129352;', 3: '&#129353;' };

        let html = '';
        rows.forEach(r => {
            const rankClass = r.rank <= 3 ? `rank-${r.rank}` : '';
            const medal = medals[r.rank] ? `<span class="medal">${medals[r.rank]}</span>` : '';
            const isExpanded = expandedContestants.has(r.contestant_id);
            const commentCount = r.comments.length;

            html += `
            <div class="leaderboard-row-wrap">
                <div class="rank-row ${rankClass}">
                    <div class="rank-num">${medal || r.rank}</div>
                    <div>
                        <div class="rank-name">#${r.contestant_number} &mdash; ${escapeHtml(r.full_name)}</div>
                        <div class="rank-meta">
                            Scored by ${r.judges_scored}/${totalJudges} judges
                            ${commentCount > 0
                                ? ` &middot; <button class="comment-toggle" id="toggle-btn-${r.contestant_id}" onclick="toggleComments(${r.contestant_id}, ${commentCount})">${isExpanded ? 'Hide' : 'Show'} ${commentCount} comment${commentCount === 1 ? '' : 's'}</button>`
                                : ' &middot; <span class="muted">No comments yet</span>'}
                            &middot; <a href="${BASE_URL}/admin/scores.php?id=${EVENT_ID}&contestant_id=${r.contestant_id}" class="comment-toggle">View per-judge breakdown</a>
                        </div>
                    </div>
                    <div class="rank-score">${r.final_score.toFixed(2)}</div>
                </div>
                ${commentCount > 0 ? `
                <div class="comments-panel ${isExpanded ? 'show' : ''}" id="comments-${r.contestant_id}">
                    ${r.comments.map(c => `
                        <div class="comment-item">
                            <span class="comment-judge">${escapeHtml(c.judge_name)}</span>
                            <p class="comment-text">${escapeHtml(c.comment)}</p>
                        </div>
                    `).join('')}
                </div>` : ''}
            </div>`;
        });
        container.innerHTML = html;
    }

    function toggleComments(contestantId, commentCount) {
        const nowExpanded = !expandedContestants.has(contestantId);
        nowExpanded ? expandedContestants.add(contestantId) : expandedContestants.delete(contestantId);

        const panel = document.getElementById(`comments-${contestantId}`);
        if (panel) panel.classList.toggle('show', nowExpanded);

        const btn = document.getElementById(`toggle-btn-${contestantId}`);
        if (btn) {
            btn.textContent = `${nowExpanded ? 'Hide' : 'Show'} ${commentCount} comment${commentCount === 1 ? '' : 's'}`;
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    fetchLeaderboard();
    setInterval(fetchLeaderboard, 4000);
</script>
</body>
</html>
