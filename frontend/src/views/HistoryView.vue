<template>
  <div class="history-root">
    <h1 class="page-title">История</h1>

    <div class="toolbar">
      <div class="search-row">
        <input v-model="search" class="search" placeholder="Поиск по названию..." @input="resetLimit" />
        <input v-model="searchAmount" class="search search--amount" placeholder="Сумма..." type="number" @input="resetLimit" />
      </div>
      <div class="filters">
        <button v-for="f in typeFilters" :key="f.value"
          class="chip" :class="{ 'chip--active': activeType === f.value }"
          @click="activeType = f.value; resetLimit()">{{ f.label }}</button>
      </div>
    </div>

    <div v-if="store.loading" class="loading-row">
      <div class="spinner"></div> Загрузка...
    </div>

    <div v-else-if="!store.transactions.length" class="empty">
      Нет операций — загрузите выписку на главной
    </div>

    <template v-else>
      <div class="table-wrap">
        <table class="tx-table">
          <thead>
            <tr>
              <th @click="sortBy('date')"   class="sortable">Дата   <span>{{ sortIcon('date')   }}</span></th>
              <th @click="sortBy('amount')" class="sortable">Сумма  <span>{{ sortIcon('amount') }}</span></th>
              <th @click="sortBy('tag')" class="sortable">Тег <span>{{ sortIcon('tag') }}</span></th>
              <th>Детали</th>
              <th>Тип</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tx in visibleTx" :key="tx.id">
              <td class="td-date">{{ tx.date }}</td>
              <td class="td-amount" :class="tx.amount > 0 ? 'pos' : 'neg'">
                {{ tx.amount > 0 ? '+' : '−' }}{{ fmt(Math.abs(tx.amount)) }}
              </td>
              <td>
                <select class="tag-select" :value="tx.tag_id ?? ''" @change="changeTag(tx.id, $event.target.value)">
                  <option value="">— без тега —</option>
                  <option v-for="t in store.tags" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </td>
              <td class="td-detail">
                {{ tx.detail }}
                <span v-if="tx.is_deposit" class="deposit-flag" title="Депозитный перевод">Д</span>
              </td>
              <td><span class="badge" :class="'badge--' + tx.type">{{ tx.type }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Футер: счётчик + sentinel для infinite scroll -->
      <div class="table-footer">
        <span class="tx-count">{{ visibleTx.length }} из {{ filtered.length }} операций</span>
        <div v-if="isLoadingMore" class="loading-more">
          <div class="spinner"></div>
        </div>
      </div>

      <!-- Sentinel — когда он виден, подгружаем следующую порцию -->
      <div ref="sentinel" class="sentinel"></div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useMainStore } from '@/stores/main.js'

const store = useMainStore()

const search       = ref('')
const searchAmount = ref('')
const activeType  = ref('all')
const sortField   = ref('date')
const sortDir     = ref(-1)
const limit       = ref(50)   // сколько показываем сейчас
const STEP        = 50        // сколько добавляем при скролле
const isLoadingMore = ref(false)
const sentinel    = ref(null) // ref на div-маркер внизу списка

const typeFilters = [
  { label: 'Все',        value: 'all'        },
  { label: 'Пополнения', value: 'Пополнение' },
  { label: 'Переводы',   value: 'Перевод'    },
  { label: 'Покупки',    value: 'Покупка'    },
  { label: 'Снятия',     value: 'Снятие'     },
]

const filtered = computed(() => {
  let list = store.transactions
  if (activeType.value !== 'all') list = list.filter(tx => tx.type === activeType.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(tx =>
      tx.detail?.toLowerCase().includes(q) || tx.type?.toLowerCase().includes(q)
    )
  }
  if (searchAmount.value !== '') {
    const amt = parseFloat(searchAmount.value)
    if (!isNaN(amt)) list = list.filter(tx => Math.abs(tx.amount) >= amt && Math.abs(tx.amount) < amt + 1000)
  }
  return [...list].sort((a, b) => {
    if (sortField.value === 'date')   return a.date.localeCompare(b.date) * sortDir.value
    if (sortField.value === 'amount') return (a.amount - b.amount) * sortDir.value
    if (sortField.value === 'tag') {
      const ta = store.tags.find(t => t.id === a.tag_id)?.name ?? 'Я'
      const tb = store.tags.find(t => t.id === b.tag_id)?.name ?? 'Я'
      return ta.localeCompare(tb, 'ru') * sortDir.value
    }
    return 0
  })
})

// Только видимый срез
const visibleTx = computed(() => filtered.value.slice(0, limit.value))

function resetLimit() {
  limit.value = STEP
}

function sortBy(field) {
  if (sortField.value === field) sortDir.value *= -1
  else { sortField.value = field; sortDir.value = -1 }
  resetLimit()
}

function sortIcon(field) {
  if (sortField.value !== field) return '↕'
  return sortDir.value === -1 ? '↓' : '↑'
}

