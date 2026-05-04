<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /register_php/register.php');
    exit;
}

$lastname = trim($_POST['lastname'] ?? '');
$firstname = trim($_POST['firstname'] ?? '');
$name = trim($lastname . ' ' . $firstname);
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password2 = $_POST['password_confirm'] ?? '';
$termsAccepted = isset($_POST['terms']);

$_SESSION['register_old_lastname'] = $lastname;
$_SESSION['register_old_firstname'] = $firstname;
$_SESSION['register_old_email'] = $email;

if ($lastname === '' || $firstname === '' || $email === '' || $password === '' || $password2 === '') {
    $_SESSION['register_error'] = 'Kérlek, tölts ki minden mezőt.';
    header('Location: /register_php/register.php');
    exit;
}

if (!$termsAccepted) {
    $_SESSION['register_error'] = 'A regisztrĂˇciĂłhoz el kell fogadnod a feltĂ©teleket.';
    header('Location: /register_php/register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'Adj meg érvényes e-mail címet.';
    header('Location: /register_php/register.php');
    exit;
}

if (mb_strlen($password) < 6) {
    $_SESSION['register_error'] = 'A jelszónak legalább 6 karakter hosszúnak kell lennie.';
    header('Location: /register_php/register.php');
    exit;
}

if ($password !== $password2) {
    $_SESSION['register_error'] = 'A két jelszó nem egyezik.';
    header('Location: /register_php/register.php');
    exit;
}

try {
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute([
        ':email' => $email
    ]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['register_error'] = 'Ez az e-mail cím már regisztrálva van.';
        header('Location: /register_php/register.php');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, role, created_at)
        VALUES (:name, :email, :password_hash, :role, NOW())
    ");

    $insertStmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':role' => 'user'
    ]);

    $_SESSION['register_success'] = 'Sikeres regisztráció. Most már bejelentkezhetsz.';
    $_SESSION['just_registered'] = true;
    unset(
        $_SESSION['register_old_lastname'],
        $_SESSION['register_old_firstname'],
        $_SESSION['register_old_email']
    );

    header('Location: /login_php/login.php');
    exit;

} catch (Throwable $e) {
    echo '<pre style="padding:20px;font-family:monospace;">';
    echo "REGISTER-PROCESS HIBA:\n\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getFile() . ' : ' . $e->getLine();
    echo '</pre>';
    exit;
}
