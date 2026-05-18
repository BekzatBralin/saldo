#!/bin/bash

PROJECT_DIR="/home/thebralin/dev/saldo"
FRONTEND_DIR="$PROJECT_DIR/frontend"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✔ $1${NC}"; }
err()  { echo -e "${RED}✘ $1${NC}"; }
info() { echo -e "${YELLOW}→ $1${NC}"; }

start_services() {
    info "Запускаем MariaDB..."
    sudo systemctl start mariadb && ok "MariaDB запущен" || err "MariaDB не запустился"

    info "Запускаем PHP-FPM..."
    sudo systemctl start php-fpm && ok "PHP-FPM запущен" || err "PHP-FPM не запустился"

    info "Запускаем Caddy..."
    sudo systemctl start caddy && ok "Caddy запущен" || err "Caddy не запустился"
}

stop_services() {
    info "Останавливаем Caddy..."
    sudo systemctl stop caddy && ok "Caddy остановлен" || err "Не удалось остановить Caddy"

    info "Останавливаем PHP-FPM..."
    sudo systemctl stop php-fpm && ok "PHP-FPM остановлен" || err "Не удалось остановить PHP-FPM"

    info "Останавливаем MariaDB..."
    sudo systemctl stop mariadb && ok "MariaDB остановлен" || err "Не удалось остановить MariaDB"
}

build_frontend() {
    info "Собираем Vue фронтенд..."
    cd "$FRONTEND_DIR" && npm run build && ok "Фронтенд собран" || err "Ошибка сборки фронтенда"
    cd - > /dev/null
}

status() {
    echo ""
    for svc in mariadb php-fpm caddy; do
        if systemctl is-active --quiet "$svc"; then
            ok "$svc — работает"
        else
            err "$svc — остановлен"
        fi
    done
    echo ""
}

echo ""
echo "  Saldo — управление проектом"
echo "  ──────────────────────────"
echo "  1) Запустить"
echo "  0) Остановить"
echo "  s) Статус"
echo ""
read -rp "  Выбор: " choice

case "$choice" in
    1)
        echo ""
        read -rp "  Пересобрать фронтенд? (y/N): " rebuild
        echo ""
        start_services
        if [[ "$rebuild" =~ ^[Yy]$ ]]; then
            build_frontend
        fi
        status
        ok "Saldo запущен → http://saldo.bralin.kz"
        ;;
    0)
        echo ""
        stop_services
        status
        ok "Saldo остановлен"
        ;;
    s)
        status
        ;;
    *)
        err "Неверный выбор"
        exit 1
        ;;
esac
