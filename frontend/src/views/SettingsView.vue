<template>
  <div>
    <h1 class="page-title">Настройки</h1>

    <!-- Теги -->
    <section class="section">
      <h2 class="section__title">Теги</h2>
      <div class="tag-list">
        <div v-for="tag in store.tags" :key="tag.id" class="tag-item">
          <span class="tag-color" :style="{ background: tag.color }"></span>
          <span class="tag-name">{{ tag.name }}</span>
          <span v-if="tag.is_system" class="tag-sys">системный</span>
          <button v-else class="tag-del" @click="removeTag(tag.id)">✕</button>
        </div>
      </div>
      <div class="add-tag">
        <input v-model="newName" class="input" placeholder="Название тега" maxlength="30" />
        <input v-model="newColor" type="color" class="color-picker" />
        <button class="btn-add" @click="addTag" :disabled="!newName.trim()">Добавить</button>
      </div>
    </section>

    <!-- AI подсказка -->
    <section class="section">
      <h2 class="section__title">Подсказка для AI</h2>
      <div class="prompt-card">
        <p class="prompt-hint">
          Укажи магазины или места которые AI неправильно классифицирует.
          Например: <em>«Bayan K., Серік — это магазины у дома, тег Покупки»</em>
        </p>
        <textarea
          v-model="aiPrompt"
          class="prompt-textarea"
          placeholder="Bayan K. — магазин, тег Покупки&#10;ИП Турсын — продукты, тег Покупки"
          maxlength="2000"
          rows="5"
        ></textarea>
        <div class="prompt-footer">
          <span class="prompt-chars">{{ aiPrompt.length }} / 2000</span>
          <button class="btn-save" @click="savePrompt" :disabled="promptSaving">
            {{ promptSaving ? 'Сохраняем...' : 'Сохранить' }}
          </button>
        </div>
      </div>
    </section>

    <!-- Данные -->
    <section class="section">
      <h2 class="section__title">Данные</h2>
      <div class="data-cards">

        <div class="data-card">
          <div class="data-card__info">
            <span class="data-card__title">Экспорт в JSON</span>
            <span class="data-card__sub">{{ store.transactions.length }} операций</span>
          </div>
          <button class="btn-action" @click="exportJSON">Скачать</button>
        </div>

        <div class="data-card">
          <div class="data-card__info">
            <span class="data-card__title">Импорт из JSON</span>
            <span class="data-card__sub">Восстановить резервную копию</span>
          </div>
          <button class="btn-action" @click="jsonInput?.click()">Загрузить</button>
          <input ref="jsonInput" type="file" accept=".json" style="display:none" @change="importJSON" />
        </div>

        <div class="data-card">
          <div class="data-card__info">
            <span class="data-card__title">Очистить транзакции</span>
            <span class="data-card__sub">Удалить все {{ store.transactions.length }} операций</span>
          </div>
          <button class="btn-action btn-danger" @click="showClearModal = true">Очистить</button>
        </div>

      </div>
    </section>

    <!-- Аккаунт -->
    <section class="section">
      <h2 class="section__title">Аккаунт</h2>
      <div class="user-card">
        <img v-if="store.user?.avatar" :src="store.user.avatar" class="user-pic" />
        <div class="user-card__info">
          <span class="user-card__name">{{ store.user?.name }}</span>
          <span class="user-card__email">{{ store.user?.email }}</span>
        </div>
        <button class="btn-logout" @click="startLogout">Выйти</button>
      </div>
    </section>

    <!-- О приложении -->
    <section class="section">
      <h2 class="section__title">О приложении</h2>
      <div class="about-card">
        <p class="about__line">Saldo — трекер расходов по банковским выпискам</p>
        <p class="about__line muted">Kaspi Gold · Freedom Finance</p>
        <a :href="supportBotUrl" target="_blank" class="bug-link">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M12 1.5L1 5.5l3.5 1.3 1.3 3.7 1.7-2.5 3 2 1.5-8.5z" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round"/>
          </svg>
          Сообщить о баге
        </a>
      </div>
    </section>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" class="toast">{{ toast }}</div>
    </Transition>

    <!-- ── Модалка: Очистить транзакции ──────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showClearModal" class="modal-overlay" @click.self="showClearModal = false">
        <div class="modal">
          <div class="modal__icon">🗑️</div>
          <h2 class="modal__title">Удалить всё?</h2>
          <p class="modal__text">
            Это удалит <strong>{{ store.transactions.length }} операций</strong> без возможности восстановления.<br/>
            Экспортируй JSON если хочешь сохранить данные.
          </p>
          <div class="modal__actions">
            <button class="modal__btn modal__btn--cancel" @click="showClearModal = false">Отмена</button>
            <button class="modal__btn modal__btn--danger" @click="doClear">Да, удалить всё</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Модалка: Выход (5 этапов) ─────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="logoutStep > 0" class="modal-overlay" @click.self="logoutStep = 0">
        <div class="modal" :class="'modal--step' + logoutStep">

          <template v-if="logoutStep === 1">
            <div class="modal__icon">🤔</div>
            <h2 class="modal__title">ТЫ ТОЧНО ЭТОГО ХОЧЕШЬ?</h2>
            <p class="modal__text">Выйти из аккаунта — это серьёзный шаг.<br/>Подумай ещё раз.</p>
            <div class="modal__actions">
              <button class="modal__btn modal__btn--cancel" @click="logoutStep = 0">Нет, я передумал</button>
              <button class="modal__btn modal__btn--warn" @click="logoutStep = 2">Да, хочу</button>
            </div>
          </template>

          <template v-if="logoutStep === 2">
            <div class="modal__icon">🌍</div>
            <h2 class="modal__title">А О ДЕТЯХ В АФРИКЕ ТЫ ПОДУМАЛ?</h2>
            <p class="modal__text">Они бы всё отдали чтобы иметь такой красивый трекер расходов.<br/>А ты вот так просто уходишь...</p>
            <div class="modal__actions">
              <button class="modal__btn modal__btn--cancel" @click="logoutStep = 0">Мне стыдно, остаюсь</button>
              <button class="modal__btn modal__btn--warn" @click="logoutStep = 3">Мне не стыдно</button>
            </div>
          </template>

          <template v-if="logoutStep === 3">
            <div class="modal__icon">😤</div>
            <h2 class="modal__title">НУ И ЧТО ТЫ БУДЕШЬ ДЕЛАТЬ БЕЗ МЕНЯ?</h2>
            <p class="modal__text">Опять в экселе всё считать?<br/>Или снова "я буду записывать в блокнот" и через 3 дня забросишь?</p>
            <div class="modal__actions">
              <button class="modal__btn modal__btn--cancel" @click="logoutStep = 0">Ладно ладно, остаюсь</button>
              <button class="modal__btn modal__btn--warn" @click="logoutStep = 4">В БЛОКНОТ, ДА!</button>
            </div>
          </template>

          <template v-if="logoutStep === 4">
            <div class="modal__icon">🫡</div>
            <h2 class="modal__title">ПОСЛЕДНИЙ ШАНС</h2>
            <p class="modal__text">
              Разработчик потратил на это приложение кучу денег, ночей и нервов.<br/>
              И вот ты — просто берёшь и уходишь.<br/><br/>
              Без слёз. Без цветов. Вот так.
            </p>
            <div class="modal__actions">
              <button class="modal__btn modal__btn--cancel" @click="logoutStep = 0">Прости, остаюсь 🥺</button>
              <button class="modal__btn modal__btn--danger" @click="logoutStep = 5">ВСЁ, ВЫХОЖУ</button>
            </div>
          </template>

          <template v-if="logoutStep === 5">
            <div class="modal__icon">👋</div>
            <h2 class="modal__title">Ну и ладно.</h2>
            <p class="modal__text">
              Данные сохранены. Возвращайся когда передумаешь.<br/><br/>
              <em>Мы всё равно тебя любим.</em>
            </p>
            <div class="modal__actions">
              <button class="modal__btn modal__btn--danger" @click="doLogout">Выйти</button>
            </div>
          </template>

        </div>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useMainStore } from '@/stores/main.js'
