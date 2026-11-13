<?php
require_once __DIR__ . '/../includes/init.php';

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
$error = '';
$success = '';

if ($adminCount > 0) {
    $error = 'An admin account already exists. Setup is locked for security.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        $success = 'Admin account created successfully! You can now log in.';
        $adminCount = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Setup &mdash; Tabulation System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="center-screen">
    <div class="glass-card" style="max-width: 420px; width: 100%;">
        <div class="logo-title">
            <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></div>
            <h1>Create Admin Account</h1>
            <p>One-time setup &mdash; runs only when no admin exists yet.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($adminCount == 0): ?>
        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required placeholder="admin">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="At least 6 characters">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 20px;">Create Account</button>
        </form>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
