<?php require_once __DIR__ . '/includes/init.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Event Tabulation System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="center-screen">
    <div style="max-width: 760px; width: 100%;">
        <div class="logo-title">
            <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></div>
            <h1>Tabulation System</h1>
            <p>Multi-criteria scoring for live events &mdash; pageants, hackathons, competitions.</p>
        </div>

        <div class="grid grid-2">
            <a href="<?= BASE_URL ?>/admin/login.php" class="glass-card hoverable portal-card">
                <div class="portal-icon portal-icon-admin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg>
                </div>
                <h2>Admin Portal</h2>
                <p>Create events, manage contestants &amp; criteria, generate judge passcodes, and watch the live leaderboard.</p>
                <span class="portal-cta">Enter as Admin &rarr;</span>
            </a>

            <a href="<?= BASE_URL ?>/judge/login.php" class="glass-card hoverable portal-card">
                <div class="portal-icon portal-icon-judge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a6 6 0 100-12 6 6 0 000 12z"/><path d="M8.5 13.5L7 22l5-3 5 3-1.5-8.5"/></svg>
                </div>
                <h2>Judge Portal</h2>
                <p>Enter your passcode to score contestants with sliders &mdash; every change saves instantly.</p>
                <span class="portal-cta">Enter as Judge &rarr;</span>
            </a>
        </div>
    </div>
</div>
</body>
</html>
