import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { noteApi, reminderApi } from '@/shared/api'
import type { Attachment, NoteDetail, NotePayload, Reminder, ReminderPayload, TitleStatus } from '@/shared/api/types'
import { useAppStore } from '@/shared/stores/app'
import { feedback } from '@/shared/ui/feedback'
import { pickFiles, uploadFile } from '@/shared/utils/upload'
import type { RecordingResult } from '@/shared/utils/recorder'

export interface NoteFormOptions {
  noteId: () => number
  initialProjectId?: () => number | null | undefined
  onCreated?: (n: NoteDetail) => void
  onSaved?: (n: NoteDetail) => void
  onDeleted?: (id: number) => void
  onLoadFailed?: () => void
}

/**
 * 记录表单的全部业务逻辑：加载、自动保存、附件、提醒、菜单操作。
 * 桌面端右栏面板和移动端整页共用，各自只写模板。
 */
export function useNoteForm(opts: NoteFormOptions) {
  const app = useAppStore()
  const currentId = ref(opts.noteId())
  const loading = ref(false)
  const saving = ref(false)
  const dirty = ref(false)
  const savedAt = ref('')
  const skip = ref(false)
  const titleStatus = ref<TitleStatus>('idle')
  let revision = 0
  let alive = true
  let saveTask: Promise<NoteDetail | null> | null = null
  let titleTimer: number | undefined

  const defaults = () => {
    const pid = opts.initialProjectId?.() ?? null
    return {
      project_id: pid as number | null,
      title: '',
      content: '',
      tags: [] as string[],
      is_pinned: 0,
      is_archived: 0,
    }
  }
  const form = ref(defaults())
  const attachments = ref<Attachment[]>([])
  const reminders = ref<Reminder[]>([])
  const meta = ref<{ created_at: string; updated_at: string } | null>(null)
  const pendingAttachmentIds = ref<number[]>([])

  const isNew = computed(() => !currentId.value)
  const saveState = computed(() => (saving.value ? '保存中…' : dirty.value ? '有改动，稍后自动保存' : savedAt.value ? '已保存' : isNew.value ? '输入后自动保存' : ''))

  async function settle(fn: () => void) {
    skip.value = true
    fn()
    await nextTick()
    skip.value = false
    dirty.value = false
  }

  function apply(n: NoteDetail) {
    return settle(() => {
      form.value = {
        project_id: n.project_id,
        title: n.title,
        content: n.content || '',
        tags: n.tags.map((t) => t.name),
        is_pinned: n.is_pinned,
        is_archived: n.is_archived,
      }
      attachments.value = n.attachments
      reminders.value = n.reminders
      meta.value = { created_at: n.created_at, updated_at: n.updated_at }
      savedAt.value = n.updated_at
      titleStatus.value = n.title_status || 'idle'
    })
  }

  async function reset() {
    await settle(() => {
      form.value = defaults()
      attachments.value = []
      reminders.value = []
      meta.value = null
      pendingAttachmentIds.value = []
      savedAt.value = ''
      titleStatus.value = 'idle'
    })
  }

  async function load() {
    if (!currentId.value) return
    loading.value = true
    try {
      await apply(await noteApi.get(currentId.value))
      scheduleTitle()
    } catch {
      opts.onLoadFailed?.()
    } finally {
      loading.value = false
    }
  }

  function payload(): NotePayload {
    const { title: _title, tags: _tags, ...data } = form.value
    return { ...data, attachment_ids: pendingAttachmentIds.value.length ? [...pendingAttachmentIds.value] : undefined }
  }

  function isEmpty() {
    const p = payload()
    return !p.content && !attachments.value.length && !pendingAttachmentIds.value.length
  }

  async function save(silent = true): Promise<NoteDetail | null> {
    if (saveTask) {
      await saveTask
      return dirty.value ? save(silent) : null
    }
    if (isEmpty()) return null
    const task = persist(silent)
    saveTask = task
    try {
      return await task
    } finally {
      saveTask = null
    }
  }

  async function persist(silent: boolean): Promise<NoteDetail> {
    saving.value = true
    const sent = payload()
    const sentRevision = revision
    const targetId = currentId.value
    try {
      let n: NoteDetail
      if (!targetId) {
        n = await noteApi.create(sent)
        currentId.value = n.id
        app.fetchProjects()
        window.dispatchEvent(new CustomEvent('note:created', { detail: n }))
        opts.onCreated?.(n)
      } else {
        n = await noteApi.update(targetId, sent)
      }
      pendingAttachmentIds.value = pendingAttachmentIds.value.filter((id) => !sent.attachment_ids?.includes(id))
      skip.value = true
      form.value.title = n.title
      titleStatus.value = n.title_status || 'idle'
      await nextTick()
      skip.value = false
      meta.value = { created_at: n.created_at, updated_at: n.updated_at }
      reminders.value = n.reminders
      const changedDuringSave = revision !== sentRevision || pendingAttachmentIds.value.length > 0
      if (!changedDuringSave) attachments.value = n.attachments
      savedAt.value = n.updated_at
      dirty.value = changedDuringSave
      if (!silent) feedback.success('已保存')
      window.dispatchEvent(new CustomEvent('note:saved', { detail: n }))
      opts.onSaved?.(n)
      if (!dirty.value) scheduleTitle()
      return n
    } finally {
      saving.value = false
      if (dirty.value && revision !== sentRevision) autosave()
    }
  }

  const autosave = useDebounceFn(() => alive && dirty.value ? save(true).catch(() => null) : null, 1200)

  function scheduleTitle() {
    clearTimeout(titleTimer)
    if (!alive || !currentId.value || !['pending', 'processing'].includes(titleStatus.value)) return
    titleTimer = window.setTimeout(refreshTitle, 2500)
  }

  async function refreshTitle() {
    if (!alive) return
    if (dirty.value || saving.value) { scheduleTitle(); return }
    const id = currentId.value
    const atRevision = revision
    try {
      const n = await noteApi.generateTitle(id)
      if (!alive || currentId.value !== id || revision !== atRevision || dirty.value) return
      skip.value = true
      form.value.title = n.title
      titleStatus.value = n.title_status || 'idle'
      await nextTick()
      skip.value = false
      opts.onSaved?.(n)
      window.dispatchEvent(new CustomEvent('note:saved', { detail: n }))
      scheduleTitle()
    } catch {
      if (alive && currentId.value === id) titleStatus.value = 'failed'
    }
  }

  watch(form, () => {
    if (skip.value || loading.value) return
    revision++
    dirty.value = true
    autosave()
  }, { deep: true })

  watch(opts.noteId, async (id) => {
    if (id === currentId.value) return
    if (dirty.value) await save(true).catch(() => null)
    currentId.value = id
    clearTimeout(titleTimer)
    id ? await load() : await reset()
  })

  // ---- 附件
  async function uploadMany(files: (File | Blob)[], names?: string[]) {
    if (!files.length) return
    const h = feedback.loading('上传中…')
    try {
      for (const [i, f] of files.entries()) {
        const att = await uploadFile(f, { note_id: currentId.value || undefined, project_id: form.value.project_id || undefined }, (pct) => h.update(`上传 ${i + 1}/${files.length}  ${pct}%`), names?.[i])
        onUploaded(att)
      }
    } catch (e) {
      feedback.error('上传失败：' + (e as Error).message)
    } finally {
      h.close()
    }
  }

  async function addFiles(accept?: string, capture?: 'environment') {
    const files = await pickFiles({ accept, multiple: !capture, capture })
    await uploadMany(files)
  }

  function onUploaded(att: Attachment) {
    if (att.note_id && att.note_id !== currentId.value) return
    if (!attachments.value.some((a) => a.id === att.id)) attachments.value.push(att)
    if (!att.note_id && !pendingAttachmentIds.value.includes(att.id)) pendingAttachmentIds.value.push(att.id)
    revision++
    dirty.value = true
    autosave()
  }

  async function onRecorded(r: RecordingResult) {
    await uploadMany([r.blob], [r.filename])
  }

  async function removeAttachment(id: number) {
    attachments.value = attachments.value.filter((a) => a.id !== id)
    pendingAttachmentIds.value = pendingAttachmentIds.value.filter((x) => x !== id)
    revision++
    dirty.value = true
    autosave()
  }

  function onAttachmentUpdated(att: Attachment) {
    if (att.asr_status === 3 && currentId.value) {
      titleStatus.value = 'pending'
      scheduleTitle()
    }
  }

  // ---- 提醒
  async function saveReminder(p: ReminderPayload, editing: Reminder | null) {
    if (isNew.value) {
      const n = await save(true)
      if (!n) {
        feedback.error('先写点内容再设置提醒')
        return false
      }
    }
    if (editing) await reminderApi.update(editing.id, p)
    else await reminderApi.create({ ...p, note_id: currentId.value, title: p.title || form.value.title })
    reminders.value = (await noteApi.get(currentId.value)).reminders
    feedback.success('提醒已设置')
    return true
  }
  async function reminderDone(r: Reminder) {
    await reminderApi.done(r.id)
    reminders.value = (await noteApi.get(currentId.value)).reminders
  }
  async function reminderDelete(r: Reminder) {
    await reminderApi.remove(r.id)
    reminders.value = reminders.value.filter((x) => x.id !== r.id)
  }
  async function refreshReminders() {
    if (currentId.value) reminders.value = (await noteApi.get(currentId.value)).reminders
  }

  // ---- 菜单
  function togglePin() {
    form.value.is_pinned = form.value.is_pinned ? 0 : 1
  }
  function toggleArchive() {
    form.value.is_archived = form.value.is_archived ? 0 : 1
    feedback.success(form.value.is_archived ? '已归档' : '已取消归档')
  }
  function plainText(html: string) {
    return [form.value.title, html.replace(/<[^>]+>/g, '\n').replace(/\n{2,}/g, '\n').trim()].filter(Boolean).join('\n')
  }
  async function copyText(html: string) {
    await navigator.clipboard?.writeText(plainText(html))
    feedback.success('已复制')
  }
  async function remove(): Promise<boolean> {
    const ok = await feedback.confirm({ title: '删除记录', message: '会进入回收站，可以恢复。', danger: true, confirmText: '删除' })
    if (!ok) return false
    const id = currentId.value
    dirty.value = false
    if (id) await noteApi.remove(id)
    feedback.success('已删除')
    opts.onDeleted?.(id)
    return true
  }

  async function flush() {
    if (saveTask) await saveTask.catch(() => null)
    if (dirty.value) await save(true).catch(() => null)
  }

  function init() {
    return currentId.value ? load() : reset()
  }

  onBeforeUnmount(() => {
    alive = false
    clearTimeout(titleTimer)
  })

  return {
    currentId, loading, saving, dirty, savedAt, saveState, isNew, titleStatus,
    form, attachments, reminders, meta,
    init, load, reset, save, flush,
    addFiles, uploadMany, onUploaded, onRecorded, removeAttachment, onAttachmentUpdated,
    saveReminder, reminderDone, reminderDelete, refreshReminders,
    togglePin, toggleArchive, copyText, remove,
  }
}
