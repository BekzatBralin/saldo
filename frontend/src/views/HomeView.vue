<template>
  <div>
    <h1 class="page-title">Главная</h1>

    <!-- Кошелёк -->
    <div class="wallet">
      <div class="wallet__top">
        <div>
          <span class="wallet__label">Ваш кошелёк:</span>
          <span class="wallet__amount">{{ fmt(totalBalance) }}</span>
        </div>
      </div>

      <!-- Балансы по банкам — показываем только если хотя бы один банк > 0 -->
      <div class="wallet__banks" v-if="hasKaspi || hasFreedom || hasOther">
        <div v-if="hasKaspi" class="wallet__bank">
          <span class="wb__label">Kaspi Gold</span>
          <span class="wb__amount">{{ fmt(store.user.balance) }}</span>
        </div>
        <div v-if="hasBothBanks" class="wb__sep"></div>
        <div v-if="hasFreedom" class="wallet__bank">
          <span class="wb__label">Freedom Bank</span>
          <span class="wb__amount">{{ fmt(store.user.balance_freedom) }}</span>
        </div>
        <div v-if="hasOther && (hasKaspi || hasFreedom)" class="wb__sep"></div>
        <div v-if="hasOther" class="wallet__bank">
          <span class="wb__label">Другой банк</span>
          <span class="wb__amount">{{ fmt(store.user.balance_other) }}</span>
        </div>
      </div>
      <div class="wallet__row">
        <div class="wallet__stat">
          <span class="ws__label">Доходы</span>
          <span class="ws__val ws__val--green">+{{ fmt(store.totalIncome) }}</span>
        </div>
        <div class="wallet__divider"></div>
        <div class="wallet__stat">
          <span class="ws__label">Расходы</span>
          <span class="ws__val ws__val--red">−{{ fmt(store.totalExpense) }}</span>
        </div>
        <div class="wallet__divider"></div>
        <div class="wallet__stat">
          <span class="ws__label">Операций</span>
          <span class="ws__val">{{ store.transactions.length }}</span>
        </div>
      </div>
    </div>

    <!-- Депозиты — отдельно, не в балансе -->
    <div v-if="store.depositTransactions.length" class="deposit-note">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
        <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2"/>
        <path d="M7 6v4M7 4.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
      </svg>
      {{ store.depositTransactions.length }} депозитных операций на сумму
      {{ fmt(depositTotal) }} не учтены в балансе
    </div>

    <!-- Загрузка выписки -->
    <section class="section">
      <h2 class="section__title">Загрузить выписку</h2>

      <div class="drop-zone"
        :class="{ 'drop-zone--active': isDragging, 'drop-zone--loading': isLoading }"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
        @click="fileInput?.click()"
      >
        <input ref="fileInput" type="file" accept=".pdf" style="display:none" @change="onFileChange" />

        <div v-if="!isLoading" class="drop-zone__inner">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <rect x="7" y="3" width="22" height="30" rx="3" stroke="currentColor" stroke-width="1.4"/>
            <path d="M12 13h12M12 18h12M12 23h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <div>
            <p class="dz__title">Перетащите PDF выписку</p>
            <p class="dz__sub">Kaspi Gold · Freedom Finance</p>
          </div>
        </div>

        <div v-else class="drop-zone__loading">
          <div class="spinner"></div>
          <div>
            <p style="font-size:14px;color:#555;margin-bottom:4px">{{ loadingStep }}</p>
            <p style="font-size:12px;color:#aaa">{{ loadingDetail }}</p>
          </div>
        </div>
      </div>

      <div v-if="importResult" class="banner" :class="importResult.added > 0 ? 'banner--ok' : 'banner--skip'">
        <template v-if="importResult.added > 0">
          Добавлено {{ importResult.added }} операций
          <span v-if="importResult.skipped > 0"> · {{ importResult.skipped }} пропущено (дубликаты)</span>
        </template>
        <template v-else>
          Все операции уже есть — ничего нового
        </template>
      </div>

      <div v-if="parseError" class="banner banner--err">{{ parseError }}</div>
    </section>

    <!-- Частые переводы -->
    <section v-if="store.frequentRecipients.length" class="section">
      <h2 class="section__title">Частые переводы</h2>
      <div class="recipients">
        <div v-for="r in store.frequentRecipients" :key="r.name" class="recipient">
          <div class="recipient__avatar">{{ initials(r.name) }}</div>
          <div class="recipient__info">
            <span class="recipient__name">{{ r.name }}</span>
            <span class="recipient__count">{{ r.count }} {{ plural(r.count) }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Пустое состояние -->
    <div v-if="!store.transactions.length && !isLoading" class="empty">
      Загрузите первую выписку чтобы начать
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useMainStore } from '@/stores/main.js'
import { detectAndParse } from '@/parsers/index.js'
import * as pdfjsLib from 'pdfjs-dist'

