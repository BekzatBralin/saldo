import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView     from '@/views/HomeView.vue'
import HistoryView  from '@/views/HistoryView.vue'
import StatsView    from '@/views/StatsView.vue'
import SettingsView from '@/views/SettingsView.vue'

const routes = [
  { path: '/',          component: HomeView,     meta: { title: 'Главная'   } },
  { path: '/history',   component: HistoryView,  meta: { title: 'История'   } },
  { path: '/stats',     component: StatsView,    meta: { title: 'Статистика'} },
  { path: '/settings',  component: SettingsView, meta: { title: 'Настройки' } },
]

export default createRouter({
  history: createWebHashHistory(),
  routes,
})
