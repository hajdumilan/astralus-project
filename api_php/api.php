<?php

declare(strict_types=1);

session_start();
ob_start();

header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

autoLoginFromRememberCookie($pdo);

const DEFAULT_MODEL = 'gpt-4.1-mini';
const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
const DEBUG_MODE = true;
const GUEST_START_CREDITS = 100;

if (!isset($_SESSION['guest_credits']) || !is_numeric($_SESSION['guest_credits'])) {
    $_SESSION['guest_credits'] = GUEST_START_CREDITS;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'ok' => false,
        'error' => 'Csak POST kérés engedélyezett.'
    ], 405);
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

function debugResponse(string $errorMessage, int $statusCode = 500, array $extra = []): void
{
    $response = [
        'ok' => false,
        'error' => $errorMessage,
    ];

    if (DEBUG_MODE && !empty($extra)) {
        $response['debug'] = $extra;
    }

    jsonResponse($response, $statusCode);
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        debugResponse('Hiányzó kérés.', 400);
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        debugResponse('Érvénytelen JSON kérés.', 400, [
            'json_last_error' => json_last_error_msg(),
        ]);
    }

    return $decoded;
}

function loadLocalConfig(): array
{
    $configPath = __DIR__ . '/api-config.php';

    if (!file_exists($configPath)) {
        return [];
    }

    $config = require $configPath;

    return is_array($config) ? $config : [];
}

function requireOpenAiApiKey(): string
{
    $envKey = trim((string) getenv('OPENAI_API_KEY'));
    if ($envKey !== '') {
        return $envKey;
    }

    $config = loadLocalConfig();
    $configKey = trim((string) ($config['openai_key'] ?? ''));

    if ($configKey !== '') {
        return $configKey;
    }

    throw new RuntimeException('Hiányzik az OpenAI API kulcs. Hozd létre az api-config.php fájlt.');
}

function getOpenAiModel(): string
{
    $envModel = trim((string) getenv('OPENAI_MODEL'));
    if ($envModel !== '') {
        return $envModel;
    }

    $config = loadLocalConfig();
    $configModel = trim((string) ($config['model'] ?? ''));

    if ($configModel !== '') {
        return $configModel;
    }

    return DEFAULT_MODEL;
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

function limitText(string $text, int $maxLength = 12000): string
{
    $text = trim($text);

    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        return $text;
    }

    return trim(mb_substr($text, 0, $maxLength, 'UTF-8')) . "\n\n[szöveg rövidítve]";
}

function normalizeConversation($conversation): array
{
    if (!is_array($conversation)) {
        return [];
    }

    $normalized = [];

    foreach ($conversation as $item) {
        if (!is_array($item)) {
            continue;
        }

        $role = safeString($item['role'] ?? '');
        $text = safeString($item['text'] ?? '');

        if ($text === '') {
            continue;
        }

        if ($role !== 'user' && $role !== 'assistant') {
            continue;
        }

        $normalized[] = [
            'role' => $role,
            'content' => $text,
        ];
    }

    return $normalized;
}

function buildMessages(string $systemPrompt, array $conversation = [], ?string $latestUserMessage = null): array
{
    $messages = [
        [
            'role' => 'system',
            'content' => $systemPrompt,
        ]
    ];

    foreach ($conversation as $msg) {
        if (!isset($msg['role'], $msg['content'])) {
            continue;
        }

        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content'],
        ];
    }

    $latestUserMessage = safeString($latestUserMessage);

    if ($latestUserMessage !== '') {
        $last = end($messages);

        $isDuplicate =
            is_array($last)
            && ($last['role'] ?? '') === 'user'
            && safeString($last['content'] ?? '') === $latestUserMessage;

        if (!$isDuplicate) {
            $messages[] = [
                'role' => 'user',
                'content' => $latestUserMessage,
            ];
        }
    }

    return $messages;
}