import { api } from '@/api/index.js'

const store          = useMainStore()
const newName        = ref('')
const newColor       = ref('#4a90d9')
const jsonInput      = ref(null)
const toast          = ref('')
const aiPrompt       = ref('')
const promptSaving   = ref(false)
const showClearModal = ref(false)
const logoutStep     = ref(0)
const supportBotUrl  = import.meta.env.VITE_SUPPORT_BOT_URL ?? '#'

onMounted(async () => {
  try {
    const res = await api.user.getPrompt()
    aiPrompt.value = res.ai_prompt ?? ''
  } catch (e) {
    console.warn('Не удалось загрузить AI промпт:', e)
  }
})

async function savePrompt() {
  promptSaving.value = true
  try {
    await api.user.savePrompt(aiPrompt.value)
    showToast('Подсказка сохранена')
  } catch (e) {
    showToast(e.message)
  } finally {
    promptSaving.value = false
  }
}

async function addTag() {
  if (!newName.value.trim()) return
  try {
    await store.addTag(newName.value.trim(), newColor.value)
    newName.value = ''
    showToast('Тег добавлен')
  } catch (e) {
    showToast(e.message)
  }
}

async function removeTag(id) {
  try {
    await store.deleteTag(id)
    showToast('Тег удалён')
  } catch (e) {
    showToast(e.message)
  }
}

