// Этот код заставляет SW активироваться сразу, как только он загружен
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

// Мы просто перехватываем запросы и отправляем их сразу в сеть
// Никакого кэширования статики
self.addEventListener('fetch', (event) => {
  event.respondWith(fetch(event.request));
});