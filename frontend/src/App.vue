<template>
  <div class="layout">

    <!-- Экран логина -->
    <div v-if="store.authChecked && !store.isLoggedIn" class="login-screen">
      <div class="login-card">
        <div class="login-logo"><div class="brand-mark"></div></div>
        <h1 class="login-title">Saldo</h1>
        <p class="login-sub">Трекер расходов по выпискам</p>
        <a :href="api.auth.loginUrl()" class="login-btn">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M17.1 9.2c0-.6-.1-1.2-.2-1.8H9v3.4h4.6c-.2 1-.8 1.9-1.7 2.4v2h2.7c1.6-1.5 2.5-3.6 2.5-6z" fill="#4285F4"/>
            <path d="M9 18c2.3 0 4.2-.7 5.6-2l-2.7-2c-.8.5-1.7.8-2.9.8-2.2 0-4.1-1.5-4.8-3.5H1.4v2.1C2.8 16.1 5.7 18 9 18z" fill="#34A853"/>
            <path d="M4.2 11.3c-.2-.5-.3-1-.3-1.5s.1-1 .3-1.5V6.2H1.4C.8 7.4.5 8.7.5 10s.3 2.6.9 3.8l2.8-2.5z" fill="#FBBC05"/>
            <path d="M9 3.6c1.2 0 2.3.4 3.2 1.3L14.8 2C13.3.7 11.3 0 9 0 5.7 0 2.8 1.9 1.4 4.7l2.8 2.1C4.9 5.1 6.8 3.6 9 3.6z" fill="#EA4335"/>
          </svg>
          Войти через Google
        </a>
      </div>
    </div>

    <!-- Загрузка при проверке авторизации -->
    <div v-else-if="!store.authChecked" class="auth-loading">
      <div class="spinner-lg"></div>
    </div>

    <!-- Основное приложение -->
    <template v-else>
      <nav class="sidebar">
        <div class="sidebar__brand">
          <div class="brand-mark"></div>
          <span class="brand-name">Saldo</span>
        </div>

        <ul class="nav-list">
          <li v-for="item in navItems" :key="item.to">
            <RouterLink :to="item.to" class="nav-link" :class="{ 'nav-link--active': $route.path === item.to }">
              <span class="nav-icon" v-html="item.icon"></span>
              <span>{{ item.label }}</span>
            </RouterLink>
          </li>
        </ul>

        <!-- Юзер внизу сайдбара -->
        <div class="sidebar__user">
          <img v-if="store.user?.avatar" :src="store.user.avatar" class="user-avatar" />
          <div v-else class="user-avatar user-avatar--placeholder">{{ initials }}</div>
          <div class="user-info">
            <span class="user-name">{{ store.user?.name }}</span>
            <button class="logout-btn" @click="store.logout()">Выйти</button>
          </div>
        </div>
      </nav>

      <!-- Мобильный bottom bar -->
      <nav class="bottom-nav">
        <RouterLink v-for="item in navItems" :key="item.to" :to="item.to"
          class="bottom-nav__item" :class="{ 'bottom-nav__item--active': $route.path === item.to }">
          <span v-html="item.icon"></span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <main class="content">
        <RouterView />
      </main>

      <!-- Плавающий AI чат -->
      <AiChat />
    </template>

  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { useMainStore } from '@/stores/main.js'
import { api } from '@/api/index.js'
import AiChat from '@/components/AiChat.vue'

const store = useMainStore()

const initials = computed(() =>
  store.user?.name?.split(' ').slice(0,2).map(w => w[0]).join('') ?? '?'
)

onMounted(async () => {
  await store.checkAuth()
  if (store.isLoggedIn) {
    await Promise.all([store.loadTransactions(), store.loadTags()])
  }
})

