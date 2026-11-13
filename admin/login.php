<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login &mdash; Tabulation System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="center-screen">
    <div class="glass-card" style="max-width: 400px; width: 100%;">
        <div class="logo-title">
            <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></div>
            <h1>Admin Login</h1>
            <p>Manage events, contestants &amp; scoring</p>
        </div>

        <?php if ($adminCount === 0): ?>
            <div class="alert alert-info">
                No admin account exists yet. <a href="setup.php">Create one now &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 20px;">Log In</button>
        </form>

        <p class="small" style="text-align:center; margin-top: 18px;">
            <a href="<?= BASE_URL ?>/index.php">&larr; Back to home</a>
        </p>
    </div>
</div>
</body>
</html>
