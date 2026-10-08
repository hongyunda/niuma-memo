import { defineConfig, presetIcons, presetWind3 } from 'unocss'

export default defineConfig({
  presets: [
    presetWind3(),
    presetIcons({
      scale: 1.15,
      extraProperties: { display: 'inline-block', 'vertical-align': '-0.15em' },
    }),
  ],
  safelist: ['i-tabler-bold', 'i-tabler-italic', 'i-tabler-strikethrough', 'i-tabler-h-2', 'i-tabler-list', 'i-tabler-list-numbers', 'i-tabler-list-check', 'i-tabler-quote', 'i-tabler-code', 'i-tabler-link', 'i-tabler-photo', 'i-tabler-arrow-back-up', 'i-tabler-arrow-forward-up', 'i-tabler-highlight'],
  theme: {
    colors: {
      primary: '#2b7fff',
      success: '#16b364',
      warning: '#f59e0b',
      danger: '#ef4444',
      muted: '#86909c',
    },
    breakpoints: { sm: '640px', md: '768px', lg: '1024px', xl: '1280px' },
  },
  shortcuts: {
    'flex-center': 'flex items-center justify-center',
    'flex-between': 'flex items-center justify-between',
    'text-ellipsis': 'overflow-hidden text-ellipsis whitespace-nowrap',
    'safe-bottom': 'pb-[env(safe-area-inset-bottom)]',
    'safe-top': 'pt-[env(safe-area-inset-top)]',
    'card': 'bg-white rounded-xl shadow-sm',
  },
})
