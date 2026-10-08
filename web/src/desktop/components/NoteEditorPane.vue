<script setup lang="ts">
import { computed, h, nextTick, onMounted, ref, watch } from 'vue'
import type { NoteDetail, Reminder, ReminderPayload } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { useNoteForm } from '@/shared/composables/useNoteForm'
import { recorderSupported } from '@/shared/utils/recorder'
import NoteEditor from '@/shared/components/NoteEditor.vue'
import AttachmentPanel from './AttachmentPanel.vue'
import ReminderRow from './ReminderRow.vue'
import ReminderDialog from '@/desktop/dialogs/ReminderDialog.vue'
import RecorderDialog from '@/desktop/dialogs/RecorderDialog.vue'
import MoveDialog from '@/desktop/dialogs/MoveDialog.vue'
import { reminderApi } from '@/shared/api'

const props = withDefaults(defineProps<{ noteId: number; initialProjectId?: number | null }>(), { initialProjectId: null })
const emit = defineEmits<{ (e: 'created', n: NoteDetail): void; (e: 'saved', n: NoteDetail): void; (e: 'deleted', id: number): void; (e: 'close'): void }>()
const app = useAppStore()

const f = useNoteForm({
  noteId: () => props.noteId,
  initialProjectId: () => props.initialProjectId,
  onCreated: (n) => emit('created', n),
  onSaved: (n) => emit('saved', n),
  onDeleted: (id) => emit('deleted', id),
  onLoadFailed: () => emit('close'),
})
const { form, attachments, reminders, saving, isNew } = f

const editorRef = ref<InstanceType<typeof NoteEditor>>()
const showReminder = ref(false)
const showReminderList = ref(false)
const editingReminder = ref<Reminder | null>(null)
const showRecorder = ref(false)
const showMove = ref(false)
const dragOver = ref(false)

const icon = (cls: string) => () => h('i', { class: cls })
const menu = computed(() => [
  { label: form.value.is_pinned ? '取消置顶' : '置顶', key: 'pin', icon: icon('i-tabler-pin') },
  { label: '移动到项目', key: 'move', icon: icon('i-tabler-arrows-exchange') },
  { label: form.value.is_archived ? '取消归档' : '归档', key: 'archive', icon: icon('i-tabler-archive') },
  { label: '复制纯文本', key: 'copy', icon: icon('i-tabler-copy') },
  { type: 'divider', key: 'd' },
  { label: '删除', key: 'delete', icon: icon('i-tabler-trash'), props: { style: 'color:#ef4444' } },
])

function onMenu(key: string) {
  if (key === 'pin') f.togglePin()
  else if (key === 'archive') f.toggleArchive()
  else if (key === 'move') showMove.value = true
  else if (key === 'copy') f.copyText(editorRef.value?.getHTML() || '')
  else if (key === 'delete') f.remove()
}

async function saveReminder(p: ReminderPayload) {
  await f.saveReminder(p, editingReminder.value)
  editingReminder.value = null
}
function openReminder(r: Reminder | null = null) {
  editingReminder.value = r
  showReminderList.value = false
  showReminder.value = true
}
async function snooze(r: Reminder, minutes: number) {
  await reminderApi.snooze(r.id, minutes)
  f.refreshReminders()
}

async function onDrop(e: DragEvent) {
  dragOver.value = false
  const files = Array.from(e.dataTransfer?.files || [])
  if (files.length) await f.uploadMany(files)
}

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
    e.preventDefault()
    f.save(false)
  }
}

async function close() {
  await f.flush()
  emit('close')
}

watch(() => props.noteId, async (id) => {
  showReminderList.value = false
  if (!id) {
    await nextTick()
    editorRef.value?.focus()
  }
})

onMounted(async () => {
  app.fetchSettings().catch(() => null)
  await f.init()
  if (!props.noteId) {
    await nextTick()
    editorRef.value?.focus()
  }
})

defineExpose({ flush: f.flush })
</script>

