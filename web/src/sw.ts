/// <reference lib="webworker" />
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching'
import { registerRoute, NavigationRoute } from 'workbox-routing'
import { CacheFirst, NetworkFirst } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'

declare let self: ServiceWorkerGlobalScope

self.skipWaiting()
cleanupOutdatedCaches()
precacheAndRoute(self.__WB_MANIFEST)

// SPA 路由：非 /api 的导航请求都回到 index.html
registerRoute(new NavigationRoute(createHandlerBoundToURL('/index.html'), { denylist: [/^\/api\//] }))

// 私有缩略图缓存一天（带 Cookie 的同源请求）
registerRoute(
  ({ url, request }) => url.pathname.startsWith('/api/files/') && url.pathname.endsWith('/thumb') && request.method === 'GET',
  new CacheFirst({ cacheName: 'thumbs', plugins: [new ExpirationPlugin({ maxEntries: 500, maxAgeSeconds: 86400 })] }),
)

// 项目 / 标签等列表接口：网络优先，断网时给缓存
registerRoute(
  ({ url, request }) => request.method === 'GET' && /^\/api\/(projects|tags|dashboard)$/.test(url.pathname),
  new NetworkFirst({ cacheName: 'api-lists', networkTimeoutSeconds: 8 }),
)

interface PushPayload {
  title?: string
  body?: string
  url?: string
  tag?: string
  icon?: string
}

self.addEventListener('push', (event) => {
  let data: PushPayload = {}
  try {
    data = event.data?.json() ?? {}
  } catch {
    data = { title: '提醒', body: event.data?.text() || '' }
  }
  const title = data.title || '牛马备忘录'
  const options: NotificationOptions & { renotify?: boolean; vibrate?: number[] } = {
    body: data.body || '',
    icon: data.icon || '/icons/icon-192.png',
    badge: '/icons/icon-192.png',
    tag: data.tag || 'memo',
    renotify: true,
    vibrate: [100, 50, 100],
    data: { url: data.url || '/' },
  }
  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const target: string = event.notification.data?.url || '/'
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if ('focus' in client) {
          client.navigate?.(target)
          return client.focus()
        }
      }
      return self.clients.openWindow(target)
    }),
  )
})
