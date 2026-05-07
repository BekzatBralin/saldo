<template>
  <div>
    <h1 class="page-title">Статистика</h1>

    <div v-if="!store.transactions.length" class="empty">
      Нет данных — загрузите выписку на главной
    </div>

    <template v-else>

      <!-- Период -->
      <div class="period-bar">
        <div class="period-group">
          <label class="period-label">С</label>
          <input type="date" v-model="dateFrom" class="date-input" @change="load" />
        </div>
        <div class="period-group">
          <label class="period-label">По</label>
          <input type="date" v-model="dateTo" class="date-input" @change="load" />
        </div>
        <button class="btn-preset" @click="setPreset('month')">Этот месяц</button>
        <button class="btn-preset" @click="setPreset('prev')">Прошлый месяц</button>
        <button class="btn-preset" @click="setPreset('all')">Всё время</button>
      </div>

      <div v-if="loading" class="loading-row">
        <div class="spinner"></div> Считаем...
      </div>

      <template v-else-if="s">

        <!-- KPI -->
        <div class="kpi-grid">
          <div class="kpi">
            <span class="kpi__label">Прибыль</span>
            <span class="kpi__val" :class="s.summary.profit >= 0 ? 'green' : 'red'">
              {{ s.summary.profit >= 0 ? '+' : '' }}{{ fmt(s.summary.profit) }}
            </span>
            <span class="kpi__pct" :class="s.summary.profit >= 0 ? 'green' : 'red'">
              {{ s.summary.profit_pct }}% от доходов
            </span>
          </div>
          <div class="kpi">
            <span class="kpi__label">Доходы</span>
            <span class="kpi__val green">+{{ fmt(s.summary.income) }}</span>
          </div>
          <div class="kpi">
            <span class="kpi__label">Расходы</span>
            <span class="kpi__val red">−{{ fmt(s.summary.expense) }}</span>
          </div>
          <div class="kpi">
            <span class="kpi__label">Сохранено</span>
            <span class="kpi__val">{{ s.summary.saved_pct }}%</span>
            <span class="kpi__pct">{{ s.summary.tx_count }} операций</span>
          </div>
        </div>

        <!-- Депозиты -->
        <div v-if="s.summary.deposit_flow !== 0" class="deposit-note">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2"/>
            <path d="M7 6v4M7 4.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
          </svg>
          Депозитные операции: {{ fmt(Math.abs(s.summary.deposit_flow)) }} — не учтены в расчётах
        </div>

        <!-- График по дням -->
        <div v-if="s.by_day.length > 1" class="chart-card">
          <h2 class="block-title">Расходы по дням</h2>
          <div class="bar-chart">
            <div
              v-for="day in chartDays" :key="day.date"
              class="bar-chart__col"
              :title="day.date + ': −' + fmt(day.expense)"
            >
              <div class="bar-chart__bar-wrap">
                <div
                  class="bar-chart__bar"
                  :style="{ height: day.pct + '%' }"
                ></div>
              </div>
              <span class="bar-chart__label">{{ day.shortDate }}</span>
            </div>
          </div>
        </div>

        <!-- Круговая диаграмма + теги -->
        <div class="tags-grid">

          <!-- Расходы -->
          <div class="tag-block">
            <h2 class="block-title">Расходы по категориям</h2>
            <div v-if="expenseTags.length">
              <!-- SVG пирог -->
              <div class="pie-wrap">
                <svg viewBox="0 0 100 100" class="pie">
                  <g transform="rotate(-90 50 50)">
                    <circle
                      v-for="(seg, i) in expensePie" :key="i"
                      cx="50" cy="50" r="40"
                      fill="none"
                      :stroke="seg.color"
                      stroke-width="20"
                      :stroke-dasharray="seg.dash"
                      :stroke-dashoffset="seg.offset"
                    />
                  </g>
                  <text x="50" y="53" text-anchor="middle" class="pie-center">
                    {{ fmt(s.summary.expense) }}
                  </text>
                </svg>
              </div>
              <div class="tag-list">
                <div v-for="item in expenseTags" :key="item.tag_name" class="tag-row">
                  <div class="tag-row__top">
                    <span class="tag-dot" :style="{ background: item.tag_color }"></span>
                    <span class="tag-name">{{ item.tag_name }}</span>
                    <span class="tag-pct">{{ item.pct }}%</span>
                    <span class="tag-val red">−{{ fmt(item.expense) }}</span>
                  </div>
                  <div class="bar-track">
                    <div class="bar-fill" :style="{ width: item.pct + '%', background: item.tag_color + 'bb' }"></div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="no-data">Нет расходов</p>
          </div>

          <!-- Доходы -->
          <div class="tag-block">
            <h2 class="block-title">Доходы по категориям</h2>
            <div v-if="incomeTags.length">
              <div class="pie-wrap">
                <svg viewBox="0 0 100 100" class="pie">
                  <g transform="rotate(-90 50 50)">
                    <circle
                      v-for="(seg, i) in incomePie" :key="i"
                      cx="50" cy="50" r="40"
                      fill="none"
                      :stroke="seg.color"
                      stroke-width="20"
                      :stroke-dasharray="seg.dash"
                      :stroke-dashoffset="seg.offset"
                    />
                  </g>
                  <text x="50" y="53" text-anchor="middle" class="pie-center">
                    {{ fmt(s.summary.income) }}
                  </text>
                </svg>
              </div>
              <div class="tag-list">
                <div v-for="item in incomeTags" :key="item.tag_name" class="tag-row">
                  <div class="tag-row__top">
                    <span class="tag-dot" :style="{ background: item.tag_color }"></span>
                    <span class="tag-name">{{ item.tag_name }}</span>
                    <span class="tag-pct">{{ item.pct }}%</span>
                    <span class="tag-val green">+{{ fmt(item.income) }}</span>
                  </div>
                  <div class="bar-track">
                    <div class="bar-fill bar-fill--green" :style="{ width: item.pct + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="no-data">Нет доходов</p>
          </div>
        </div>

        <!-- Сравнение с прошлым месяцем -->
        <div v-if="prev" class="compare-card">
          <h2 class="block-title">Сравнение с прошлым месяцем</h2>
          <div class="compare-grid">
            <div class="compare-row">
              <span class="compare-label">Доходы</span>
              <span class="compare-cur green">+{{ fmt(s.summary.income) }}</span>
              <span class="compare-arrow" :class="s.summary.income >= prev.summary.income ? 'up' : 'down'">
                <template v-if="diffPct(s.summary.income, prev.summary.income) !== null">
                  {{ s.summary.income >= prev.summary.income ? '↑' : '↓' }}
                  {{ diffPct(s.summary.income, prev.summary.income) }}%
                </template>
                <template v-else>—</template>
              </span>
              <span class="compare-prev">{{ prev.summary.income ? fmt(prev.summary.income) : 'нет данных' }}</span>
            </div>
            <div class="compare-row">
              <span class="compare-label">Расходы</span>
              <span class="compare-cur red">−{{ fmt(s.summary.expense) }}</span>
              <span class="compare-arrow" :class="s.summary.expense <= prev.summary.expense ? 'up' : 'down'">
                <template v-if="diffPct(s.summary.expense, prev.summary.expense) !== null">
                  {{ s.summary.expense <= prev.summary.expense ? '↓' : '↑' }}
                  {{ diffPct(s.summary.expense, prev.summary.expense) }}%
                </template>
                <template v-else>—</template>
              </span>
              <span class="compare-prev">{{ prev.summary.expense ? fmt(prev.summary.expense) : 'нет данных' }}</span>
            </div>
            <div class="compare-row">
              <span class="compare-label">Прибыль</span>
              <span class="compare-cur" :class="s.summary.profit >= 0 ? 'green' : 'red'">
                {{ s.summary.profit >= 0 ? '+' : '' }}{{ fmt(s.summary.profit) }}
              </span>
              <span class="compare-arrow" :class="s.summary.profit >= prev.summary.profit ? 'up' : 'down'">
                <template v-if="diffPct(s.summary.profit, prev.summary.profit) !== null">
                  {{ s.summary.profit >= prev.summary.profit ? '↑' : '↓' }}
                  {{ diffPct(s.summary.profit, prev.summary.profit) }}%
                </template>
                <template v-else>—</template>
              </span>
              <span class="compare-prev">{{ prev.summary.profit ? fmt(prev.summary.profit) : 'нет данных' }}</span>
            </div>
          </div>
          <p class="compare-hint">← прошлый месяц</p>
        </div>

      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useMainStore } from '@/stores/main.js'