const store = useMainStore()
const fileInput    = ref(null)
const isDragging   = ref(false)
const isLoading    = ref(false)
const loadingStep  = ref('')
const loadingDetail = ref('')
const importResult = ref(null)
const parseError   = ref('')

const depositTotal = computed(() =>
  store.depositTransactions.reduce((s, tx) => s + Math.abs(tx.amount), 0)
)

const hasKaspi     = computed(() => parseFloat(store.user?.balance ?? 0) > 0)
const hasFreedom   = computed(() => parseFloat(store.user?.balance_freedom ?? 0) > 0)
const hasOther     = computed(() => parseFloat(store.user?.balance_other ?? 0) > 0)
const hasBothBanks = computed(() => hasKaspi.value && hasFreedom.value)

const totalBalance = computed(() =>
  parseFloat(store.user?.balance ?? 0) +
  parseFloat(store.user?.balance_freedom ?? 0) +
  parseFloat(store.user?.balance_other ?? 0)
)

function fmt(n) {
  return new Intl.NumberFormat('ru-KZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' ₸'
}
function initials(name) {
  return name.split(' ').slice(0,2).map(w => w[0]?.toUpperCase()).join('')
}
function plural(n) {
  if (n % 10 === 1 && n % 100 !== 11) return 'перевод'
  if ([2,3,4].includes(n % 10) && ![12,13,14].includes(n % 100)) return 'перевода'
  return 'переводов'
}

function onDrop(e) {
  isDragging.value = false
  const file = e.dataTransfer.files[0]
  if (file) processFile(file)
}
function onFileChange(e) {
  const file = e.target.files[0]
  if (file) processFile(file)
  e.target.value = ''
}

async function processFile(file) {
  if (!file.name.toLowerCase().endsWith('.pdf')) {
    parseError.value = 'Нужен PDF файл'
    return
  }
  isLoading.value   = true
  parseError.value  = ''
  importResult.value = null

try {
      loadingStep.value   = 'Читаем PDF...'
      loadingDetail.value = file.name

      // Для Kaspi нужен текст, для Freedom — File объект напрямую
      // Читаем текст в любом случае (нужен для определения банка и Kaspi парсера)
      let text = ''
      try {
        text = await extractText(file)
      } catch (e) {
        console.warn('extractText failed:', e.message, '— пробуем AI напрямую')
      }

      loadingStep.value   = 'Парсим транзакции...'
      loadingDetail.value = ''
      let parsed  // ← объявляем ЗДЕСЬ, до любого использования

      // Если текст не извлёкся — сразу к Gemini
      if (!text.trim()) {
        loadingStep.value   = 'PDF не читается — спрашиваем ИИ...'
        loadingDetail.value = 'Это может занять 10–20 секунд'
        parsed = await parseWithAI(file.name, file)
      } else {
        try {
          parsed = await detectAndParse(file.name, text, file)
        } catch (e) {
          if (e.message === 'UNKNOWN_BANK') {
            loadingStep.value   = 'Не распознан банк — спрашиваем ИИ...'
            loadingDetail.value = 'Это может занять 10–20 секунд'
            parsed = await parseWithAI(file.name, file)
          } else {
            throw e
          }
        }
      }

    loadingStep.value   = 'Сохраняем...'
    loadingDetail.value = `${parsed.transactions.length} операций`
    importResult.value  = await store.importTransactions(parsed)

  } catch (e) {
    parseError.value = e.message || 'Не удалось прочитать выписку'
    console.error(e)
  } finally {
    isLoading.value = false
    loadingStep.value = ''
    loadingDetail.value = ''
  }
}

async function parseWithAI(filename, file) {
  // Отправляем PDF файл на PHP backend → Gemini 2.5 Flash читает нативно
  const formData = new FormData()
  formData.append('file', file)
 
  const res = await fetch('/api/ai/parse', {
    method: 'POST',
    body:   formData,
  })
 
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error || `Gemini error: ${res.status}`)
  }
 
  const data = await res.json()
  if (!data.transactions) throw new Error('ИИ не смог извлечь транзакции')
  return data
}

