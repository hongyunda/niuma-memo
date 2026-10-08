<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import type { Reminder, ReminderPayload } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { useNoteForm } from '@/shared/composables/useNoteForm'
import { fmtDate } from '@/shared/utils/format'
import { recorderSupported } from '@/shared/utils/recorder'
import PageHeader from '@/mobile/components/PageHeader.vue'
import NoteEditor from '@/shared/components/NoteEditor.vue'
import AttachmentList from '@/mobile/components/AttachmentList.vue'
import ProjectPicker from '@/mobile/components/ProjectPicker.vue'
import ReminderPicker from '@/mobile/components/ReminderPicker.vue'
import ReminderItem from '@/mobile/components/ReminderItem.vue'
import AudioRecorder from '@/mobile/components/AudioRecorder.vue'
import SnoozeSheet from '@/mobile/components/SnoozeSheet.vue'

const route = useRoute()
const router = useRouter()
const app = useAppStore()

const noteId = computed(() => (route.name === 'note' ? Number(route.params.id) : 0))
const initialProjectId = computed(() => (route.query.project_id ? Number(route.query.project_id) : null))

const f = useNoteForm({
  noteId: () => noteId.value,
  initialProjectId: () => initialProjectId.value,
  onCreated: (n) => router.replace(`/notes/${n.id}`),
  onDeleted: () => router.back(),
  onLoadFailed: () => router.replace('/'),
})
const { form, attachments, reminders, meta, saving, isNew } = f

const editorRef = ref<InstanceType<typeof NoteEditor>>()
const showProject = ref(false)
const showReminder = ref(false)
const editingReminder = ref<Reminder | null>(null)
const showRecorder = ref(false)
const showMenu = ref(false)
const snoozeTarget = ref<Reminder | null>(null)

const menuActions = computed(() => [
  { name: form.value.is_pinned ? '取消置顶' : '置顶', key: 'pin' },
  { name: '移动到项目', key: 'move' },
  { name: form.value.is_archived ? '取消归档' : '归档', key: 'archive' },
  { name: '复制纯文本', key: 'copy' },
  { name: '删除', key: 'delete', color: '#ef4444' },
])
function onMenu(a: { key: string }) {
  showMenu.value = false
  if (a.key === 'pin') f.togglePin()
  else if (a.key === 'archive') f.toggleArchive()
  else if (a.key === 'move') showProject.value = true
  else if (a.key === 'copy') f.copyText(editorRef.value?.getHTML() || '')
  else if (a.key === 'delete') f.remove()
}

async function saveReminder(p: ReminderPayload) {
  await f.saveReminder(p, editingReminder.value)
  editingReminder.value = null
}

onBeforeRouteLeave(() => f.flush())
onMounted(async () => {
  await f.init()
  if (isNew.value) {
    await nextTick()
    editorRef.value?.focus()
  }
  app.fetchSettings().catch(() => null)
})
</script>

<template>
  <div class="page page--bare note-page">
    <PageHeader :title="isNew ? '新建记录' : '记录'" :fallback="form.project_id ? `/projects/${form.project_id}` : '/'">
      <template #right>
        <van-icon v-if="!isNew" name="ellipsis" size="20" class="cursor-pointer" @click="showMenu = true" />
        <van-button v-else size="small" type="primary" round :loading="saving" @click="f.save(false)">保存</van-button>
      </template>
    </PageHeader>

    <div class="body">
      <h1 v-if="form.title" class="note-title">{{ form.title }}</h1>
      <NoteEditor ref="editorRef" v-model="form.content" :note-id="f.currentId.value || undefined" :project-id="form.project_id" @uploaded="f.onUploaded" />

      <section class="section">
        <div class="section-title">
          <span>附件 <span v-if="attachments.length" class="text-gray-400 font-normal">{{ attachments.length }}</span></span>
          <div class="flex gap-3 text-primary text-sm">
            <span class="act" @click="f.addFiles('image/*')"><van-icon name="photo-o" /> 图片</span>
            <span class="act" @click="f.addFiles('image/*', 'environment')"><van-icon name="photograph" /> 拍照</span>
            <span v-if="recorderSupported()" class="act" @click="showRecorder = true"><van-icon name="volume-o" /> 录音</span>
            <span class="act" @click="f.addFiles()"><van-icon name="description" /> 文件</span>
          </div>
        </div>
        <AttachmentList :items="attachments" :asr-enabled="!!app.settings?.asr.enabled" @remove="f.removeAttachment" @update="f.onAttachmentUpdated" @insert-text="(t) => editorRef?.insertText(t)" />
        <div v-if="!attachments.length" class="hint">图片、录音、PDF / Word / Excel / PPT、视频都可以放这里；录音会自动转文字</div>
      </section>

      <section class="section">
        <div class="section-title">
          <span>提醒 <span v-if="reminders.length" class="text-gray-400 font-normal">{{ reminders.length }}</span></span>
          <span class="act text-primary text-sm" @click="editingReminder = null; showReminder = true"><van-icon name="bell" /> 添加提醒</span>
        </div>
        <div v-if="reminders.length" class="rounded-xl overflow-hidden border border-gray-100 divide-y divide-gray-100">
          <ReminderItem v-for="r in reminders" :key="r.id" :reminder="r" show-actions @done="f.reminderDone" @snooze="snoozeTarget = $event" @edit="(r) => { editingReminder = r; showReminder = true }" @delete="f.reminderDelete" />
        </div>
        <div v-else class="hint">到点通过浏览器通知 / Bark / 企业微信喊你</div>
      </section>

      <div v-if="meta" class="text-xs text-gray-400 px-1 py-4">创建于 {{ fmtDate(meta.created_at) }} · 最后编辑 {{ fmtDate(meta.updated_at) }}</div>
    </div>

    <ProjectPicker v-model:show="showProject" v-model="form.project_id" />
    <ReminderPicker v-model:show="showReminder" :reminder="editingReminder" :default-title="form.title" @save="saveReminder" />
    <AudioRecorder v-model:show="showRecorder" @recorded="f.onRecorded" />
    <SnoozeSheet :reminder="snoozeTarget" @close="snoozeTarget = null" @snoozed="snoozeTarget = null; f.refreshReminders()" />
    <van-action-sheet v-model:show="showMenu" :actions="menuActions" cancel-text="取消" close-on-click-action @select="onMenu" />
  </div>
</template>

<style scoped>
.note-page { background: #fff; min-height: 100dvh; }
.body { padding: 8px 14px calc(40px + var(--safe-bottom)); }
.note-title { margin: 8px 0 4px; font-size: 22px; line-height: 1.4; font-weight: 600; color: var(--t-1); overflow-wrap: anywhere; }
.section { margin-top: 18px; padding-top: 12px; border-top: 1px solid var(--b-2); }
.section-title { display: flex; align-items: center; justify-content: space-between; font-size: 15px; font-weight: 600; margin-bottom: 10px; }
.act { display: inline-flex; align-items: center; gap: 3px; font-weight: 400; }
.text-primary { color: var(--c-primary); }
.hint { font-size: 12.5px; color: var(--t-3); border: 1px dashed var(--b-1); border-radius: 10px; padding: 14px; text-align: center; line-height: 1.6; }
</style>
