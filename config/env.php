<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * Load key/value pairs from .env files into $_ENV (without overriding
 * already-defined server environment variables).
 */
function loadEnvFile(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        if ($key === '') {
            continue;
        }

        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Read an environment variable with fallback support.
 */
function env(string $key, mixed $default = null): mixed
{
    static $loaded = false;

    if (!$loaded) {
        $root = dirname(__DIR__);
        loadEnvFile($root . '/.env');
        loadEnvFile($root . '/.env.local');
        $loaded = true;
    }

    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    return $default;
}

