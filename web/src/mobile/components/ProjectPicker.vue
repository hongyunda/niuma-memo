<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAppStore } from '@/shared/stores/app'

defineProps<{ show: boolean; modelValue: number | null }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'update:modelValue', v: number | null): void }>()
const app = useAppStore()
const keyword = ref('')
const list = computed(() => app.activeProjects.filter((p) => !keyword.value || p.name.includes(keyword.value)))

function pick(id: number | null) {
  emit('update:modelValue', id)
  emit('update:show', false)
}
</script>

<template>
  <van-popup :show="show" position="bottom" round closeable safe-area-inset-bottom :style="{ maxHeight: '80%' }" @update:show="emit('update:show', $event)">
    <div class="p-3 pt-4">
      <h3 class="m-0 mb-2 px-1 text-base font-semibold">选择项目</h3>
      <van-search v-model="keyword" placeholder="搜索项目" shape="round" />
      <van-cell v-for="p in list" :key="p.id" :title="p.name" clickable @click="pick(p.id)">
        <template #icon><span class="dot mr-3 mt-1.5" style="background: var(--c-primary)" /></template>
        <template #right-icon><van-icon v-if="modelValue === p.id" name="success" color="#1989fa" /></template>
      </van-cell>
      <div v-if="!list.length" class="text-center text-gray-400 py-6 text-sm">没有匹配的项目</div>
    </div>
  </van-popup>
</template>