const store   = useMainStore()
const loading = ref(false)
const s       = ref(null)
const prev    = ref(null)

const today    = new Date()
const dateFrom = ref(new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0,10))
const dateTo   = ref(today.toISOString().slice(0,10))

async function load() {
  loading.value = true
  try {
    await store.loadStats(dateFrom.value, dateTo.value)
    s.value = store.stats

    // Загружаем прошлый месяц для сравнения
    const from = new Date(dateFrom.value)
    const prevTo   = new Date(from.getFullYear(), from.getMonth(), 0)
    const prevFrom = new Date(prevTo.getFullYear(), prevTo.getMonth(), 1)
    await store.loadStats(
      prevFrom.toISOString().slice(0,10),
      prevTo.toISOString().slice(0,10)
    )
    prev.value = store.stats

    // Восстанавливаем текущую статистику
    await store.loadStats(dateFrom.value, dateTo.value)
    s.value = store.stats
  } finally {
    loading.value = false
  }
}

function setPreset(preset) {
  if (preset === 'month') {
    dateFrom.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0,10)
    dateTo.value   = today.toISOString().slice(0,10)
  } else if (preset === 'prev') {
    const prevTo   = new Date(today.getFullYear(), today.getMonth(), 0)
    const prevFrom = new Date(prevTo.getFullYear(), prevTo.getMonth(), 1)
    dateFrom.value = prevFrom.toISOString().slice(0,10)
    dateTo.value   = prevTo.toISOString().slice(0,10)
  } else {
    const dates = store.transactions.map(tx => tx.date).sort()
    if (!dates.length) return
    dateFrom.value = dates[0]
    dateTo.value   = dates[dates.length - 1]
  }
  load()
}

