<?php

declare(strict_types=1);

function astralus_load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function astralus_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $default;
    }

    return is_string($value) ? $value : $default;
}

function astralus_database_error(string $message, ?Throwable $previous = null): void
{
    http_response_code(503);

    $detail = $previous ? $previous->getMessage() : null;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $wantsJson = stripos($accept, 'application/json') !== false
        || stripos($uri, 'api') !== false
        || stripos($uri, 'history') !== false;

    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'error' => $message,
            'detail' => $detail,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><title>Adatbázis hiba</title></head><body>';
    echo '<h1>Adatbázis kapcsolat hiba</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';

    if ($detail !== null && $detail !== '') {
        echo '<pre>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>';
    }

    echo '</body></html>';
    exit;
}

astralus_load_env_file(dirname(__DIR__) . '/.env');

$host = astralus_env('ASTRALUS_DB_HOST', 'localhost');
$dbname = astralus_env('ASTRALUS_DB_NAME', 'adatbazis_nev');
$user = astralus_env('ASTRALUS_DB_USER', 'adatbazis_felhasznalo');
$pass = astralus_env('ASTRALUS_DB_PASS', 'adatbazis_jelszo');
$dsn = astralus_env('ASTRALUS_DB_DSN');

if ($dsn === null || $dsn === '') {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    astralus_database_error(
        'Nem sikerült kapcsolódni a MySQL adatbázishoz. Ellenőrizd az ASTRALUS_DB_HOST, ASTRALUS_DB_NAME, ASTRALUS_DB_USER és ASTRALUS_DB_PASS értékeket a .env fájlban.',
        $e
    );
}