async function extractText(file) {
  const buf = await file.arrayBuffer()
  const pdf = await pdfjsLib.getDocument({ data: buf }).promise
  let fullText = ''
  for (let p = 1; p <= pdf.numPages; p++) {
    const page    = await pdf.getPage(p)
    const content = await page.getTextContent()
    const items   = content.items
      .filter(i => i.str.trim())
      .sort((a, b) => {
        const yDiff = Math.round(b.transform[5]) - Math.round(a.transform[5])
        if (Math.abs(yDiff) > 3) return yDiff
        return a.transform[4] - b.transform[4]
      })
    const groups = []
    for (const item of items) {
      const y = Math.round(item.transform[5])
      const last = groups[groups.length - 1]
      if (last && Math.abs(last.y - y) <= 5) last.parts.push(item.str)
      else groups.push({ y, parts: [item.str] })
    }
    fullText += groups.map(g => g.parts.join('\t')).join('\n') + '\n'
  }

  return fullText
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 600; letter-spacing: -0.4px; margin-bottom: 24px; }

.wallet {
  background: #1a1a18; border-radius: 16px; padding: 24px;
  color: #fff; margin-bottom: 12px;
}
.wallet__top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.wallet__label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 8px; }
.wallet__amount { font-size: 30px; font-weight: 700; letter-spacing: -1px; display: block; }
.wallet__avatar { width: 40px; height: 40px; border-radius: 50%; opacity: .7; }
.wallet__row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.wallet__divider { width: 1px; height: 28px; background: #333; }
.wallet__stat { display: flex; flex-direction: column; gap: 3px; }
.ws__label { font-size: 11px; color: #666; }
.ws__val   { font-size: 14px; font-weight: 600; }
.ws__val--green { color: #4ecb81; }
.ws__val--red   { color: #f07070; }
.wallet__banks {
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 16px; padding: 12px 16px;
  background: rgba(255,255,255,0.06); border-radius: 10px;
}
.wallet__bank { display: flex; flex-direction: column; gap: 3px; }
.wb__label  { font-size: 11px; color: #888; }
.wb__amount { font-size: 15px; font-weight: 600; color: #fff; }
.wb__sep    { width: 1px; height: 28px; background: #333; }

.deposit-note {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; color: #888; margin-bottom: 24px;
  padding: 8px 12px; background: #f4f3ef; border-radius: 8px;
}

.section { margin-bottom: 32px; }
.section__title {
  font-size: 12px; font-weight: 600; color: #aaa;
  text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px;
}

.drop-zone {
  border: 1.5px dashed #ccc; border-radius: 14px;
  padding: 40px 24px; text-align: center; cursor: pointer;
  background: #fff; transition: border-color .15s, background .15s;
}
.drop-zone:hover, .drop-zone--active { border-color: #e42420; background: #fff8f8; }
.drop-zone__inner { display: flex; align-items: center; justify-content: center; gap: 16px; color: #ccc; }
.dz__title { font-size: 15px; font-weight: 500; color: #333; text-align: left; }
.dz__sub   { font-size: 13px; color: #aaa; text-align: left; margin-top: 2px; }
.drop-zone__loading { display: flex; align-items: center; gap: 16px; justify-content: center; }
.spinner { width: 28px; height: 28px; border: 2.5px solid #eee; border-top-color: #e42420; border-radius: 50%; animation: spin .7s linear infinite; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.banner { margin-top: 10px; padding: 10px 14px; border-radius: 8px; font-size: 13px; }
.banner--ok   { background: #e8f5ef; color: #1a7a45; }
.banner--skip { background: #f4f3ef; color: #888; }
.banner--err  { background: #fff0f0; color: #b00; }

.recipients { display: flex; flex-direction: column; gap: 4px; }
.recipient {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: 10px;
  background: #fff; border: 1px solid #e8e6df;
}
.recipient__avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: #f4f3ef; color: #666; display: flex;
  align-items: center; justify-content: center;
  font-size: 12px; font-weight: 600; flex-shrink: 0;
}
.recipient__info { display: flex; flex-direction: column; gap: 2px; }
.recipient__name  { font-size: 14px; font-weight: 500; }
.recipient__count { font-size: 12px; color: #aaa; }

.empty { text-align: center; color: #bbb; padding: 48px 0; font-size: 14px; }
</style>