function callOpenAi(array $messages, float $temperature = 0.7, ?int $maxTokens = null): string
{
    $apiKey = requireOpenAiApiKey();
    $model = getOpenAiModel();

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
    ];

    if ($maxTokens !== null && $maxTokens > 0) {
        $payload['max_completion_tokens'] = $maxTokens;
    }

    $ch = curl_init(OPENAI_ENDPOINT);

    if ($ch === false) {
        throw new RuntimeException('Nem sikerült inicializálni a cURL kapcsolatot.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 20,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($responseBody === false || $curlError) {
        throw new RuntimeException('Kapcsolódási hiba az OpenAI felé: ' . $curlError);
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Az OpenAI válasza nem értelmezhető JSON.');
    }

    if ($httpCode >= 400) {
        $errorMessage = $decoded['error']['message'] ?? 'Ismeretlen OpenAI hiba.';
        throw new RuntimeException($errorMessage);
    }

    $text = $decoded['choices'][0]['message']['content'] ?? '';

    if (!is_string($text) || trim($text) === '') {
        throw new RuntimeException('Az OpenAI nem adott vissza szöveges választ.');
    }

    return trim($text);
}

function getActionCost(string $action): int
{
    switch ($action) {
        case 'news':
            return 5;
        case 'notes':
            return 7;
        case 'study':
            return 10;
        case 'chat':
            return 1;
        default:
            return 0;
    }
}

function beginCreditDebit(PDO $pdo, int $cost): array
{
    if ($cost <= 0) {
        debugResponse('Érvénytelen credit költség.', 400);
    }

    if (!empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, name, email, credits
            FROM users
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            debugResponse('A felhasználó nem található.', 404);
        }

        $oldCredits = (int)($user['credits'] ?? 0);

        if ($oldCredits < $cost) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            debugResponse('Nincs elég credited ehhez a művelethez.', 402, [
                'required_credits' => $cost,
                'current_credits' => $oldCredits,
                'credit_mode' => 'user',
            ]);
        }

        $newCredits = $oldCredits - $cost;

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

        return [
            'mode' => 'user',
            'user' => $user,
            'old_credits' => $oldCredits,
            'new_credits' => $newCredits,
            'used_credits' => $cost,
        ];
    }

    $oldCredits = (int)($_SESSION['guest_credits'] ?? GUEST_START_CREDITS);

    if ($oldCredits < $cost) {
        debugResponse('Nincs elég vendég credited ehhez a művelethez.', 402, [
            'required_credits' => $cost,
            'current_credits' => $oldCredits,
            'credit_mode' => 'guest',
        ]);
    }

    $newCredits = $oldCredits - $cost;
    $_SESSION['guest_credits'] = $newCredits;

    return [
        'mode' => 'guest',
        'user' => null,
        'old_credits' => $oldCredits,
        'new_credits' => $newCredits,
        'used_credits' => $cost,
    ];
}

function commitCreditDebit(PDO $pdo, array $creditState): void
{
    if (($creditState['mode'] ?? '') === 'user' && $pdo->inTransaction()) {
        $pdo->commit();
    }
}

function rollbackCreditDebit(PDO $pdo, ?array $creditState): void
{
    if (!$creditState) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return;
    }

    if (($creditState['mode'] ?? '') === 'user') {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return;
    }

    if (($creditState['mode'] ?? '') === 'guest') {
        $_SESSION['guest_credits'] = (int)($creditState['old_credits'] ?? GUEST_START_CREDITS);
    }
}

function handleNews(array $payload): string
{
    $topic = safeString($payload['topic'] ?? '');
    $tone = safeString($payload['tone'] ?? 'Rövid kivonat');

    if ($topic === '') {
        debugResponse('Hiányzik a hírkeresési téma.', 400);
    }

    $systemPrompt = <<<PROMPT
Te egy magyar nyelven válaszoló hírösszefoglaló AI vagy.
A felhasználó által megadott témáról készíts tömör, tiszta, jól tagolt összefoglalót.
Ne találj ki konkrét forrásokat vagy eseményeket.
Ha nem kaptál valós hírlistát, akkor általános, semleges témavázlatot adj.
Mindig magyarul válaszolj.
PROMPT;

    $userPrompt = <<<PROMPT
Téma: {$topic}
Kívánt stílus: {$tone}

Készíts jól olvasható, magyar nyelvű összefoglalót.
PROMPT;

    return callOpenAi(
        buildMessages($systemPrompt, [], $userPrompt),
        0.6,
        900
    );
}

