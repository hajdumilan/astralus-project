<?php

declare(strict_types=1);

const ASTRALUS_REMEMBER_COOKIE = 'astralus_remember';
const ASTRALUS_REMEMBER_DAYS = 30;

function log_user_in_session(array $user): void
{
    $_SESSION['user_id'] = (int)($user['id'] ?? 0);
    $_SESSION['user_name'] = (string)($user['name'] ?? '');
    $_SESSION['user_email'] = (string)($user['email'] ?? '');
    $_SESSION['user_role'] = (string)($user['role'] ?? 'user');
}

function clear_remember_cookie(): void
{
    setcookie(ASTRALUS_REMEMBER_COOKIE, '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);

    unset($_COOKIE[ASTRALUS_REMEMBER_COOKIE]);
}

function delete_remember_tokens_by_user_id(PDO $pdo, int $userId): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
    } catch (Throwable $e) {
        return;
    }
}

function create_remember_login(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);
    $expiresAt = (new DateTimeImmutable('+' . ASTRALUS_REMEMBER_DAYS . ' days'))->format('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
            VALUES (:user_id, :selector, :token_hash, :expires_at)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':selector' => $selector,
            ':token_hash' => $validatorHash,
            ':expires_at' => $expiresAt,
        ]);
    } catch (Throwable $e) {
        return;
    }

    setcookie(ASTRALUS_REMEMBER_COOKIE, $selector . ':' . $validator, time() + (ASTRALUS_REMEMBER_DAYS * 86400), '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
}

function autoLoginFromRememberCookie(PDO $pdo): void
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE[ASTRALUS_REMEMBER_COOKIE])) {
        return;
    }

    $parts = explode(':', (string)$_COOKIE[ASTRALUS_REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) {
        clear_remember_cookie();
        return;
    }

    [$selector, $validator] = $parts;

    try {
        $stmt = $pdo->prepare("
            SELECT rt.token_hash, rt.user_id, u.id, u.name, u.email, u.role
            FROM remember_tokens rt
            INNER JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = :selector
              AND rt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':selector' => $selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        clear_remember_cookie();
        return;
    }

    if (!$row || !hash_equals((string)$row['token_hash'], hash('sha256', $validator))) {
        clear_remember_cookie();
        return;
    }

    log_user_in_session($row);
}

function logoutUser(PDO $pdo): void
{
    if (!empty($_SESSION['user_id'])) {
        delete_remember_tokens_by_user_id($pdo, (int)$_SESSION['user_id']);
    }

    clear_remember_cookie();
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
