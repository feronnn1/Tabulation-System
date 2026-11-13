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

// Optional deep-link: scores.php?id=X&contestant_id=Y (or &judge_id=Z) preselects a filter.
$initialContestantId = (int)($_GET['contestant_id'] ?? 0);
$initialJudgeId = (int)($_GET['judge_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scoring Details &mdash; <?= htmlspecialchars($event['event_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="topbar glass">
    <div class="brand"><span class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></span> Tabulation Admin</div>
    <div class="topbar-right">
        <a href="leaderboard.php?id=<?= $eventId ?>" class="btn btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Leaderboard
        </a>
        <a href="event.php?id=<?= $eventId ?>" class="btn btn-sm">Manage Event</a>
        <a href="dashboard.php" class="btn btn-sm">Dashboard</a>
    </div>
</div>

<div class="wrapper">
    <h1><?= htmlspecialchars($event['event_name']) ?> &mdash; Scoring Details</h1>
    <p>See exactly what each judge gave each contestant, criteria by criteria, plus their comments.</p>

    <div class="glass-card" style="margin-bottom: 20px;">
        <div class="filter-row">
            <div style="flex:1; min-width:200px;">
                <label for="filterContestant">Contestant</label>
                <select id="filterContestant">
                    <option value="0">All contestants</option>
                </select>
            </div>
            <div style="flex:1; min-width:200px;">
                <label for="filterJudge">Judge</label>
                <select id="filterJudge">
                    <option value="0">All judges</option>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="button" class="btn btn-sm" id="clearFiltersBtn">Clear filters</button>
            </div>
        </div>
    </div>

    <div id="resultsSummary" class="small muted" style="margin-bottom: 12px;"></div>
    <div id="detailsContainer">
        <div class="skeleton-line" style="height:90px; border-radius:16px;"></div>
        <div class="skeleton-line" style="height:90px; border-radius:16px;"></div>
        <div class="skeleton-line" style="height:90px; border-radius:16px;"></div>
    </div>
</div>

<script>
    const EVENT_ID = <?= $eventId ?>;
    const BASE_URL = "<?= BASE_URL ?>";
    const INITIAL_CONTESTANT_ID = <?= $initialContestantId ?>;
    const INITIAL_JUDGE_ID = <?= $initialJudgeId ?>;

    let allContestants = [];
    let allJudges = [];

    async function loadFilterOptions() {
        const data = await fetchDetails(); // first call (unfiltered) also gives us the option lists
        populateFilters(data.contestants, data.judges);

        // Apply any filter passed in via the URL (e.g. from a leaderboard "View breakdown" link).
        if (INITIAL_CONTESTANT_ID) document.getElementById('filterContestant').value = INITIAL_CONTESTANT_ID;
        if (INITIAL_JUDGE_ID) document.getElementById('filterJudge').value = INITIAL_JUDGE_ID;

        if (INITIAL_CONTESTANT_ID || INITIAL_JUDGE_ID) {
            applyFilters();
        } else {
            renderEntries(data.entries, data.max_score);
        }
    }

    async function fetchDetails() {
        const contestantId = document.getElementById('filterContestant').value || 0;
        const judgeId = document.getElementById('filterJudge').value || 0;
        const url = `${BASE_URL}/admin/api/get_score_details.php?event_id=${EVENT_ID}&contestant_id=${contestantId}&judge_id=${judgeId}`;
        const res = await fetch(url);
        return res.json();
    }

    function populateFilters(contestants, judges) {
        allContestants = contestants;
        allJudges = judges;

        const cSelect = document.getElementById('filterContestant');
        contestants.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.contestant_id;
            opt.textContent = `#${c.contestant_number} - ${c.full_name}`;
            cSelect.appendChild(opt);
        });

        const jSelect = document.getElementById('filterJudge');
        judges.forEach(j => {
            const opt = document.createElement('option');
            opt.value = j.judge_id;
            opt.textContent = j.judge_name;
            jSelect.appendChild(opt);
        });
    }

    async function applyFilters() {
        document.getElementById('detailsContainer').innerHTML = `
            <div class="skeleton-line" style="height:90px; border-radius:16px;"></div>
            <div class="skeleton-line" style="height:90px; border-radius:16px;"></div>`;
        const data = await fetchDetails();
        if (data.success) {
            renderEntries(data.entries, data.max_score);
        }
    }

    function renderEntries(entries, maxScore) {
        const container = document.getElementById('detailsContainer');
        const summary = document.getElementById('resultsSummary');

        summary.textContent = `${entries.length} score record${entries.length === 1 ? '' : 's'} match the current filters`;

        if (!entries.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <p>No scores match this filter yet.</p>
                </div>`;
            return;
        }

        container.innerHTML = entries.map(e => `
            <div class="glass-card fade-in detail-card">
                <div class="flex-between" style="align-items:flex-start;">
                    <div>
                        <div class="detail-contestant">#${e.contestant_number} &mdash; ${escapeHtml(e.contestant_name)}</div>
                        <div class="detail-judge">Scored by <strong>${escapeHtml(e.judge_name)}</strong></div>
                        ${e.locked
                            ? `<span class="badge badge-ongoing" style="margin-top:6px;">Confirmed &amp; Locked</span>
                               <button type="button" class="comment-toggle" style="margin-left:8px;" onclick="unlockEntry(${e.judge_id}, ${e.contestant_id})">Unlock</button>`
                            : `<span class="badge badge-upcoming" style="margin-top:6px;">Not yet confirmed</span>`}
                    </div>
                    <div class="rank-score">${e.weighted_total.toFixed(2)} <span class="muted small">/ 100</span></div>
                </div>

                <div class="detail-criteria-grid">
                    ${e.criteria_scores.map(cs => `
                        <div class="detail-criteria-item">
                            <div class="flex-between">
                                <span class="small">${escapeHtml(cs.criteria_name)}</span>
                                <span class="small muted">${cs.weight_percentage}%</span>
                            </div>
                            <div class="flex-between" style="margin-top:4px;">
                                <span class="detail-raw-score">${cs.raw_score} <span class="muted small">/ ${maxScore}</span></span>
                                <span class="detail-weighted-contribution">+${cs.weighted_contribution.toFixed(2)}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>

                ${e.comment ? `
                    <div class="detail-comment">
                        <span class="comment-judge">Comment</span>
                        <p class="comment-text">${escapeHtml(e.comment)}</p>
                    </div>` : `<p class="muted small" style="margin-top:12px;">No comment left.</p>`}
            </div>
        `).join('');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    async function unlockEntry(judgeId, contestantId) {
        if (!confirm('Unlock this judge\'s score for this contestant? They will be able to change it again.')) return;
        try {
            const res = await fetch(`${BASE_URL}/admin/api/unlock_score.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ judge_id: judgeId, contestant_id: contestantId })
            });
            const data = await res.json();
            if (data.success) {
                applyFilters();
            } else {
                alert(data.message || 'Could not unlock.');
            }
        } catch (err) {
            alert('Network error - please try again.');
        }
    }

    document.getElementById('filterContestant').addEventListener('change', applyFilters);
    document.getElementById('filterJudge').addEventListener('change', applyFilters);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('filterContestant').value = '0';
        document.getElementById('filterJudge').value = '0';
        applyFilters();
    });

    loadFilterOptions();
</script>
</body>
</html>