function exportJSON() {
  // Строим map тегов id → name для быстрого поиска
  const tagById = {}
  store.tags.forEach(t => { tagById[t.id] = t.name })

  const data = {
    version: 2,
    exportedAt: new Date().toISOString(),
    user: {
      balance:         store.user?.balance         ?? 0,
      balance_freedom: store.user?.balance_freedom ?? 0,
      balance_other:   store.user?.balance_other   ?? 0,
    },
    tags: store.tags,
    transactions: store.transactions.map(tx => ({
      hash:       tx.hash,
      date:       tx.date,       // YYYY-MM-DD (из БД)
      amount:     tx.amount,
      type:       tx.type,
      detail:     tx.detail,
      bank:       tx.bank       ?? 'kaspi',
      is_deposit: tx.is_deposit ?? 0,
      _tagName:   tagById[tx.tag_id] ?? null,   // имя тега — не ID
    })),
  }
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href = url
  a.download = `saldo-export-${new Date().toISOString().slice(0,10)}.json`
  a.click()
  URL.revokeObjectURL(url)
}

function importJSON(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = async (ev) => {
    try {
      const data = JSON.parse(ev.target.result)
      if (!data.transactions || !Array.isArray(data.transactions)) {
        showToast('Ошибка: нет поля transactions')
        return
      }

      const tagById = {}
      // Теги из файла — для обратной совместимости с v1 (где _tagName мог отсутствовать)
      if (Array.isArray(data.tags)) {
        data.tags.forEach(t => { tagById[t.id] = t.name })
      }

      const txForImport = data.transactions.map(tx => {
        // Конвертируем дату YYYY-MM-DD → DD.MM.YY (backend сам обратно переконвертит)
        let kaspiDate = tx.date ?? ''
        if (kaspiDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
          const [y, m, d] = kaspiDate.split('-')
          kaspiDate = `${d}.${m}.${y.slice(2)}`
        }
        // v2 имеет _tagName напрямую, v1 — ищем по tag_id
        const tagName = tx._tagName ?? (tx.tag_id ? tagById[tx.tag_id] : null) ?? null
        return {
          hash:       tx.hash,
          date:       kaspiDate,
          amount:     tx.amount,
          type:       tx.type   ?? '',
          detail:     tx.detail ?? '',
          bank:       tx.bank   ?? 'kaspi',
          is_deposit: tx.is_deposit ?? 0,
          _tagName:   tagName,
        }
      })

      await store.importTransactions({
        transactions: txForImport,
        bank: 'import',
        period: '',
        name: '',
        summary: { balanceEnd: data.user?.balance ?? null },
      })
      showToast(`Импортировано ${txForImport.length} операций`)
    } catch (err) {
      console.error(err)
      showToast('Ошибка: неверный формат файла')
    }
  }
  reader.readAsText(file)
  e.target.value = ''
}

async function doClear() {
  showClearModal.value = false
  try {
    await store.clearTransactions()
    showToast('Транзакции удалены')
  } catch (e) {
    showToast(e.message)
  }
}

function startLogout() {
  logoutStep.value = 1
}

async function doLogout() {
  logoutStep.value = 0
  await store.logout()
}

function showToast(msg) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 2500)
}
</script>

