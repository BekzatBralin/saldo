/**
 * Парсер выписок Freedom Bank Kazakhstan.
 * Шаг 1: отправляет PDF на Python микросервис (pdfplumber) → точный результат.
 * Шаг 2: если Python вернул ошибку или 0 транзакций → Gemini через PHP backend.
 */
export async function parseFreedom(file) {
  // ── Шаг 1: Python парсер ──────────────────────────────────────────────────
  try {
    const formData = new FormData()
    formData.append('file', file)

    const res = await fetch('/api/parse/freedom', {
      method: 'POST',
      body:   formData,
    })

    if (res.ok) {
      const data = await res.json()
      if (data.error) {
        console.warn('[Freedom] Python вернул ошибку:', data.error, '— пробуем Gemini')
      } else if (data.transactions && data.transactions.length > 0) {
        return data
      } else {
        console.warn('[Freedom] Python вернул 0 транзакций — пробуем Gemini')
      }
    }
  } catch (e) {
    console.warn('[Freedom] Python парсер упал:', e.message, '— пробуем Gemini')
  }

  // ── Шаг 2: Gemini fallback через PHP ─────────────────────────────────────
  const geminiFormData = new FormData()   // ← было: const formData (дубликат!)
  geminiFormData.append('file', file)

  const geminiRes = await fetch('/api/ai/parse', {   // ← было: const res (дубликат!)
    method: 'POST',
    body:   geminiFormData,
  })

  if (!geminiRes.ok) {
    const err = await geminiRes.json().catch(() => ({}))
    throw new Error(err.error || `Gemini fallback error: ${geminiRes.status}`)
  }

  return await geminiRes.json()
}