<?php

require_once __DIR__ . '/../includes/db.php';

$name = 'Teszt User';
$email = 'teszt@astralus.hu';
$password = '123456';

// JELSZÓ HASH
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// INSERT
$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role, created_at)
    VALUES (:name, :email, :password_hash, :role, NOW())
");

$stmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':password_hash' => $hashedPassword,
    ':role' => 'user'
]);

echo "Felhasználó létrehozva!";
