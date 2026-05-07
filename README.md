#  Saldo Project
Веб-приложение для парсинга банковских выписок (Freedom Bank, Kaspi) и управления личными финансами.

##  Структура проекта
* [cite_start]**backend/** — API на PHP (нативная разработка).
* [cite_start]**frontend/** — Интерфейс на Vue.js.
* [cite_start]**parser/** — Микросервис на Python для обработки PDF.
* [cite_start]**db/** — SQL файл базы данных.

##  Требования
* PHP 8.1+ & Composer
* Node.js & npm
* Python 3.10+
* Caddy (для проксирования)
* MySQL 8.0+ / MariaDB 10.x+

##  Быстрый запуск

### 1. Backend
cd backend
composer install
cp .env.example .env
# Настрой БД и API ключи в .env

### 2. Frontend
cd frontend
npm install
npm run dev

### 3. Python Parser
cd parser
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
python parser_service.py

### Конфигурация Caddy
Проект настроен на работу через Caddy. Пример конфига можно найти в файле Caddyfile

### База Данных
Проект работает на MySQL. sql файл с структурой можно найти в /db/saldo.sql