// ── Теги ─────────────────────────────────────────────────────────────────────
const maxExpense = computed(() => Math.max(...(s.value?.by_tag.map(t => t.expense) ?? [1]), 1))
const maxIncome  = computed(() => Math.max(...(s.value?.by_tag.map(t => t.income)  ?? [1]), 1))

const expenseTags = computed(() =>
  (s.value?.by_tag ?? [])
    .filter(t => t.expense > 0)
    .sort((a,b) => b.expense - a.expense)
    .slice(0, 8)
    .map(t => ({ ...t, pct: Math.round(t.expense / maxExpense.value * 100) }))
)

const incomeTags = computed(() =>
  (s.value?.by_tag ?? [])
    .filter(t => t.income > 0)
    .sort((a,b) => b.income - a.income)
    .slice(0, 8)
    .map(t => ({ ...t, pct: Math.round(t.income / maxIncome.value * 100) }))
)

// ── Круговые диаграммы ────────────────────────────────────────────────────────
const CIRC = 2 * Math.PI * 40

const EXPENSE_PALETTE = ['#c0392b','#e07040','#e4a030','#888','#555','#a05030','#704080','#307090']
const INCOME_PALETTE  = ['#1a7a45','#2196a0','#2980b9','#16a085','#6d4fc2','#27ae60','#c07020','#1abc9c']

function buildPie(tags, totalKey, palette) {
  const total    = tags.reduce((s, t) => s + t[totalKey], 0)
  const rawColors = tags.map(t => (t.tag_color || '').toLowerCase().trim())
  const allSame   = rawColors.length > 1 && rawColors.every(c => c === rawColors[0])
  let offset = 0
  return tags.map((t, i) => {
    const pct   = total > 0 ? t[totalKey] / total : 0
    const dash  = pct * CIRC + ' ' + CIRC
    const color = (!t.tag_color || allSame) ? palette[i % palette.length] : t.tag_color
    const seg   = { color, dash, offset: -offset }
    offset += pct * CIRC
    return seg
  })
}

const expensePie = computed(() => buildPie(expenseTags.value, 'expense', EXPENSE_PALETTE))
const incomePie  = computed(() => buildPie(incomeTags.value,  'income',  INCOME_PALETTE))

// ── График по дням ────────────────────────────────────────────────────────────
const chartDays = computed(() => {
  const days = s.value?.by_day ?? []
  const max  = Math.max(...days.map(d => d.expense), 1)
  return days.map(d => ({
    ...d,
    pct:       Math.round(d.expense / max * 100),
    shortDate: d.date.slice(8), // DD
  }))
})

// ── Сравнение ─────────────────────────────────────────────────────────────────
function diffPct(cur, old) {
  if (!old) return null
  return Math.abs(Math.round((cur - old) / old * 100))
}

function fmt(n) {
  return new Intl.NumberFormat('ru-KZ', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(n) + ' ₸'
}

onMounted(load)
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 600; letter-spacing: -0.4px; margin-bottom: 24px; }

