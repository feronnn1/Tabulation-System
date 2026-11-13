<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['judge_id'])) {
    header('Location: score.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passcode = strtoupper(cleanInput($_POST['passcode'] ?? ''));

    $stmt = $pdo->prepare("
        SELECT j.*, e.status AS event_status, e.event_name
        FROM judges j
        JOIN events e ON e.event_id = j.event_id
        WHERE j.passcode = ?
    ");
    $stmt->execute([$passcode]);
    $judge = $stmt->fetch();

    if (!$judge) {
        $error = 'Invalid passcode. Please check with the event admin.';
    } elseif (!$judge['is_active']) {
        $error = 'This passcode has been deactivated.';
    } elseif ($judge['event_status'] === 'completed') {
        $error = 'This event has already concluded. Scoring is closed.';
    } else {
        $_SESSION['judge_id'] = $judge['judge_id'];
        $_SESSION['judge_name'] = $judge['judge_name'];
        $_SESSION['event_id'] = $judge['event_id'];
        $_SESSION['event_name'] = $judge['event_name'];

        $upd = $pdo->prepare("UPDATE judges SET last_login = NOW() WHERE judge_id = ?");
        $upd->execute([$judge['judge_id']]);

        header('Location: score.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Judge Login &mdash; Tabulation System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="center-screen">
    <div class="glass-card" style="max-width: 380px; width: 100%;">
        <div class="logo-title">
            <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></div>
            <h1>Judge Login</h1>
            <p>Enter the passcode given by the event admin</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="passcode">Passcode</label>
            <input type="text" id="passcode" name="passcode" required autofocus
                   style="text-align:center; letter-spacing:4px; font-size:1.3rem; text-transform:uppercase;"
                   maxlength="10" placeholder="e.g. JX9K2">

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 20px;">Enter</button>
        </form>

        <p class="small" style="text-align:center; margin-top: 18px;">
            <a href="<?= BASE_URL ?>/index.php">&larr; Back to home</a>
        </p>
    </div>
</div>
</body>
</html>
