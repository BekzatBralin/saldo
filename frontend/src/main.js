import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { GlobalWorkerOptions } from 'pdfjs-dist'
import workerSrc from 'pdfjs-dist/build/pdf.worker.mjs?url'

import App from './App.vue'
import router from './router'

GlobalWorkerOptions.workerSrc = workerSrc

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(reg => console.log('Service Worker зарегистрирован!', reg))
      .catch(err => console.log('Ошибка регистрации SW', err));
  });
}