<?php

declare(strict_types=1);

session_start();
ob_start();

header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

autoLoginFromRememberCookie($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Csak POST kérés engedélyezett.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $statusCode = 400): void
{
    jsonResponse([
        'ok' => false,
        'error' => $message,
    ], $statusCode);
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        fail('Hiányzó kérés.', 400);
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        fail('Érvénytelen JSON kérés.', 400);
    }

    return $decoded;
}

function safeString($value): string
{
    if (is_string($value)) {
        return trim($value);
    }

    if (is_numeric($value)) {
        return trim((string) $value);
    }

    return '';
}

function currentUserId(): int
{
    return !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function requireLogin(): int
{
    $userId = currentUserId();

    if ($userId <= 0) {
        fail('Előzmény mentése csak bejelentkezve érhető el.', 401);
    }

    return $userId;
}

function normalizeHistoryItem(array $item): array
{
    $historyKey = safeString($item['id'] ?? '');
    if ($historyKey === '') {
        $historyKey = 'history-' . time() . '-' . bin2hex(random_bytes(4));
    }

    $savedTags = $item['savedTags'] ?? [];
    if (!is_array($savedTags)) {
        $savedTags = [];
    }

    $conversation = $item['conversation'] ?? [];
    if (!is_array($conversation)) {
        $conversation = [];
    }

    $formData = $item['formData'] ?? [];
    if (!is_array($formData)) {
        $formData = [];
    }

    return [
        'id' => $historyKey,
        'at' => safeString($item['at'] ?? date('Y-m-d H:i:s')),
        'module' => safeString($item['module'] ?? ''),
        'type' => safeString($item['type'] ?? ''),
        'title' => safeString($item['title'] ?? ''),
        'preview' => safeString($item['preview'] ?? ''),
        'fullOutput' => safeString($item['fullOutput'] ?? ''),
        'savedTags' => array_values($savedTags),
        'conversation' => array_values($conversation),
        'formData' => $formData,
    ];
}

function fetchHistoryItems(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            history_key,
            module,
            type,
            title,
            preview,
            full_output,
            saved_tags,
            conversation,
            form_data,
            updated_at
        FROM ai_history
        WHERE user_id = :user_id
        ORDER BY updated_at DESC, id DESC
        LIMIT 50
    ");
    $stmt->execute([
        ':user_id' => $userId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $items = [];

    foreach ($rows as $row) {
        $items[] = [
            'id' => (string) ($row['history_key'] ?? ''),
            'at' => (string) ($row['updated_at'] ?? ''),
            'module' => (string) ($row['module'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'preview' => (string) ($row['preview'] ?? ''),
            'fullOutput' => (string) ($row['full_output'] ?? ''),
            'savedTags' => json_decode((string) ($row['saved_tags'] ?? '[]'), true) ?: [],
            'conversation' => json_decode((string) ($row['conversation'] ?? '[]'), true) ?: [],
            'formData' => json_decode((string) ($row['form_data'] ?? '{}'), true) ?: [],
        ];
    }

    return $items;
}

try {
    $request = getJsonInput();
    $action = safeString($request['action'] ?? '');
    $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

    if ($action === '') {
        fail('Hiányzik az action mező.', 400);
    }

    if ($action === 'list_history') {
        $userId = currentUserId();

        if ($userId <= 0) {
            jsonResponse([
                'ok' => true,
                'items' => [],
            ]);
        }

        jsonResponse([
            'ok' => true,
            'items' => fetchHistoryItems($pdo, $userId),
        ]);
    }

    if ($action === 'upsert_history') {
        $userId = requireLogin();
        $item = normalizeHistoryItem(is_array($payload['item'] ?? null) ? $payload['item'] : []);

        $stmt = $pdo->prepare("
            INSERT INTO ai_history (
                user_id,
                history_key,
                module,
                type,
                title,
                preview,
                full_output,
                saved_tags,
                conversation,
                form_data
            ) VALUES (
                :user_id,
                :history_key,
                :module,
                :type,
                :title,
                :preview,
                :full_output,
                :saved_tags,
                :conversation,
                :form_data
            )
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                type = VALUES(type),
                title = VALUES(title),
                preview = VALUES(preview),
                full_output = VALUES(full_output),
                saved_tags = VALUES(saved_tags),
                conversation = VALUES(conversation),
                form_data = VALUES(form_data),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':history_key' => $item['id'],
            ':module' => $item['module'],
            ':type' => $item['type'],
            ':title' => $item['title'],
            ':preview' => $item['preview'],
            ':full_output' => $item['fullOutput'],
            ':saved_tags' => json_encode($item['savedTags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':conversation' => json_encode($item['conversation'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':form_data' => json_encode($item['formData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        jsonResponse([
            'ok' => true,
            'item' => $item,
            'items' => fetchHistoryItems($pdo, $userId),
        ]);
    }

    if ($action === 'delete_history') {
        $userId = requireLogin();
        $historyKey = safeString($payload['id'] ?? '');

        if ($historyKey === '') {
            fail('Hiányzik a törlendő előzmény azonosítója.', 400);
        }

        $stmt = $pdo->prepare("
            DELETE FROM ai_history
            WHERE user_id = :user_id
              AND history_key = :history_key
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':history_key' => $historyKey,
        ]);

        jsonResponse([
            'ok' => true,
            'items' => fetchHistoryItems($pdo, $userId),
        ]);
    }

    if ($action === 'clear_history') {
        $userId = requireLogin();

        $stmt = $pdo->prepare("
            DELETE FROM ai_history
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            ':user_id' => $userId,
        ]);

        jsonResponse([
            'ok' => true,
            'items' => [],
        ]);
    }

    fail('Ismeretlen action: ' . $action, 400);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'error' => 'Szerverhiba: ' . $e->getMessage(),
    ], 500);
}
