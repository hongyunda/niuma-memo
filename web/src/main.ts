import '@/shared/styles/tokens.css'
import '@/shared/styles/global.css'
import 'virtual:uno.css'
import { registerSW } from 'virtual:pwa-register'
import { platform, watchPlatformChange } from '@/shared/platform'

registerSW({ immediate: true })
watchPlatformChange()

// 桌面端与移动端是两套独立的界面，按当前视口只加载其中一套
// 每个分支单独 await，避免生产构建将预加载依赖合并后只保留一端的 CSS。
async function boot() {
  if (platform === 'desktop') {
    const { mount } = await import('@/desktop/bootstrap')
    mount('#app')
  } else {
    const { mount } = await import('@/mobile/bootstrap')
    mount('#app')
  }
}

void boot()
