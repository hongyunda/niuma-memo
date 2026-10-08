<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/shared/api'
import { feedback } from '@/shared/ui/feedback'

defineProps<{ show: boolean }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void }>()
const f = ref({ old_password: '', new_password: '', confirm: '' })
const saving = ref(false)

async function save() {
  if (f.value.new_password.length < 6) return feedback.error('新密码至少 6 位')
  if (f.value.new_password !== f.value.confirm) return feedback.error('两次输入的新密码不一致')
  saving.value = true
  try {
    await authApi.password(f.value.old_password, f.value.new_password)
    feedback.success('密码已修改')
    f.value = { old_password: '', new_password: '', confirm: '' }
    emit('update:show', false)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <n-modal :show="show" preset="card" title="修改密码" :style="{ width: '420px' }" :bordered="false" @update:show="emit('update:show', $event)">
    <n-form label-placement="left" label-width="72" :show-feedback="false" class="flex flex-col gap-4">
      <n-form-item label="原密码"><n-input v-model:value="f.old_password" type="password" show-password-on="click" /></n-form-item>
      <n-form-item label="新密码"><n-input v-model:value="f.new_password" type="password" show-password-on="click" placeholder="至少 6 位，建议 12 位以上" /></n-form-item>
      <n-form-item label="确认"><n-input v-model:value="f.confirm" type="password" show-password-on="click" @keydown.enter="save" /></n-form-item>
    </n-form>
    <template #footer>
      <div class="flex justify-end gap-2">
        <n-button @click="emit('update:show', false)">取消</n-button>
        <n-button type="primary" :loading="saving" @click="save">确认修改</n-button>
      </div>
    </template>
  </n-modal>
</template>
