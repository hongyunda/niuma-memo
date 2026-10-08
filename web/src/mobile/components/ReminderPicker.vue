<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { showToast } from 'vant'
import type { Reminder, ReminderPayload } from '@/shared/api/types'
import { CHANNELS, REPEAT_TYPES, dayjs } from '@/shared/utils/format'
import { useAppStore } from '@/shared/stores/app'

const props = defineProps<{ show: boolean; reminder?: Reminder | null; defaultTitle?: string }>()
const emit = defineEmits<{ (e: 'update:show', v: boolean): void; (e: 'save', payload: ReminderPayload): void }>()
const app = useAppStore()

const title = ref('')
const date = ref<string[]>([])
const time = ref<string[]>([])
const repeat = ref(0)
const channels = ref<string[]>(['webpush'])
const advance = ref(0)
const showRepeat = ref(false)

const quick = computed(() => {
  const now = dayjs()
  const tonight = now.hour(20).minute(0)
  return [
    { label: '1 小时后', at: now.add(1, 'hour').minute(0) },
    { label: '今晚 20:00', at: tonight.isAfter(now) ? tonight : tonight.add(1, 'day') },
    { label: '明早 9:00', at: now.add(1, 'day').hour(9).minute(0) },
    { label: '明天下午 14:00', at: now.add(1, 'day').hour(14).minute(0) },
    { label: '下周一 9:00', at: now.day(8).hour(9).minute(0) },
    { label: '下个月 1 号', at: now.add(1, 'month').date(1).hour(9).minute(0) },
  ]
})

const remindAt = computed(() => (date.value.length && time.value.length ? `${date.value.join('-')} ${time.value.join(':')}` : ''))
const minDate = new Date()
const maxDate = new Date(new Date().getFullYear() + 3, 11, 31)

watch(() => props.show, async (v) => {
  if (!v) return
  const settings = await app.fetchSettings().catch(() => null)
  const r = props.reminder
  const at = r ? dayjs(r.remind_at) : dayjs().add(1, 'hour').minute(0)
  title.value = r?.title || props.defaultTitle || ''
  date.value = [at.format('YYYY'), at.format('MM'), at.format('DD')]
  time.value = [at.format('HH'), at.format('mm')]
  repeat.value = r?.repeat_type ?? 0
  advance.value = r?.advance_minutes ?? 0
  const defaults = settings?.push_config?.default_channels
  channels.value = r ? r.channels.split(',').filter(Boolean) : defaults?.length ? defaults : ['webpush']
})

function pickQuick(at: dayjs.Dayjs) {
  date.value = [at.format('YYYY'), at.format('MM'), at.format('DD')]
  time.value = [at.format('HH'), at.format('mm')]
}

function save() {
  if (!remindAt.value) return showToast('请选择时间')
  if (!channels.value.length) return showToast('至少选一个提醒方式')
  emit('save', {
    title: title.value.trim(),
    remind_at: remindAt.value,
    repeat_type: repeat.value,
    channels: channels.value,
    advance_minutes: advance.value,
  })
  emit('update:show', false)
}

const repeatActions = Object.entries(REPEAT_TYPES).map(([v, name]) => ({ name, value: Number(v) }))
function pickRepeat(a: { value: number }) {
  repeat.value = a.value
  showRepeat.value = false
}

const channelHint = (key: string) => {
  const ok = app.settings?.channels?.[key]
  if (ok === undefined) return ''
  return ok ? '' : '（未配置）'
}
</script>

<template>
  <van-popup :show="show" position="bottom" round closeable safe-area-inset-bottom :style="{ maxHeight: '92%' }" @update:show="emit('update:show', $event)">
    <div class="p-4 pt-5">
      <h3 class="m-0 mb-3 text-base font-semibold">{{ reminder ? '编辑提醒' : '设置提醒' }}</h3>
      <van-field v-model="title" placeholder="提醒标题（默认用记录标题）" class="rounded-lg bg-gray-50 mb-3" />

      <div class="flex flex-wrap gap-2 mb-3">
        <van-tag v-for="q in quick" :key="q.label" size="large" plain type="primary" class="cursor-pointer" @click="pickQuick(q.at)">{{ q.label }}</van-tag>
      </div>

      <div class="picker-row">
        <van-date-picker v-model="date" :min-date="minDate" :max-date="maxDate" :show-toolbar="false" :columns-type="['year', 'month', 'day']" option-height="36" visible-option-num="5" class="flex-1" />
        <van-time-picker v-model="time" :show-toolbar="false" option-height="36" visible-option-num="5" class="flex-1" />
      </div>
      <div class="text-center text-sm text-primary my-2">{{ remindAt ? dayjs(remindAt).format('M月D日 dddd HH:mm') : '' }}</div>

      <van-cell-group inset class="!mx-0">
        <van-cell title="重复" is-link :value="REPEAT_TYPES[repeat]" @click="showRepeat = true" />
        <van-cell title="提前提醒">
          <template #value>
            <van-radio-group v-model="advance" direction="horizontal" class="justify-end">
              <van-radio :name="0" icon-size="16">准时</van-radio>
              <van-radio :name="10" icon-size="16">10 分钟</van-radio>
              <van-radio :name="60" icon-size="16">1 小时</van-radio>
            </van-radio-group>
          </template>
        </van-cell>
        <van-cell title="提醒方式">
          <template #label>
            <van-checkbox-group v-model="channels" direction="horizontal" class="mt-2 gap-y-2">
              <van-checkbox v-for="(label, key) in CHANNELS" :key="key" :name="key" shape="square" icon-size="16">{{ label }}<span class="text-gray-400">{{ channelHint(key) }}</span></van-checkbox>
            </van-checkbox-group>
          </template>
        </van-cell>
      </van-cell-group>

      <van-button type="primary" block round class="mt-4" @click="save">保存提醒</van-button>
    </div>

    <van-action-sheet v-model:show="showRepeat" :actions="repeatActions" cancel-text="取消" @select="pickRepeat" />
  </van-popup>
</template>

<style scoped>
.picker-row { display: flex; gap: 8px; background: #f7f8fa; border-radius: 12px; padding: 4px; }
.text-primary { color: var(--van-primary-color); }
</style>