<style scoped>
.btn-danger { background: #fff0f0; color: #c0392b; border-color: #fcc; }
.btn-danger:hover { background: #ffe0e0; }
.page-title { font-size: 22px; font-weight: 600; letter-spacing: -0.4px; margin-bottom: 24px; }
.section { margin-bottom: 36px; }
.section__title { font-size: 12px; font-weight: 600; color: #aaa; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }

.tag-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.tag-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border: 1px solid #e8e6df; border-radius: 9px; }
.tag-color { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.tag-name  { font-size: 14px; flex: 1; }
.tag-sys   { font-size: 11px; color: #ccc; }
.tag-del   { background: none; border: none; color: #ccc; font-size: 14px; padding: 0; line-height: 1; }
.tag-del:hover { color: #e42420; }

.add-tag { display: flex; gap: 8px; align-items: center; }
.input { flex: 1; padding: 9px 12px; border: 1px solid #e0ded8; border-radius: 9px; font-size: 14px; font-family: inherit; outline: none; }
.input:focus { border-color: #e42420; }
.color-picker { width: 36px; height: 36px; border: 1px solid #e0ded8; border-radius: 8px; padding: 2px; cursor: pointer; }
.btn-add { padding: 9px 16px; background: #e42420; color: #fff; border: none; border-radius: 9px; font-size: 14px; font-family: inherit; }
.btn-add:disabled { opacity: .4; cursor: default; }

.prompt-card { background: #fff; border: 1px solid #e8e6df; border-radius: 12px; padding: 16px; }
.prompt-hint { font-size: 13px; color: #888; margin-bottom: 12px; line-height: 1.5; }
.prompt-hint em { color: #555; font-style: normal; }
.prompt-textarea {
  width: 100%; box-sizing: border-box;
  padding: 10px 12px; border: 1px solid #e0ded8; border-radius: 9px;
  font-size: 13px; font-family: inherit; line-height: 1.5;
  resize: vertical; outline: none; color: #333;
}
.prompt-textarea:focus { border-color: #e42420; }
.prompt-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
.prompt-chars { font-size: 11px; color: #ccc; }
.btn-save { padding: 8px 18px; background: #e42420; color: #fff; border: none; border-radius: 9px; font-size: 13px; font-family: inherit; }
.btn-save:disabled { opacity: .5; cursor: default; }

.data-cards { display: flex; flex-direction: column; gap: 8px; }
.data-card { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border: 1px solid #e8e6df; border-radius: 10px; }
.data-card__info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.data-card__title { font-size: 14px; font-weight: 500; }
.data-card__sub   { font-size: 12px; color: #aaa; }
.btn-action { padding: 7px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; background: #fff; color: #333; font-family: inherit; white-space: nowrap; }
.btn-action:hover { background: #f4f3ef; }

.user-card { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border: 1px solid #e8e6df; border-radius: 12px; }
.user-pic  { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; }
.user-card__info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.user-card__name  { font-size: 14px; font-weight: 500; }
.user-card__email { font-size: 12px; color: #aaa; }
.btn-logout { padding: 7px 14px; border: 1px solid #fcc; border-radius: 8px; font-size: 13px; background: #fff0f0; color: #c0392b; font-family: inherit; }
.btn-logout:hover { background: #ffe0e0; }

.about-card { background: #fff; border: 1px solid #e8e6df; border-radius: 12px; padding: 18px 20px; display: flex; flex-direction: column; gap: 8px; }
.about__line { font-size: 14px; color: #555; }
.muted { font-size: 13px; color: #aaa; }
.bug-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #2c5fa8; margin-top: 4px; }
.bug-link:hover { text-decoration: underline; }

/* ── Модалки ─────────────────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,0.55);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.modal {
  background: #fff; border-radius: 20px;
  padding: 32px 24px; max-width: 340px; width: 100%;
  text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
  transition: background .3s;
}
.modal__icon { font-size: 52px; margin-bottom: 12px; line-height: 1; }
.modal__title {
  font-size: 17px; font-weight: 700; margin-bottom: 10px;
  color: #1a1a18; line-height: 1.3;
}
.modal__text {
  font-size: 14px; color: #666; line-height: 1.65;
  margin-bottom: 24px;
}
.modal__text em { color: #aaa; font-style: italic; }
.modal__actions { display: flex; flex-direction: column; gap: 8px; }
.modal__btn {
  width: 100%; padding: 13px; border-radius: 12px;
  font-size: 14px; font-weight: 500; font-family: inherit;
  border: none; cursor: pointer; transition: opacity .15s;
}
.modal__btn:hover { opacity: .85; }
.modal__btn--cancel { background: #f4f3ef; color: #555; }
.modal__btn--danger { background: #e42420; color: #fff; }
.modal__btn--warn   { background: #ff9500; color: #fff; }

.modal--step2 { background: #fffbf0; }
.modal--step3 { background: #fff0f8; }
.modal--step4 { background: #f0f4ff; }
.modal--step5 { background: #f0fff4; }

.toast {
  position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
  background: #1a1a18; color: #fff; padding: 10px 20px;
  border-radius: 20px; font-size: 13px; z-index: 300;
}
.toast-enter-active, .toast-leave-active { transition: opacity .2s, transform .2s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
.modal-enter-active, .modal-leave-active { transition: opacity .2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>