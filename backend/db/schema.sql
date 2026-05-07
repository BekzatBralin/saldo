-- Kaspi App — полная схема БД
-- Запустить: mysql -u root -p kaspi_app < schema.sql

CREATE DATABASE IF NOT EXISTS kaspi_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kaspi_app;

-- ── Пользователи ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_id  VARCHAR(64)  NOT NULL UNIQUE,
    email      VARCHAR(255) NOT NULL UNIQUE,
    name       VARCHAR(255) NOT NULL,
    avatar     VARCHAR(512) DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Теги ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tags (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED NOT NULL,
    name      VARCHAR(64)  NOT NULL,
    color     VARCHAR(16)  NOT NULL DEFAULT '#888888',
    is_system TINYINT(1)   NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Транзакции ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    date       DATE         NOT NULL,          -- 2026-04-19
    amount     DECIMAL(12,2) NOT NULL,         -- отрицательное = расход
    type       VARCHAR(32)  NOT NULL,          -- Покупка / Перевод / Пополнение / Снятие / Разное
    detail     VARCHAR(512) NOT NULL DEFAULT '',
    tag_id     INT UNSIGNED DEFAULT NULL,
    bank       VARCHAR(32)  NOT NULL DEFAULT 'kaspi',
    is_deposit TINYINT(1)   NOT NULL DEFAULT 0,
    hash       VARCHAR(64)  NOT NULL,          -- дедупликация
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_hash (user_id, hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE SET NULL,
    INDEX idx_user_date (user_id, date),
    INDEX idx_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Мерчанты (кэш автотегирования) ───────────────────────────────────────────
-- Когда ИИ один раз определил тег для "ИП демі" — запоминаем,
-- следующий PDF не будет тратить токены на то же самое
CREATE TABLE IF NOT EXISTS merchants (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name    VARCHAR(512) NOT NULL,
    tag_id  INT UNSIGNED DEFAULT NULL,
    UNIQUE KEY uq_user_merchant (user_id, name(100)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Системные теги (создаются для каждого нового юзера) ──────────────────────
-- Вставляются через PHP при регистрации, не хардкодим здесь
