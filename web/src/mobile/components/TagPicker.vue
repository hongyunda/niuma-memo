<script setup lang="ts">
import { ref } from 'vue'
import { useAppStore } from '@/shared/stores/app'

const props = defineProps<{ modelValue: string[] }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string[]): void }>()
const app = useAppStore()
const show = ref(false)
const input = ref('')

function toggle(name: string) {
  const set = new Set(props.modelValue)
  set.has(name) ? set.delete(name) : set.add(name)
  emit('update:modelValue', [...set])
}

function add() {
  const name = input.value.trim().slice(0, 30)
  if (!name) return
  if (!props.modelValue.includes(name)) emit('update:modelValue', [...props.modelValue, name])
  input.value = ''
}
</script>

<template>
  <div class="flex flex-wrap gap-2 items-center" @click="show = true">
    <van-tag v-for="t in modelValue" :key="t" plain type="primary" size="medium" closeable @close.stop="toggle(t)">{{ t }}</van-tag>
    <span class="text-sm text-gray-400 cursor-pointer"><van-icon name="plus" /> 标签</span>
  </div>
  <van-popup v-model:show="show" position="bottom" round closeable safe-area-inset-bottom :style="{ maxHeight: '70%' }">
    <div class="p-4 pt-5">
      <h3 class="m-0 mb-3 text-base font-semibold">标签</h3>
      <div class="flex gap-2 mb-3">
        <input v-model="input" class="tag-input" placeholder="输入新标签，回车添加" maxlength="30" @keyup.enter="add" />
        <van-button size="small" type="primary" @click="add">添加</van-button>
      </div>
      <div class="flex flex-wrap gap-2">
        <van-tag
          v-for="t in app.tags"
          :key="t.id"
          size="large"
          :plain="!modelValue.includes(t.name)"
          type="primary"
          class="cursor-pointer"
          @click="toggle(t.name)"
        >{{ t.name }}<span v-if="t.note_count" class="opacity-60 ml-1">{{ t.note_count }}</span></van-tag>
        <span v-if="!app.tags.length" class="text-sm text-gray-400">还没有标签，上面输入一个吧</span>
      </div>
      <van-button block round type="primary" class="mt-5" @click="show = false">完成</van-button>
    </div>
  </van-popup>
</template>

<style scoped>
.tag-input { flex: 1; border: 1px solid #ebedf0; border-radius: 8px; padding: 6px 10px; font-size: 14px; outline: none; }
.tag-input:focus { border-color: var(--van-primary-color); }
</style>