.period-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
.period-group { display: flex; align-items: center; gap: 6px; }
.period-label { font-size: 13px; color: #888; }
.date-input { padding: 7px 10px; border: 1px solid #e0ded8; border-radius: 8px; font-size: 13px; font-family: inherit; background: #fff; outline: none; }
.date-input:focus { border-color: #e42420; }
.btn-preset { padding: 7px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 12px; background: #fff; color: #555; font-family: inherit; white-space: nowrap; }
.btn-preset:hover { background: #f4f3ef; }

.loading-row { display: flex; align-items: center; gap: 10px; color: #888; font-size: 14px; padding: 32px 0; }
.spinner { width: 20px; height: 20px; border: 2px solid #eee; border-top-color: #e42420; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px; }
.kpi { background: #fff; border: 1px solid #e8e6df; border-radius: 12px; padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; }
.kpi__label { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: .5px; }
.kpi__val   { font-size: 18px; font-weight: 600; letter-spacing: -0.5px; }
.kpi__pct   { font-size: 12px; color: #aaa; }
.green { color: #1a7a45; } .red { color: #c0392b; }

.deposit-note {
  display: flex; align-items: center; gap: 6px; font-size: 12px; color: #888;
  margin-bottom: 20px; padding: 8px 12px; background: #f4f3ef; border-radius: 8px;
}

/* ── График по дням ─────────────────────────────────────────────────────────── */
.chart-card {
  background: #fff; border: 1px solid #e8e6df; border-radius: 12px;
  padding: 18px; margin-bottom: 16px;
}
.bar-chart {
  display: flex; align-items: flex-end; gap: 4px;
  height: 140px; overflow-x: auto;
  border-bottom: 1px solid #f0ede6;
}
.bar-chart__col {
  display: flex; flex-direction: column; align-items: center;
  gap: 4px; flex-shrink: 0; min-width: 22px; height: 100%;
  justify-content: flex-end;
}
.bar-chart__bar-wrap {
  width: 100%; display: flex; align-items: flex-end;
  height: 110px;
}
.bar-chart__bar {
  width: 100%; background: #e42420bb; border-radius: 3px 3px 0 0;
  transition: height .3s; min-height: 3px;
}
.bar-chart__bar:hover { background: #e42420; }
.bar-chart__label { font-size: 9px; color: #aaa; padding-top: 4px; }

/* ── Теги + пирог ────────────────────────────────────────────────────────────── */
.tags-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
@media (max-width: 600px) { .tags-grid { grid-template-columns: 1fr; } }

.tag-block { background: #fff; border: 1px solid #e8e6df; border-radius: 12px; padding: 18px; }
.block-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 14px; }

.pie-wrap { display: flex; justify-content: center; margin-bottom: 16px; }
.pie { width: 140px; height: 140px; }
.pie-center { font-size: 8px; fill: #555; font-weight: 600; }

.tag-list { display: flex; flex-direction: column; gap: 10px; }
.tag-row { display: flex; flex-direction: column; gap: 4px; }
.tag-row__top { display: flex; align-items: center; gap: 7px; }
.tag-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.tag-name { font-size: 13px; color: #333; flex: 1; }
.tag-pct  { font-size: 11px; color: #bbb; }
.tag-val  { font-size: 13px; font-weight: 500; white-space: nowrap; }
.bar-track { height: 3px; background: #f0ede6; border-radius: 2px; overflow: hidden; }
.bar-fill  { height: 100%; border-radius: 2px; transition: width .3s; background: #e07070; }
.bar-fill--green { background: #4ecb81; }
.no-data { font-size: 13px; color: #bbb; text-align: center; padding: 20px 0; }

/* ── Сравнение ───────────────────────────────────────────────────────────────── */
.compare-card {
  background: #fff; border: 1px solid #e8e6df; border-radius: 12px;
  padding: 18px; margin-bottom: 16px;
}
.compare-grid { display: flex; flex-direction: column; gap: 12px; margin-bottom: 8px; }
.compare-row  { display: grid; grid-template-columns: 80px 1fr auto 1fr; align-items: center; gap: 8px; }
.compare-label { font-size: 13px; color: #888; }
.compare-cur   { font-size: 14px; font-weight: 600; }
.compare-prev  { font-size: 13px; color: #bbb; text-align: right; }
.compare-arrow { font-size: 12px; font-weight: 600; text-align: center; }
.compare-arrow.up   { color: #1a7a45; }
.compare-arrow.down { color: #c0392b; }
.compare-hint { font-size: 11px; color: #ccc; text-align: right; margin-top: 4px; }

.empty { text-align: center; color: #bbb; padding: 64px 0; font-size: 14px; }
</style>