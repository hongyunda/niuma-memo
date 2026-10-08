/**
 * 平台判定：启动时按视口决定一次，桌面与移动端是两套独立的 UI；
 * 跨过断点（拖窄 / 拉宽窗口）时整页刷新切换，避免两套组件树混跑。
 */
export const DESKTOP_MIN_WIDTH = 900

export const isDesktop: boolean = typeof window !== 'undefined' && window.matchMedia(`(min-width: ${DESKTOP_MIN_WIDTH}px)`).matches
export const platform: 'desktop' | 'mobile' = isDesktop ? 'desktop' : 'mobile'

export function watchPlatformChange() {
  const mq = window.matchMedia(`(min-width: ${DESKTOP_MIN_WIDTH}px)`)
  let timer: number | undefined
  mq.addEventListener('change', (e) => {
    if (e.matches === isDesktop) return
    clearTimeout(timer)
    timer = window.setTimeout(() => location.reload(), 300)
  })
}

export const isIOS = () => typeof navigator !== 'undefined' && /iP(hone|ad|od)/.test(navigator.userAgent)
export const isMac = () => typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)
export const modKey = () => (isMac() ? '⌘' : 'Ctrl')
