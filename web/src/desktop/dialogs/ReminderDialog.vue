<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Reminder, ReminderPayload } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { CHANNELS, REPEAT_TYPES, dayjs } from '@/shared/utils/format'
import { quickTimes, reminderDefaults, toReminderPayload } from '@/shared/composables/useReminderForm'
import { feedback } from '@/shared/ui/feedback'

const props = defineProps<{ show: boolean; reminder?: Reminder | null; defaultTitle?: string }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'save', payload: ReminderPayload): void }>()
const app = useAppStore()

const f = ref({ title: '', at: Date.now(), repeat: 0, advance: 0, channels: ['webpush'] as string[] })
const quick = computed(() => quickTimes())
const repeatOptions = Object.entries(REPEAT_TYPES).map(([v, l]) => ({ label: l, value: Number(v) }))
const channelOptions = computed(() => Object.entries(CHANNELS).map(([k, l]) => ({ value: k, label: l + (app.settings?.channels?.[k] === false ? '（未配置）' : '') })))

watch(() => props.show, async (v) => {
  if (!v) return
  const s = await app.fetchSettings().catch(() => null)
  const d = reminderDefaults(props.reminder, s?.push_config?.default_channels)
  f.value = { title: d.title || props.defaultTitle || '', at: d.at, repeat: d.repeat, advance: d.advance, channels: d.channels }
})

function save() {
  if (!f.value.at) return feedback.error('请选择时间')
  if (!f.value.channels.length) return feedback.error('至少选一个提醒方式')
  emit('save', toReminderPayload(f.value))
  emit('update:show', false)
}
</script>

<template>
  <n-modal :show="show" preset="card" :title="reminder ? '编辑提醒' : '设置提醒'" :style="{ width: '520px' }" :bordered="false" @update:show="emit('update:show', $event)">
    <div class="flex flex-col gap-4">
      <n-input v-model:value="f.title" placeholder="提醒标题（默认用记录标题）" maxlength="200" />
      <div class="flex flex-wrap gap-2">
        <n-button v-for="q in quick" :key="q.label" size="small" tertiary round @click="f.at = q.at.valueOf()">{{ q.label }}</n-button>
      </div>
      <n-date-picker v-model:value="f.at" type="datetime" format="yyyy-MM-dd HH:mm" :actions="['now', 'confirm']" style="width: 100%" />
      <div class="text-sm text-center" style="color: var(--c-primary)">{{ dayjs(f.at).format('M月D日 dddd HH:mm') }}</div>
      <n-form label-placement="left" label-width="72" :show-feedback="false" class="flex flex-col gap-3">
        <n-form-item label="重复"><n-select v-model:value="f.repeat" :options="repeatOptions" /></n-form-item>
        <n-form-item label="提前提醒">
          <n-radio-group v-model:value="f.advance" size="small">
            <n-radio-button :value="0">准时</n-radio-button>
            <n-radio-button :value="10">10 分钟</n-radio-button>
            <n-radio-button :value="30">30 分钟</n-radio-button>
            <n-radio-button :value="60">1 小时</n-radio-button>
            <n-radio-button :value="1440">1 天</n-radio-button>
          </n-radio-group>
        </n-form-item>
        <n-form-item label="提醒方式">
          <n-checkbox-group v-model:value="f.channels">
            <div class="flex flex-wrap gap-x-4 gap-y-2"><n-checkbox v-for="c in channelOptions" :key="c.value" :value="c.value" :label="c.label" /></div>
          </n-checkbox-group>
        </n-form-item>
      </n-form>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <n-button @click="emit('update:show', false)">取消</n-button>
        <n-button type="primary" @click="save">保存提醒</n-button>
      </div>
    </template>
  </n-modal>
</template>
