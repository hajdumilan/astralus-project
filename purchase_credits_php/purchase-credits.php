<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

autoLoginFromRememberCookie($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Nem engedélyezett metódus.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'A művelethez be kell jelentkezned.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Érvénytelen kérés.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$amount = (int)($data['amount'] ?? 0);
$allowedPackages = [50, 1000, 5000];

if (!in_array($amount, $allowedPackages, true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Érvénytelen credit csomag.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $selectStmt = $pdo->prepare("
        SELECT id, name, credits
        FROM users
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");
    $selectStmt->execute([':id' => $userId]);

    $user = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $pdo->rollBack();

        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'error' => 'A felhasználó nem található.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $currentCredits = (int)($user['credits'] ?? 0);
    $newCredits = $currentCredits + $amount;

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET credits = :credits
        WHERE id = :id
        LIMIT 1
    ");
    $updateStmt->execute([
        ':credits' => $newCredits,
        ':id' => $userId,
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'A credit sikeresen jóváírva.',
        'credits_added' => $amount,
        'old_credits' => $currentCredits,
        'new_credits' => $newCredits,
        'user_name' => $user['name'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Szerverhiba történt: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