function handleNotes(array $payload): string
{
    $text = safeString($payload['text'] ?? '');
    $mode = safeString($payload['mode'] ?? 'Bullet point összefoglaló');

    if ($text === '') {
        debugResponse('Hiányzik az összefoglalni kívánt szöveg.', 400);
    }

    $text = limitText($text, 16000);

    $systemPrompt = <<<PROMPT
Te egy magyar nyelven válaszoló tanulási és jegyzetösszefoglaló AI vagy.
A kapott szöveget jól érthetően, tömören, de hasznosan dolgozd fel.
Tartsd meg a lényeget, emeld ki a fontos pontokat.
Mindig magyarul válaszolj.
PROMPT;

    $userPrompt = <<<PROMPT
Feldolgozási mód: {$mode}

Az alábbi szöveget foglald össze:

{$text}
PROMPT;

    return callOpenAi(
        buildMessages($systemPrompt, [], $userPrompt),
        0.4,
        1400
    );
}

function handleStudy(array $payload): string
{
    $subject = safeString($payload['subject'] ?? '');
    $topic = safeString($payload['topic'] ?? '');
    $level = safeString($payload['level'] ?? 'Középiskola');

    if ($subject === '' || $topic === '') {
        debugResponse('Hiányzik a tantárgy vagy a téma.', 400);
    }

    $systemPrompt = <<<PROMPT
Te egy magyar nyelven válaszoló tanulást segítő AI vagy.
Készíts strukturált, jól tanulható jegyzetet.
Használj rövid bevezetőt, alcímeket, felsorolást és egyszerű magyarázatokat.
A nyelvezet igazodjon a megadott szinthez.
Mindig magyarul válaszolj.
PROMPT;

    $userPrompt = <<<PROMPT
Tantárgy: {$subject}
Téma: {$topic}
Szint: {$level}

Készíts tanulható, jól tagolt magyar nyelvű jegyzetet.
PROMPT;

    return callOpenAi(
        buildMessages($systemPrompt, [], $userPrompt),
        0.6,
        1400
    );
}

function handleChat(array $payload): string
{
    $message = safeString($payload['message'] ?? '');
    $conversation = normalizeConversation($payload['conversation'] ?? []);

    if ($message === '') {
        debugResponse('Hiányzik az üzenet.', 400);
    }

    $systemPrompt = <<<PROMPT
Te egy hasznos, gyors, világos, magyar nyelven válaszoló AI asszisztens vagy.
Mindig magyarul válaszolj.
Légy természetes, közvetlen és jól érthető.
Ha a felhasználó korábbi üzenetei rendelkezésre állnak, használd őket kontextusként.
PROMPT;

    $messages = buildMessages($systemPrompt, $conversation, $message);

    return callOpenAi($messages, 0.7, 1200);
}

$creditState = null;

try {
    $request = getJsonInput();

    $action = safeString($request['action'] ?? '');
    $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

    if ($action === '') {
        debugResponse('Hiányzik az action mező.', 400);
    }

    $cost = getActionCost($action);

    if ($cost <= 0) {
        debugResponse('Ismeretlen action: ' . $action, 400);
    }

    $creditState = beginCreditDebit($pdo, $cost);

    switch ($action) {
        case 'news':
            $text = handleNews($payload);
            break;
        case 'notes':
            $text = handleNotes($payload);
            break;
        case 'study':
            $text = handleStudy($payload);
            break;
        case 'chat':
            $text = handleChat($payload);
            break;
        default:
            throw new RuntimeException('Ismeretlen action.');
    }

    commitCreditDebit($pdo, $creditState);

    jsonResponse([
        'ok' => true,
        'text' => $text,
        'credits_used' => $creditState['used_credits'],
        'old_credits' => $creditState['old_credits'],
        'new_credits' => $creditState['new_credits'],
        'credit_mode' => $creditState['mode'],
        'is_logged_in' => !empty($_SESSION['user_id']),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO) {
        rollbackCreditDebit($pdo, $creditState);
    }

    debugResponse(
        $e->getMessage(),
        500,
        [
            'exception_type' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'credit_mode' => $creditState['mode'] ?? null,
        ]
    );
}