const navItems = [
  { to: '/',         label: 'Главная',    icon: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 7.5L9 2l7 5.5V16a1 1 0 01-1 1H3a1 1 0 01-1-1V7.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 17v-6h6v6" stroke="currentColor" stroke-width="1.4"/></svg>` },
  { to: '/history',  label: 'История',    icon: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="3" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 7h8M5 10h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>` },
  { to: '/stats',    label: 'Статистика', icon: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 14l4-5 4 3 4-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>` },
  { to: '/settings', label: 'Настройки',  icon: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 1v2M9 15v2M1 9h2M15 9h2M3.22 3.22l1.41 1.41M13.36 13.36l1.42 1.42M3.22 14.78l1.41-1.41M13.36 4.64l1.42-1.42" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>` },
]
</script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Golos Text', 'Segoe UI', system-ui, sans-serif;
  background: #f4f3ef;
  color: #1a1a18;
  -webkit-font-smoothing: antialiased;
}
a { text-decoration: none; color: inherit; }
button { font-family: inherit; cursor: pointer; }
input, select, textarea { font-family: inherit; }
</style>

<style scoped>
.layout { display: flex; min-height: 100vh; }

/* ── Login ── */
.login-screen {
  width: 100vw; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  background: #f4f3ef;
}
.login-card {
  background: #fff; border-radius: 20px; border: 1px solid #e8e6df;
  padding: 48px 40px; text-align: center; max-width: 340px; width: 90%;
  display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.login-logo { margin-bottom: 4px; }
.login-title { font-size: 24px; letter-spacing: -0.5px; }
.login-title strong { font-weight: 700; }
.login-sub { font-size: 14px; color: #aaa; margin-bottom: 8px; }
.login-btn {
  display: flex; align-items: center; gap: 10px;
  background: #fff; border: 1px solid #ddd; border-radius: 10px;
  padding: 11px 20px; font-size: 14px; font-weight: 500; color: #333;
  transition: background .12s; width: 100%; justify-content: center;
}
.login-btn:hover { background: #f7f6f2; }

/* ── Auth loading ── */
.auth-loading {
  width: 100vw; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
}
.spinner-lg {
  width: 36px; height: 36px;
  border: 3px solid #e8e6df; border-top-color: #e42420;
  border-radius: 50%; animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Sidebar ── */
.sidebar {
  width: 220px; flex-shrink: 0;
  background: #fff; border-right: 1px solid #e8e6df;
  display: flex; flex-direction: column;
  padding: 24px 12px; position: sticky; top: 0; height: 100vh;
}
.sidebar__brand {
  display: flex; align-items: center; gap: 10px;
  padding: 0 8px 20px; border-bottom: 1px solid #f0ede6; margin-bottom: 16px;
}
.brand-mark { width: 26px; height: 26px; background: #e42420; border-radius: 7px; flex-shrink: 0; }
.brand-name { font-size: 15px; letter-spacing: -0.3px; }
.brand-name strong { font-weight: 700; }

.nav-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
.nav-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 8px; font-size: 14px; color: #777;
  transition: background .12s, color .12s;
}
.nav-link:hover { background: #f4f3ef; color: #333; }
.nav-link--active { background: #fff4f4; color: #e42420; font-weight: 500; }
.nav-icon { display: flex; flex-shrink: 0; }

/* ── User block ── */
.sidebar__user {
  margin-top: auto; display: flex; align-items: center; gap: 10px;
  padding: 12px 8px; border-top: 1px solid #f0ede6;
}
.user-avatar {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; object-fit: cover;
}
.user-avatar--placeholder {
  background: #f0ede6; display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 600; color: #888;
}
.user-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.user-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.logout-btn {
  font-size: 11px; color: #bbb; background: none; border: none;
  padding: 0; text-align: left; cursor: pointer;
}
.logout-btn:hover { color: #e42420; }

/* ── Content ── */
.content { flex: 1; min-width: 0; padding: 32px; padding-bottom: 80px; }

/* ── Bottom nav ── */
.bottom-nav {
  display: none; position: fixed; bottom: 0; left: 0; right: 0;
  background: #fff; border-top: 1px solid #e8e6df; z-index: 100;
}
.bottom-nav__item {
  flex: 1; display: flex; flex-direction: column; align-items: center;
  gap: 3px; padding: 10px 4px 12px; font-size: 11px; color: #aaa;
  transition: color .12s;
}
.bottom-nav__item--active { color: #e42420; }

@media (max-width: 680px) {
  .sidebar { display: none; }
  .bottom-nav { display: flex; }
  /* 60px нижнее меню + 64px AI кнопка + 20px запас */
  .content { padding: 20px 16px 144px; }
}
</style>
