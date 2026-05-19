<?php

/**
 * Центральный файл конфигурации проекта Saldo
 * Загружает настройки из .env и определяет глобальные константы
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Путь к папке, где лежит .env (на уровень выше от текущей папки config/)
$dotenvPath = __DIR__ . '/..';

if (file_exists($dotenvPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($dotenvPath);
    $dotenv->load();
} else {
    // Если файла нет, выводим ошибку (важно для деплоя)
    die('Ошибка: Файл .env не найден в корне backend. Скорпируйте .env.example в .env и настройте ключи.');
}

// ── База данных ───────────────────────────────────────────────────────────────
define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'saldo');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ── JWT (Авторизация) ─────────────────────────────────────────────────────────
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_me');
define('JWT_TTL',    (int)($_ENV['JWT_TTL'] ?? 2592000)); // По умолчанию 30 дней

// ── Google OAuth ──────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']     ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI',  $_ENV['GOOGLE_REDIRECT_URI']  ?? '');

// ── AI Models (DeepSeek & Gemini) ─────────────────────────────────────────────
define('DEEPSEEK_API_KEY', $_ENV['DEEPSEEK_API_KEY'] ?? '');
define('DEEPSEEK_API_URL', $_ENV['DEEPSEEK_API_URL'] ?? 'https://api.deepseek.com/v1/chat/completions');
define('DEEPSEEK_MODEL',   $_ENV['DEEPSEEK_MODEL']   ?? 'deepseek-v4-flash');
define('GEMINI_API_KEY',   $_ENV['GEMINI_API_KEY']   ?? '');

// ── Приложение ────────────────────────────────────────────────────────────────
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development'); // 'production' или 'development'

// Включаем отображение ошибок, если мы в режиме разработки
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}