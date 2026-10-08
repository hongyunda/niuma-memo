<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAppStore } from '@/shared/stores/app'

const props = defineProps<{ show: boolean; current: number | null }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'select', pid: number | null): void }>()
const app = useAppStore()
const value = ref<number>(0)
const options = computed(() => app.activeProjects.map((p) => ({ label: p.name, value: p.id })))
watch(() => props.show, (v) => v && (value.value = props.current || app.activeProjects[0]?.id || 0))
function ok() {
  if (!value.value) return
  emit('select', value.value || null)
  emit('update:show', false)
}
</script>

<template>
  <n-modal :show="show" preset="card" title="移动到项目" :style="{ width: '420px' }" :bordered="false" @update:show="emit('update:show', $event)">
    <n-select v-model:value="value" :options="options" filterable placeholder="选择项目" />
    <template #footer>
      <div class="flex justify-end gap-2">
        <n-button @click="emit('update:show', false)">取消</n-button>
        <n-button type="primary" :disabled="!value" @click="ok">移动</n-button>
      </div>
    </template>
  </n-modal>
</template>
