<?php

function app_load_env_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('The environment file must be a readable regular file.');
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (!is_array($values)) {
        throw new RuntimeException('Could not parse the environment file.');
    }

    foreach ($values as $name => $value) {
        if (!preg_match('/\A[A-Z][A-Z0-9_]*\z/D', (string)$name) || !is_string($value)) {
            throw new RuntimeException('The environment file contains an invalid setting.');
        }
        if (getenv($name) !== false || isset($_ENV[$name]) || isset($_SERVER[$name])) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

app_load_env_file(dirname(__DIR__) . '/.env');

function app_env(string $name): ?string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }
    if (isset($_ENV[$name]) && is_string($_ENV[$name])) {
        return $_ENV[$name];
    }
    if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
        return $_SERVER[$name];
    }

    return null;
}

function app_env_required(string $name): string
{
    $value = app_env($name);
    if ($value === null || trim($value) === '') {
        throw new RuntimeException('Required environment setting is missing: ' . $name);
    }

    return $value;
}

function app_bootstrap_username(string $name): string
{
    $username = app_env_required($name);
    if (
        strlen($username) > 50
        || str_contains(strtolower($username), 'replace_with_')
    ) {
        throw new RuntimeException($name . ' must be set to a real username.');
    }

    return $username;
}

function app_bootstrap_password(string $name): string
{
    $password = app_env_required($name);
    if (strlen($password) < 16 || str_contains(strtolower($password), 'replace_with_')) {
        throw new RuntimeException($name . ' must be a unique password of at least 16 characters.');
    }

    return $password;
}

function render_font_links(): string
{
    $preconnectUrl = app_env('FONT_PRECONNECT_URL') ?? '';
    $stylesheetUrl = app_env('FONT_STYLESHEET_URL') ?? '';

    if ($preconnectUrl === '' && $stylesheetUrl === '') {
        return '';
    }

    $links = '';
    if ($preconnectUrl !== '') {
        $links .= '<link rel="preconnect" href="' .
            htmlspecialchars($preconnectUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '" crossorigin>' . "\n";
    }
    if ($stylesheetUrl !== '') {
        $links .= '<link rel="stylesheet" href="' .
            htmlspecialchars($stylesheetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '">' . "\n";
    }

    return $links;
}

$appTimezone = app_env('APP_TIMEZONE');
if ($appTimezone !== null && $appTimezone !== '') {
    date_default_timezone_set($appTimezone);
}
