<script setup lang="ts">
import { ref, watch } from 'vue'
import { projectApi } from '@/shared/api'
import type { Project } from '@/shared/api/types'
import { feedback } from '@/shared/ui/feedback'
const props = defineProps<{ show: boolean; project?: Project | null }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'saved', p: Project): void }>()
const form = ref({ name: '', description: '' })
const saving = ref(false)
watch(() => props.show, v => {
  if (v) form.value = { name: props.project?.name || '', description: props.project?.description || '' }
})
async function save() {
  if (saving.value) return
  const payload = { name: form.value.name.trim(), description: form.value.description.trim() }
  if (!payload.name) return feedback.error('请输入项目名称')
  saving.value = true
  try {
    const p = props.project ? await projectApi.update(props.project.id, payload) : await projectApi.create(payload)
    emit('saved', p)
    emit('update:show', false)
    feedback.success(props.project ? '已保存' : '项目已创建')
  } finally { saving.value = false }
}
</script>
<template>
  <van-popup :show="show" position="bottom" round closeable safe-area-inset-bottom @update:show="emit('update:show', $event)">
    <div class="p-4">
      <h3 class="mt-0">{{ project ? '编辑项目' : '新建项目' }}</h3>
      <van-field v-model="form.name" label="名称" placeholder="项目名称" maxlength="100" />
      <van-field v-model="form.description" label="简介" placeholder="项目简介（可选）" type="textarea" rows="2" autosize />
      <van-button class="mt-4" block type="primary" :loading="saving" @click="save">{{ project ? '保存' : '创建' }}</van-button>
    </div>
  </van-popup>
</template>
