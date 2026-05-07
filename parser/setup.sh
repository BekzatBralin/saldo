#!/bin/bash
# Установка Freedom Parser на ВДС
# Запускать от root: bash setup.sh

set -e

echo "=== Создаём папку ==="
mkdir -p /root/freedom_parser
cp parser_service.py /root/freedom_parser/

echo "=== Создаём venv ==="
python3 -m venv /root/freedom_parser/venv

echo "=== Устанавливаем зависимости ==="
/root/freedom_parser/venv/bin/pip install --upgrade pip
/root/freedom_parser/venv/bin/pip install flask flask-cors pdfplumber

echo "=== Устанавливаем systemd сервис ==="
cp freedom-parser.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable freedom-parser
systemctl start freedom-parser

echo ""
echo "=== Проверка ==="
sleep 2
systemctl status freedom-parser --no-pager
curl -s http://127.0.0.1:5055/health

echo ""
echo "=== Готово! Сервис запущен на порту 5055 ==="
