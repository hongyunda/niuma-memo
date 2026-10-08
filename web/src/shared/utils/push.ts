import { pushApi } from '@/shared/api'

function urlBase64ToUint8Array(base64: string) {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(b64)
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)))
}

export function pushSupported() {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

export function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || (navigator as unknown as { standalone?: boolean }).standalone === true
}

export function isIOS() {
  return /iP(hone|ad|od)/.test(navigator.userAgent)
}

export async function currentSubscription(): Promise<PushSubscription | null> {
  if (!pushSupported()) return null
  const reg = await navigator.serviceWorker.ready
  return reg.pushManager.getSubscription()
}

/** 申请通知权限并订阅，把订阅信息交给后端 */
export async function subscribePush(vapidPublic: string): Promise<PushSubscription> {
  if (!pushSupported()) throw new Error('当前浏览器不支持推送通知')
  if (!vapidPublic) throw new Error('服务端未配置 VAPID 公钥')
  const permission = await Notification.requestPermission()
  if (permission !== 'granted') throw new Error('你拒绝了通知权限，可在浏览器设置里重新开启')
  const reg = await navigator.serviceWorker.ready
  let sub = await reg.pushManager.getSubscription()
  if (!sub) {
    sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(vapidPublic) })
  }
  await pushApi.subscribe(sub.toJSON())
  return sub
}

export async function unsubscribePush() {
  const sub = await currentSubscription()
  if (!sub) return
  await pushApi.unsubscribe(sub.endpoint).catch(() => {})
  await sub.unsubscribe()
}
