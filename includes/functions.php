<?php
/**
 * functions.php
 * Shared helper functions used across the admin and judge portals.
 */

/**
 * Generates a unique, human-friendly judge passcode.
 * Excludes visually-confusing characters (0/O, 1/I).
 */
function generatePasscode(PDO $pdo, int $length = 5): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM judges WHERE passcode = ?");
        $stmt->execute([$code]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

/** Redirects to the admin login page unless an admin session exists. */
function requireAdminLogin(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/** Redirects to the judge login page unless a judge session exists. */
function requireJudgeLogin(): void {
    if (empty($_SESSION['judge_id'])) {
        header('Location: ' . BASE_URL . '/judge/login.php');
        exit;
    }
}

/** Sends a JSON response and halts execution. */
function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Basic input sanitation for display/storage of plain text fields. */
function cleanInput(?string $str): string {
    return trim(htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'));
}

/** Reads the current request's JSON body as an associative array. */
function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * True if this judge has already confirmed (locked) their scores for this
 * contestant. Used by every score/comment-writing endpoint to enforce the
 * lock server-side - a judge cannot bypass this by replaying old requests
 * or editing the page's JavaScript, since the check happens on the server.
 */
function isContestantLocked(PDO $pdo, int $judgeId, int $contestantId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM score_locks WHERE judge_id = ? AND contestant_id = ?");
    $stmt->execute([$judgeId, $contestantId]);
    return $stmt->fetchColumn() > 0;
}
