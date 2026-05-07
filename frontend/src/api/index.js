// Все запросы к бэкенду — в одном месте.
// Когда переключаемся с localStorage на бэк — меняем только здесь.

const BASE = '/api'

async function request(method, path, body = null) {
  const opts = {
    method,
    credentials: 'include', // для httpOnly cookie
    headers: { 'Content-Type': 'application/json' },
  }
  if (body) opts.body = JSON.stringify(body)

  const res = await fetch(BASE + path, opts)

  if (res.status === 401) {
    // Токен протух — редиректим на логин
    window.location.href = '/api/auth/google'
    return
  }

  const data = await res.json()
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
  return data
}

// ── Auth ─────────────────────────────────────────────────────────────────────

export const api = {
  auth: {
    me:     ()      => request('GET',  '/auth/me'),
    logout: ()      => request('POST', '/auth/logout'),
    loginUrl:       () => BASE + '/auth/google',
  },

  // ── Transactions ────────────────────────────────────────────────────────────

  transactions: {
    list: (params = {}) => {
      const q = new URLSearchParams(params).toString()
      return request('GET', `/transactions${q ? '?' + q : ''}`)
    },
import: (transactions, bank = 'kaspi', balanceEnd = null) =>
  request('POST', '/transactions', { transactions, bank, balance_end: balanceEnd }),
    updateTag: (id, tagId) =>
      request('PATCH', `/transactions/${id}`, { tag_id: tagId }),
    clear: () => request('DELETE', '/transactions'),
  },

  // ── Tags ────────────────────────────────────────────────────────────────────

  tags: {
    list:   ()                   => request('GET',    '/tags'),
    create: (name, color)        => request('POST',   '/tags', { name, color }),
    delete: (id)                 => request('DELETE', `/tags/${id}`),
  },

  // ── Stats ───────────────────────────────────────────────────────────────────

  stats: {
    get: (dateFrom, dateTo) => {
      const q = new URLSearchParams({
        ...(dateFrom && { date_from: dateFrom }),
        ...(dateTo   && { date_to:   dateTo   }),
      }).toString()
      return request('GET', `/stats${q ? '?' + q : ''}`)
    },
  },

  // ── AI ──────────────────────────────────────────────────────────────────────

  ai: {
    chat: (messages, context) =>
      request('POST', '/ai/chat', { messages, context }),
    tagMerchants: (merchants) =>
      request('POST', '/ai/tag', { merchants }),
  },
    user: {
    getPrompt:  ()       => request('GET',   '/user/prompt'),
    savePrompt: (prompt) => request('PATCH',  '/user/prompt', { ai_prompt: prompt }),
  },
}
