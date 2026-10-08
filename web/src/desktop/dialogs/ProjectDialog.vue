<script setup lang="ts">
import { ref, watch } from 'vue'
import { projectApi } from '@/shared/api'
import type { Project } from '@/shared/api/types'
import { feedback } from '@/shared/ui/feedback'

const props = defineProps<{ show: boolean; project?: Project | null }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'saved', p: Project): void }>()
const form = ref({ name: '', description: '' })
const saving = ref(false)
watch(() => props.show, (v) => {
  if (v) form.value = { name: props.project?.name || '', description: props.project?.description || '' }
})
async function save() {
  if (saving.value) return
  const payload = { name: form.value.name.trim(), description: form.value.description.trim() }
  if (!payload.name) return feedback.error('请输入项目名称')
  saving.value = true
  try {
    const p = props.project ? await projectApi.update(props.project.id, payload) : await projectApi.create(payload)
    feedback.success(props.project ? '已保存' : '项目已创建')
    emit('saved', p)
    emit('update:show', false)
  } finally { saving.value = false }
}
</script>

<template>
  <n-modal :show="show" preset="card" :title="project ? '编辑项目' : '新建项目'" :style="{ width: '480px', maxWidth: 'calc(100vw - 32px)' }" :bordered="false" @update:show="emit('update:show', $event)">
    <n-form label-placement="left" label-width="56" :show-feedback="false" class="flex flex-col gap-4">
      <n-form-item label="名称"><n-input v-model:value="form.name" placeholder="项目名称" maxlength="100" @keydown.enter="save" /></n-form-item>
      <n-form-item label="简介"><n-input v-model:value="form.description" type="textarea" :autosize="{ minRows: 2, maxRows: 5 }" placeholder="项目简介（可选）" /></n-form-item>
    </n-form>
    <template #footer>
      <div class="flex justify-end gap-2">
        <n-button @click="emit('update:show', false)">取消</n-button>
        <n-button type="primary" :loading="saving" @click="save">{{ project ? '保存' : '创建' }}</n-button>
      </div>
    </template>
  </n-modal>
</template>