<template>
  <div class="ep" :class="{ over: dragOver }" @keydown="onKeydown" @dragover.prevent="dragOver = true" @dragleave="dragOver = false" @drop.capture="dragOver = false" @drop.prevent="onDrop">
    <div class="ep-bar">
      <h1 class="ep-title" :title="form.title || '新记录'">{{ form.title || '新记录' }}</h1>
      <div class="ep-actions">
        <n-button v-if="isNew" size="small" type="primary" :loading="saving" @click="f.save(false)">保存</n-button>
        <n-dropdown v-else :options="menu" trigger="click" placement="bottom-end" @select="onMenu">
          <n-button size="small" quaternary circle title="更多操作"><template #icon><i class="i-tabler-dots" /></template></n-button>
        </n-dropdown>
        <n-tooltip><template #trigger><n-button size="small" quaternary circle @click="close"><template #icon><i class="i-tabler-x" /></template></n-button></template>关闭 (Esc)</n-tooltip>
      </div>
    </div>

    <div class="ep-body">
      <NoteEditor ref="editorRef" v-model="form.content" :note-id="f.currentId.value || undefined" :project-id="form.project_id" @uploaded="f.onUploaded" />
    </div>

    <section class="ep-attachments" aria-label="附件">
        <div class="ep-section-head">
          <span>附件 <em v-if="attachments.length">{{ attachments.length }}</em></span>
          <div class="ep-attachment-actions">
            <n-popover v-if="reminders.length" v-model:show="showReminderList" trigger="click" placement="top-end" :width="400" :style="{ maxWidth: 'calc(100vw - 32px)' }">
              <template #trigger>
                <n-button size="tiny" quaternary><template #icon><i class="i-tabler-bell-plus" /></template>添加提醒 <em>{{ reminders.length }}</em></n-button>
              </template>
              <div class="ep-reminder-list">
                <ReminderRow v-for="r in reminders" :key="r.id" :reminder="r" show-actions @done="f.reminderDone" @snooze="snooze" @edit="openReminder" @delete="f.reminderDelete" />
              </div>
              <n-button class="ep-reminder-add" size="small" block @click="openReminder()"><template #icon><i class="i-tabler-plus" /></template>添加提醒</n-button>
            </n-popover>
            <n-button v-else size="tiny" quaternary @click="openReminder()"><template #icon><i class="i-tabler-bell-plus" /></template>添加提醒</n-button>
            <n-button size="tiny" quaternary @click="f.addFiles('image/*')"><template #icon><i class="i-tabler-photo" /></template>图片</n-button>
            <n-button v-if="recorderSupported()" size="tiny" quaternary @click="showRecorder = true"><template #icon><i class="i-tabler-microphone" /></template>录音</n-button>
            <n-button size="tiny" quaternary @click="f.addFiles()"><template #icon><i class="i-tabler-paperclip" /></template>文件</n-button>
          </div>
        </div>
        <div class="ep-attachment-content">
          <AttachmentPanel :items="attachments" :asr-enabled="!!app.settings?.asr.enabled" @remove="f.removeAttachment" @update="f.onAttachmentUpdated" @insert-text="(t) => editorRef?.insertText(t)" />
          <div v-if="!attachments.length" class="ep-drop">拖入文件或粘贴图片，录音会自动转文字</div>
        </div>
    </section>

    <ReminderDialog v-model:show="showReminder" :reminder="editingReminder" :default-title="form.title" @save="saveReminder" />
    <RecorderDialog v-model:show="showRecorder" @recorded="f.onRecorded" />
    <MoveDialog v-model:show="showMove" :current="form.project_id" @select="(pid) => (form.project_id = pid)" />
  </div>
</template>

<style scoped>
.ep { position: relative; display: flex; flex-direction: column; height: 100%; min-height: 0; overflow: hidden; background: #fff; }
.ep.over::after { content: '松开上传到这条记录'; position: absolute; inset: 8px; border: 2px dashed var(--c-primary); border-radius: 12px; background: rgba(43, 127, 255, 0.06); display: flex; align-items: center; justify-content: center; color: var(--c-primary); font-size: 15px; pointer-events: none; z-index: 5; }
.ep-bar { flex: 0 0 auto; z-index: 3; display: flex; align-items: center; gap: 16px; min-height: 52px; padding: 10px 2.5%; background: #fff; border-bottom: 1px solid var(--b-2); }
.ep-title { flex: 1; min-width: 0; margin: 0; font-size: 18px; line-height: 1.5; font-weight: 600; color: var(--t-1); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ep-actions { flex: 0 0 auto; display: flex; align-items: center; gap: 4px; }
.ep-body { flex: 1; min-height: 0; display: flex; width: 95%; margin: 0 auto; padding: 8px 0 0; }
.ep-body :deep(.note-editor) { flex: 1; min-width: 0; min-height: 0; display: flex; flex-direction: column; }
.ep-body :deep(.toolbar) { flex: 0 0 auto; }
.ep-body :deep(.editor-content) { flex: 1; min-height: 0; overflow-y: auto; }
.ep-body :deep(.tiptap) { min-height: 100%; padding: 10px 0 18px; }
.ep-attachments { flex: 0 0 auto; width: 95%; margin: 12px auto 16px; padding: 12px 16px; border: 1px solid var(--b-1); border-radius: 12px; background: #fff; box-shadow: 0 4px 18px rgb(0 0 0 / 5%); }
.ep-section-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; font-weight: 600; margin-bottom: 8px; }
.ep-section-head em { font-style: normal; font-weight: 400; color: var(--t-3); margin-left: 2px; }
.ep-attachment-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-left: auto; }
.ep-attachment-content { max-height: min(28vh, 240px); overflow-y: auto; }
.ep-attachment-content :deep(.ap-grid) { grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); }
.ep-attachment-content :deep(.ap-img) { max-height: 88px; }
.ep-drop { font-size: 12px; color: var(--t-3); border: 1px dashed var(--b-1); border-radius: 8px; padding: 10px; text-align: center; }
.ep-reminder-list { max-height: 280px; overflow-y: auto; }
.ep-reminder-list :deep(.rr + .rr) { border-top: 1px solid var(--b-2); }
.ep-reminder-add { margin-top: 8px; }
</style>
