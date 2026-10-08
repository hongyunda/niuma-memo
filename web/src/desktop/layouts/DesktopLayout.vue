<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from '@/desktop/components/Sidebar.vue'
import CommandPalette from '@/desktop/components/CommandPalette.vue'

const route = useRoute()
const bare = computed(() => !!route.meta.bare)
const paletteOpen = ref(false)

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    paletteOpen.value = !paletteOpen.value
  }
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <template v-if="bare"><slot /></template>
  <div v-else class="dl">
    <Sidebar @search="paletteOpen = true" />
    <main class="dl-main"><slot /></main>
    <CommandPalette v-model:show="paletteOpen" />
  </div>
</template>

<style scoped>
.dl { display: flex; height: 100vh; overflow: hidden; background: var(--bg-page); }
.dl-main { flex: 1 1 auto; min-width: 0; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
</style>
