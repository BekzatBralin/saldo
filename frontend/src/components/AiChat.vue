<template>
  <!-- Кнопка -->
  <button class="chat-fab" @click="store.toggleChat()" :class="{ 'chat-fab--open': store.chatOpen }">
    <svg v-if="!store.chatOpen" width="22" height="22" viewBox="0 0 22 22" fill="none">
      <path d="M4 4h14a1 1 0 011 1v9a1 1 0 01-1 1H7l-4 3V5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    </svg>
    <svg v-else width="18" height="18" viewBox="0 0 18 18" fill="none">
      <path d="M2 2l14 14M16 2L2 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
  </button>

  <!-- Окно чата -->
  <Transition name="chat">
    <div v-if="store.chatOpen" class="chat-window">

      <div class="chat-header">
        <div class="chat-header__info">
          <div class="chat-ai-dot"></div>
          <span class="chat-title">ИИ помощник</span>
          <span class="chat-model">DeepSeek</span>
        </div>
        <button class="chat-clear" @click="clearChat" title="Очистить чат">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M2 3h10M5 3V2h4v1M3 3l.7 9h6.6L11 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      <div class="chat-messages" ref="messagesEl">
        <!-- Приветствие -->
        <div v-if="store.chatMessages.length === 0" class="chat-welcome">
          <p>Привет! Я могу помочь разобраться с твоими расходами.</p>
          <div class="chat-suggestions">
            <button v-for="s in suggestions" :key="s" class="suggestion" @click="send(s)">{{ s }}</button>
          </div>
        </div>

        <!-- Сообщения -->
        <div
          v-for="(msg, i) in store.chatMessages"
          :key="i"
          class="chat-msg"
          :class="'chat-msg--' + msg.role"
        >
          <div class="chat-bubble" v-html="formatMsg(msg.content)"></div>
        </div>

        <!-- Индикатор набора -->
        <div v-if="store.chatLoading" class="chat-msg chat-msg--assistant">
          <div class="chat-bubble chat-typing">
            <span></span><span></span><span></span>
          </div>
        </div>
      </div>

      <div class="chat-input-row">
        <input
          v-model="inputText"
          class="chat-input"
          placeholder="Напиши вопрос..."
          @keydown.enter.prevent="sendInput"
          :disabled="store.chatLoading"
        />
        <button class="chat-send" @click="sendInput" :disabled="!inputText.trim() || store.chatLoading">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M14 8H2M14 8l-5-5M14 8l-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

    </div>
  </Transition>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { useMainStore } from '@/stores/main.js'

const store = useMainStore()
const inputText  = ref('')
const messagesEl = ref(null)

const suggestions = [
  'Сколько я потратил в этом месяце?',
  'На что уходит больше всего?',
  'Как сэкономить?',
]

function sendInput() {
  if (!inputText.value.trim() || store.chatLoading) return
  send(inputText.value.trim())
  inputText.value = ''
}

function send(text) {
  store.sendChatMessage(text)
}

function clearChat() {
  store.chatMessages.length = 0
}

// Простое форматирование — переносы строк и жирный текст
function formatMsg(text) {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br>')
}

// Скролл вниз при новых сообщениях
watch(() => store.chatMessages.length, async () => {
  await nextTick()
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
})
</script>

<style scoped>
/* ── FAB кнопка ── */
.chat-fab {
  position: fixed; bottom: 88px; right: 24px;
  width: 52px; height: 52px; border-radius: 50%;
  background: #e42420; color: #fff; border: none;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(228,36,32,.35);
  transition: transform .15s, background .15s;
  z-index: 200;
}
.chat-fab:hover { transform: scale(1.06); }
.chat-fab--open { background: #555; box-shadow: 0 4px 12px rgba(0,0,0,.2); }

/* ── Окно чата ── */
.chat-window {
  position: fixed; bottom: 152px; right: 24px;
  width: 340px; height: 480px;
  background: #fff; border-radius: 16px;
  border: 1px solid #e8e6df;
  box-shadow: 0 8px 32px rgba(0,0,0,.12);
  display: flex; flex-direction: column;
  z-index: 199; overflow: hidden;
}

/* Анимация появления */
.chat-enter-active, .chat-leave-active { transition: opacity .2s, transform .2s; }
.chat-enter-from, .chat-leave-to { opacity: 0; transform: translateY(12px) scale(.97); }

/* ── Header ── */
.chat-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px; border-bottom: 1px solid #f0ede6;
}
.chat-header__info { display: flex; align-items: center; gap: 8px; }
.chat-ai-dot { width: 8px; height: 8px; border-radius: 50%; background: #4ecb81; }
.chat-title  { font-size: 14px; font-weight: 600; }
.chat-model  { font-size: 11px; color: #bbb; }
.chat-clear  { background: none; border: none; color: #bbb; display: flex; padding: 4px; }
.chat-clear:hover { color: #e42420; }

/* ── Messages ── */
.chat-messages {
  flex: 1; overflow-y: auto; padding: 16px;
  display: flex; flex-direction: column; gap: 10px;
  scroll-behavior: smooth;
}

.chat-welcome { color: #888; font-size: 13px; line-height: 1.6; }
.chat-suggestions { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.suggestion {
  background: #f4f3ef; border: none; border-radius: 8px;
  padding: 7px 12px; font-size: 12px; color: #555; text-align: left;
  cursor: pointer; transition: background .1s;
}
.suggestion:hover { background: #ece9e1; }

.chat-msg { display: flex; }
.chat-msg--user      { justify-content: flex-end; }
.chat-msg--assistant { justify-content: flex-start; }

.chat-bubble {
  max-width: 80%; padding: 9px 13px; border-radius: 12px;
  font-size: 13px; line-height: 1.55;
}
.chat-msg--user .chat-bubble {
  background: #e42420; color: #fff; border-bottom-right-radius: 4px;
}
.chat-msg--assistant .chat-bubble {
  background: #f4f3ef; color: #333; border-bottom-left-radius: 4px;
}

/* Typing indicator */
.chat-typing { display: flex; align-items: center; gap: 4px; padding: 12px 16px; }
.chat-typing span {
  width: 6px; height: 6px; border-radius: 50%; background: #bbb;
  animation: bounce .9s infinite;
}
.chat-typing span:nth-child(2) { animation-delay: .15s; }
.chat-typing span:nth-child(3) { animation-delay: .3s; }
@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30%            { transform: translateY(-5px); }
}

/* ── Input ── */
.chat-input-row {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 14px; border-top: 1px solid #f0ede6;
}
.chat-input {
  flex: 1; border: 1px solid #e0ded8; border-radius: 8px;
  padding: 8px 12px; font-size: 13px; outline: none;
  transition: border-color .15s;
}
.chat-input:focus { border-color: #e42420; }
.chat-send {
  width: 34px; height: 34px; border-radius: 8px;
  background: #e42420; color: #fff; border: none;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: opacity .15s;
}
.chat-send:disabled { opacity: .4; }

@media (max-width: 680px) {
  .chat-window {
    right: 12px; left: 12px; width: auto; bottom: 130px;
  }
  .chat-fab { bottom: 76px; right: 16px; }
}
</style>
