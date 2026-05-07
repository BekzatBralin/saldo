import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/api/index.js'

export const useMainStore = defineStore('main', () => {

  // ── Auth ───────────────────────────────────────────────────────────────────
  const user        = ref(null)
  const authChecked = ref(false)

  // ── Data ───────────────────────────────────────────────────────────────────
  const transactions = ref([])
  const tags         = ref([])
  const stats        = ref(null)
  const loading      = ref(false)

  // ── AI Chat (живёт в Pinia — не сбрасывается при переходах) ───────────────
  const chatOpen     = ref(false)
  const chatMessages = ref([])
  const chatLoading  = ref(false)

  // ── Computed ───────────────────────────────────────────────────────────────
  const isLoggedIn = computed(() => !!user.value)

  const realTransactions = computed(() =>
    transactions.value.filter(tx => !tx.is_deposit)
  )

  const depositTransactions = computed(() =>
    transactions.value.filter(tx => tx.is_deposit)
  )

  const totalIncome = computed(() =>
    realTransactions.value
      .filter(tx => tx.amount > 0)
      .reduce((s, tx) => s + tx.amount, 0)
  )

  const totalExpense = computed(() =>
    realTransactions.value
      .filter(tx => tx.amount < 0)
      .reduce((s, tx) => s + Math.abs(tx.amount), 0)
  )

  const balance = computed(() => totalIncome.value - totalExpense.value)

  const frequentRecipients = computed(() => {
    const counts = {}
    transactions.value
      .filter(tx => tx.type === 'Перевод' && tx.amount < 0)
      .forEach(tx => { counts[tx.detail] = (counts[tx.detail] || 0) + 1 })
    return Object.entries(counts)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 8)
      .map(([name, count]) => ({ name, count }))
  })

  // ── Auth ───────────────────────────────────────────────────────────────────

  async function checkAuth() {
    try {
      user.value = await api.auth.me()
    } catch {
      user.value = null
    } finally {
      authChecked.value = true
    }
  }

  async function logout() {
    await api.auth.logout()
    user.value = null
    transactions.value = []
    tags.value = []
    chatMessages.value = []
  }

  // ── Data ───────────────────────────────────────────────────────────────────

  async function loadTransactions(params = {}) {
    loading.value = true
    try {
      const res = await api.transactions.list({ limit: 500, ...params })
      transactions.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function loadTags() {
    tags.value = await api.tags.list()
  }

  async function loadStats(dateFrom, dateTo) {
    stats.value = await api.stats.get(dateFrom, dateTo)
  }

  async function importTransactions(parsed) {
    const result = await api.transactions.import(
      parsed.transactions,
      parsed.bank,
      parsed.summary?.balanceEnd ?? null
    )
    // Перезагружаем юзера чтобы получить обновлённый баланс
    user.value = await api.auth.me()
    await loadTransactions()

    // AI автотегирование — уникальные мерчанты без тега
    const untagged = transactions.value
      .filter(tx => !tx.tag_id && tx.detail)
      .map(tx => ({ detail: tx.detail, type: tx.type, amount: tx.amount }))

    // Дедупликация по detail+type
    const seen = new Set()
    const unique = untagged.filter(tx => {
      const key = tx.detail + '|' + tx.type
      if (seen.has(key)) return false
      seen.add(key)
      return true
    }).slice(0, 50)

if (unique.length > 0) {
  try {
    const tagMap = await api.ai.tagMerchants(unique)

    // Применяем теги к транзакциям
    if (tagMap && typeof tagMap === 'object') {
      const updates = []
      for (const tx of transactions.value) {
        if (tx.tag_id) continue
        const key = tx.detail + '|' + tx.type
        const tagName = tagMap[key]
        if (!tagName) continue
        const tag = tags.value.find(t => t.name === tagName)
        if (tag) updates.push(updateTag(tx.id, tag.id))
      }
      if (updates.length > 0) await Promise.all(updates)
    }

    await loadTransactions()
  } catch (e) {
    console.warn('AI tagging failed:', e)
  }
}

    return result
  }

  async function updateTag(txId, tagId) {
    await api.transactions.updateTag(txId, tagId)
    const tx = transactions.value.find(t => t.id === txId)
    if (tx) tx.tag_id = tagId
  }

  async function addTag(name, color) {
    const tag = await api.tags.create(name, color)
    tags.value.push(tag)
    return tag
  }

  async function deleteTag(tagId) {
    await api.tags.delete(tagId)
    tags.value = tags.value.filter(t => t.id !== tagId)
  }
  async function clearTransactions() {
    await api.transactions.clear()
    transactions.value = []
    // Обнуляем оба баланса в локальном объекте юзера
    if (user.value) {
      user.value.balance         = 0
      user.value.balance_freedom = 0
    }
    // Синхронизируем с сервером
    user.value = await api.auth.me()
  }

  // ── AI Chat ────────────────────────────────────────────────────────────────

  async function sendChatMessage(content) {
    chatMessages.value.push({ role: 'user', content })
    chatLoading.value = true
    try {
      const context = {
        balance:  Math.round(balance.value),
        income:   Math.round(totalIncome.value),
        expense:  Math.round(totalExpense.value),
        tx_count: transactions.value.length,
      }
      const recent = chatMessages.value.slice(-20)
      const res = await api.ai.chat(recent, context)
      chatMessages.value.push({ role: 'assistant', content: res.reply })
    } catch {
      chatMessages.value.push({ role: 'assistant', content: 'Ошибка соединения. Попробуйте ещё раз.' })
    } finally {
      chatLoading.value = false
    }
  }

  function toggleChat() { chatOpen.value = !chatOpen.value }

  return {
    user, authChecked, isLoggedIn, checkAuth, logout,
    transactions, tags, stats, loading,
    realTransactions, depositTransactions,
    totalIncome, totalExpense, balance, frequentRecipients,
    loadTransactions, loadTags, loadStats,
    importTransactions, updateTag, addTag, deleteTag, clearTransactions,
    chatOpen, chatMessages, chatLoading, sendChatMessage, toggleChat,
  }
})
