<?php

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../includes/db.php'; // DB kapcsolat

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Hiányzó email vagy jelszó."
    ]);
    exit;
}

// user lekérés
$stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Nincs ilyen felhasználó."
    ]);
    exit;
}

// jelszó ellenőrzés
if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    echo json_encode([
        "success" => false,
        "message" => "Hibás jelszó."
    ]);
    exit;
}

// SESSION
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];

echo json_encode([
    "success" => true,
    "message" => "Sikeres bejelentkezés!"
]);