async function changeTag(txId, tagId) {
  await store.updateTag(txId, tagId ? Number(tagId) : null)
}

function fmt(n) {
  return new Intl.NumberFormat('ru-KZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' ₸'
}

// ── Infinite scroll через IntersectionObserver ────────────────────────────────
let observer = null

function loadMore() {
  if (limit.value >= filtered.value.length) return
  isLoadingMore.value = true
  // Небольшая задержка чтобы браузер успел отрисовать
  setTimeout(() => {
    limit.value += STEP
    isLoadingMore.value = false
  }, 150)
}

onMounted(() => {
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) loadMore()
    },
    { rootMargin: '200px' }
  )
  if (sentinel.value) observer.observe(sentinel.value)
})

// sentinel появляется только когда есть транзакции — следим за ним
watch(sentinel, (el) => {
  if (el) observer?.observe(el)
})

onUnmounted(() => {
  observer?.disconnect()
})

// При смене фильтров сбрасываем limit
watch([search, searchAmount, activeType], resetLimit)
</script>

<style scoped>
.history-root { display: flex; flex-direction: column; min-height: 0; }
.page-title { font-size: 22px; font-weight: 600; letter-spacing: -0.4px; margin-bottom: 24px; }

.toolbar { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.search-row { display: flex; gap: 8px; }
.search {
  flex: 1; padding: 9px 14px; border: 1px solid #e0ded8;
  border-radius: 9px; font-size: 14px; background: #fff; outline: none;
}
.search:focus { border-color: #e42420; }
.search--amount { flex: 0 0 120px; }
.filters { display: flex; gap: 6px; flex-wrap: wrap; }
.chip { padding: 5px 13px; border-radius: 20px; font-size: 12px; border: 1px solid #ddd; background: #fff; color: #555; cursor: pointer; }
.chip--active { background: #e42420; border-color: #e42420; color: #fff; }

.loading-row { display: flex; align-items: center; gap: 10px; color: #888; font-size: 14px; padding: 40px 0; justify-content: center; }
.spinner { width: 20px; height: 20px; border: 2px solid #eee; border-top-color: #e42420; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.table-wrap {
  background: #fff; border: 1px solid #e8e6df; border-radius: 12px;
  overflow-x: auto; overflow-y: visible;
  -webkit-overflow-scrolling: touch;
}
.tx-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.tx-table th {
  background: #f7f6f2; padding: 10px 12px; text-align: left;
  font-size: 11px; font-weight: 500; color: #aaa; text-transform: uppercase;
  letter-spacing: .4px; border-bottom: 1px solid #e8e6df;
  position: sticky; top: 0; z-index: 1;
}
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { color: #555; }
.tx-table td { padding: 10px 12px; border-bottom: 1px solid #f0ede6; vertical-align: middle; }
.tx-table tr:last-child td { border-bottom: none; }
.tx-table tr:hover td { background: #faf9f6; }

.td-date   { color: #999; font-size: 12px; white-space: nowrap; }
.td-detail { color: #444; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.td-amount { font-weight: 500; white-space: nowrap; }
.pos { color: #1a7a45; } .neg { color: #c0392b; }

.tag-select { border: 1px solid #e8e6df; border-radius: 6px; font-size: 12px; padding: 3px 6px; background: #f7f6f2; color: #555; }

.badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 500; white-space: nowrap; }
.badge--Пополнение { background: #e8f5ef; color: #1a7a45; }
.badge--Покупка    { background: #fff0e8; color: #b85010; }
.badge--Перевод    { background: #e8eef8; color: #2c5fa8; }
.badge--Снятие     { background: #f8e8e8; color: #a82c2c; }
.badge--Разное     { background: #f0ede6; color: #888;    }

.deposit-flag {
  display: inline-flex; align-items: center; justify-content: center;
  width: 16px; height: 16px; border-radius: 50%;
  background: #e8eef8; color: #2c5fa8; font-size: 9px; font-weight: 600; margin-left: 6px;
}

.table-footer {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 12px; padding-bottom: 24px;
}
.tx-count { font-size: 12px; color: #aaa; }
.loading-more { display: flex; align-items: center; }

/* Sentinel невидим, просто маркер для observer */
.sentinel { height: 1px; }

.empty { text-align: center; color: #bbb; padding: 64px 0; font-size: 14px; }

@media (max-width: 680px) {
  .page-title { font-size: 18px; margin-bottom: 16px; }
  .search-row { flex-direction: column; }
  .search--amount { flex: 1; }
  .tx-table { font-size: 12px; min-width: 480px; }
  .tx-table th, .tx-table td { padding: 8px 6px; }
  .td-detail { max-width: 100px; }
  /* Дополнительный отступ снизу чтобы последняя строка таблицы
     не перекрывалась нижним меню (60px) + AI кнопка (52px) + зазор */
  .table-footer { padding-bottom: 8px; }
  .sentinel { height: 120px; } /* sentinel-запас для bottom nav */
}
</style>