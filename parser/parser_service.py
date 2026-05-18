#!/usr/bin/env python3
"""
Freedom Bank PDF парсер — Flask микросервис.
Путь на ВДС: /root/freedom_parser/parser_service.py

Запуск:
  cd /root/freedom_parser
  source venv/bin/activate
  python parser_service.py

Слушает: 127.0.0.1:5055
"""

import re
import json
import base64
import logging
from pathlib import Path
from urllib.parse import quote

import pdfplumber
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app, origins=['*'])  # Caddy сам фильтрует снаружи

logging.basicConfig(level=logging.INFO)
log = logging.getLogger('freedom_parser')


# ── Helpers ───────────────────────────────────────────────────────────────────

def parse_amount(s: str):
    if not s:
        return None
    clean = re.sub(r'[₸T$\s]', '', s).replace(',', '')
    try:
        return float(clean)
    except ValueError:
        return None


def short_date(d: str) -> str:
    parts = d.split('.')
    if len(parts) == 3 and len(parts[2]) == 4:
        return f"{parts[0]}.{parts[1]}.{parts[2][2:]}"
    return d


def normalize_type(t: str) -> str:
    mapping = {
        'Покупка':           'Покупка',
        'Перевод':           'Перевод',
        'Платеж':            'Покупка',
        'Платеж по кредиту': 'Покупка',
        'Снятие':            'Снятие',
        'Пополнение':        'Пополнение',
        'Сумма в обработке': 'Покупка',
        'Погашение':         'Покупка',
        'Овердрафт':         'Разное',
        'Другое':            'Разное',
    }
    return mapping.get(t, 'Разное')


def make_hash(date, amount, tx_type, detail) -> str:
    raw = f"{date}|{amount}|{tx_type}|{detail}"
    encoded = base64.b64encode(quote(raw).encode()).decode()
    return re.sub(r'[^a-z0-9]', '', encoded, flags=re.IGNORECASE)[:32]


TX_TYPES = {
    'Покупка', 'Перевод', 'Платеж', 'Снятие', 'Пополнение',
    'Сумма в обработке', 'Погашение', 'Овердрафт', 'Другое', 'Платеж по кредиту'
}
DATE_RE   = re.compile(r'^\d{2}\.\d{2}\.\d{4}$')
AMOUNT_RE = re.compile(r'^-?[\d,]+\.\d{2}')


# ── Парсер ────────────────────────────────────────────────────────────────────

def parse_freedom(pdf_bytes: bytes) -> dict:
    result = {
        'bank':    'freedom',
        'name':    '',
        'period':  '',
        'summary': {'balanceStart': 0, 'balanceEnd': 0, 'income': 0, 'expense': 0},
        'breakdown': [],
        'transactions': [],
    }

    import io
    full_text = ''
    all_table_rows = []

    with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
        for page in pdf.pages:
            text = page.extract_text() or ''
            full_text += text + '\n'

            tables = page.extract_tables({
                'vertical_strategy':   'lines',
                'horizontal_strategy': 'lines',
                'snap_tolerance':      5,
                'join_tolerance':      3,
            })
            for table in tables:
                for row in table:
                    cleaned = [
                        (cell.strip().replace('\n', ' ') if cell else '')
                        for cell in row
                    ]
                    if any(cleaned):
                        all_table_rows.append(cleaned)

    # Имя
    name_match = re.search(r'\n([А-ЯЁ][А-ЯЁ\s]{3,})\n([А-ЯЁ][А-ЯЁ\s]{3,})\n', full_text)
    if name_match:
        result['name'] = name_match.group(1).strip() + ' ' + name_match.group(2).strip()

    # Период
    period_match = re.search(
        r'за период с (\d{2}\.\d{2}\.\d{4}) по (\d{2}\.\d{2}\.\d{4})', full_text
    )
    if period_match:
        result['period'] = f"{period_match.group(1)} — {period_match.group(2)}"

    # Баланс KZT
    balance_match = re.search(r'KZT\s+([\d,]+\.\d{2})\s*[₸T]', full_text)
    if balance_match:
        result['summary']['balanceEnd'] = parse_amount(balance_match.group(1)) or 0

    # Краткое содержание
    def get_summary(label):
        m = re.search(label + r'\s+(-?[\d,]+\.\d{2})\s*[₸T]', full_text)
        return abs(parse_amount(m.group(1)) or 0) if m else 0

    purchases   = get_summary('Покупка')
    transfers   = get_summary('Перевод')
    payments    = get_summary('Платеж')
    withdrawals = get_summary('Снятие')
    income      = get_summary('Пополнение')

    result['summary']['income']  = income
    result['summary']['expense'] = purchases + transfers + payments + withdrawals
    result['breakdown'] = [
        {'label': 'Пополнения', 'amount': income,      'type': 'income' },
        {'label': 'Покупки',    'amount': purchases,    'type': 'expense'},
        {'label': 'Переводы',   'amount': transfers,    'type': 'expense'},
        {'label': 'Платежи',    'amount': payments,     'type': 'expense'},
        {'label': 'Снятия',     'amount': withdrawals,  'type': 'expense'},
    ]

    # Транзакции
    header_found = False
    transactions = []

    for row in all_table_rows:
        row_text = ' '.join(row).lower()
        if 'дата' in row_text and 'сумма' in row_text and 'операция' in row_text:
            header_found = True
            continue
        if not header_found or not any(row):
            continue

        date_str = row[0].strip() if row else ''
        if not DATE_RE.match(date_str):
            continue

        amount_str = row[1].strip() if len(row) > 1 else ''
        amount = parse_amount(amount_str)
        if amount is None:
            continue

        tx_type_raw = (row[3].strip() if len(row) > 3 else '').replace('\n', ' ').strip()
        detail      = (row[4].strip() if len(row) > 4 else '').replace('\n', ' ').strip()

        tx_type = normalize_type(tx_type_raw) if tx_type_raw in TX_TYPES else 'Покупка'
        date    = short_date(date_str)
        tx_hash = make_hash(date, amount, tx_type, detail)

        transactions.append({
            'date':   date,
            'amount': amount,
            'type':   tx_type,
            'detail': detail,
            'hash':   tx_hash,
        })

    result['transactions'] = transactions
    log.info(f"Parsed {len(transactions)} transactions, period={result['period']}")
    return result


# ── Маршруты ──────────────────────────────────────────────────────────────────

@app.route('/api/parse/health', methods=['GET'])
def health():
    return jsonify({'ok': True})


@app.route('/api/parse/freedom', methods=['POST'])
def parse_endpoint():
    if 'file' not in request.files:
        return jsonify({'error': 'No file uploaded'}), 400

    f = request.files['file']
    if not f.filename.lower().endswith('.pdf'):
        return jsonify({'error': 'Only PDF files accepted'}), 400

    try:
        pdf_bytes = f.read()
        result = parse_freedom(pdf_bytes)
        return jsonify(result)
    except Exception as e:
        log.exception('Parse error')
        return jsonify({'error': str(e)}), 500


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5055, debug=False)
