<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login_php/login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = !empty($_POST['remember_me']);

$_SESSION['old_login_email'] = $email;

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Kérlek, töltsd ki az összes mezőt.';
    header('Location: /login_php/login.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, password_hash, role
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Nincs ilyen felhasználó.';
        header('Location: /login_php/login.php');
        exit;
    }

    if (empty($user['password_hash'])) {
        $_SESSION['login_error'] = 'Ehhez a fiókhoz nincs érvényes jelszó mentve.';
        header('Location: /login_php/login.php');
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $_SESSION['login_error'] = 'Hibás jelszó.';
        header('Location: /login_php/login.php');
        exit;
    }

    log_user_in_session($user);

    if ($rememberMe) {
        create_remember_login($pdo, (int)$user['id']);
    } else {
        delete_remember_tokens_by_user_id($pdo, (int)$user['id']);
        clear_remember_cookie();
    }

    unset($_SESSION['old_login_email']);

    header('Location: /index.php');
    exit;

} catch (Throwable $e) {
    $_SESSION['login_error'] = 'Szerverhiba: ' . $e->getMessage();
    header('Location: /login_php/login.php');
    exit;
}
