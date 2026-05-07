/**
 * Парсер выписок Kaspi Gold.
 * Принимает текст (собранный из pdfjs по Y-группировке),
 * возвращает { name, period, summary, breakdown, transactions }.
 */
export function parseKaspi(text) {
  const lines = text.split('\n').map(l => l.trim()).filter(Boolean)

  // ── Период ──────────────────────────────────────────────────────────────
  const periodMatch = text.match(/за период с (\d{2}\.\d{2}\.\d{2}) по (\d{2}\.\d{2}\.\d{2})/)
  const period = periodMatch ? `${periodMatch[1]} — ${periodMatch[2]}` : ''

  // ── Имя владельца ────────────────────────────────────────────────────────
  let name = ''
  for (let i = 0; i < lines.length; i++) {
    if (lines[i].startsWith('Номер карты')) {
      const candidates = lines.slice(Math.max(0, i - 3), i)
      name = candidates
        .filter(l => !l.includes('ВЫПИСКА') && !l.includes('период') && !l.includes('Kaspi') && l.length > 3)
        .join(' ')
        .trim()
      break
    }
  }

  // ── Балансы ──────────────────────────────────────────────────────────────
  const balanceRe = /Доступно на \d{2}\.\d{2}\.\d{2}\s*\n?\s*([+\-]\s*[\d\s,]+)\s*₸/g
  const balances = []
  let bm
  while ((bm = balanceRe.exec(text)) !== null) {
    const v = parseAmount(bm[1])
    if (v !== null) balances.push(v)
  }

  // ── Краткое содержание ───────────────────────────────────────────────────
  function getSummaryLine(label) {
    const re = new RegExp(label + '[\\s\\S]{0,10}?([+\\-]\\s*[\\d\\s,]+)\\s*₸')
    const m = text.match(re)
    return m ? Math.abs(parseAmount(m[1]) ?? 0) : 0
  }

  const income      = getSummaryLine('Пополнения')
  const transfers   = getSummaryLine('Переводы')
  const purchases   = getSummaryLine('Покупки')
  const withdrawals = getSummaryLine('Снятия')

  // ── Транзакции ───────────────────────────────────────────────────────────
  const TX_TYPES  = ['Пополнение', 'Покупка', 'Перевод', 'Снятие', 'Разное']
  const dateRe    = /^\d{2}\.\d{2}\.\d{2}$/
  const amountRe  = /^[+\-]\s*[\d\s]+,\d{2}$/

  const transactions = []
  let tableStarted = false

  for (const line of lines) {
    if (line.includes('Дата') && line.includes('Сумма') && line.includes('Операция')) {
      tableStarted = true
      continue
    }
    if (!tableStarted) continue

    const cols = line.split('\t').map(c => c.trim()).filter(Boolean)
    if (cols.length < 3) continue
    if (!dateRe.test(cols[0])) continue

    const date = cols[0]
    let amountStr = null
    let typeIdx   = -1

    for (let ci = 1; ci < cols.length; ci++) {
      const candidate = cols[ci].replace('₸', '').trim()
      if (amountRe.test(candidate)) {
        amountStr = candidate
        if (ci + 1 < cols.length && TX_TYPES.includes(cols[ci + 1])) {
          typeIdx = ci + 1
        }
        break
      }
    }

    if (!amountStr) continue
    const amount = parseAmount(amountStr)
    if (amount === null) continue

    const type   = typeIdx >= 0 ? cols[typeIdx] : ''
    const detail = cols.slice(typeIdx + 1).join(' ').trim()
    if (!TX_TYPES.includes(type)) continue

    transactions.push({
      date,
      amount,
      type,
      detail,
      hash: makeHash(date, amount, type, detail),
    })
  }

  return {
    name,
    period,
    summary: {
      balanceStart: balances[0] ?? 0,
      balanceEnd:   balances[balances.length - 1] ?? 0,
      income,
      expense: transfers + purchases + withdrawals,
    },
    breakdown: [
      { label: 'Пополнения', amount: income,      type: 'income'  },
      { label: 'Покупки',    amount: purchases,    type: 'expense' },
      { label: 'Переводы',   amount: transfers,    type: 'expense' },
      { label: 'Снятия',     amount: withdrawals,  type: 'expense' },
    ],
    transactions,
  }
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function parseAmount(str) {
  if (!str) return null
  const clean = str.replace(/\s/g, '').replace(',', '.')
  const match = clean.match(/([+\-])([\d.]+)/)
  if (!match) return null
  const val = parseFloat(match[2])
  return match[1] === '-' ? -val : val
}

// Хэш для дедупликации
function makeHash(date, amount, type, detail) {
  return btoa(encodeURIComponent(`${date}|${amount}|${type}|${detail}`))
    .replace(/[^a-z0-9]/gi, '')
    .slice(0, 32)
}
