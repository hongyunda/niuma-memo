import { computed, ref, watch } from 'vue'
import { noteApi } from '@/shared/api'
import type { NoteListItem, NoteQuery } from '@/shared/api/types'
import { feedback } from '@/shared/ui/feedback'

/**
 * 记录列表：分页加载 + 行内操作（置顶 / 归档 / 删除 / 移动），两端共用
 */
export function useNoteList(query: () => NoteQuery, pageSize = 20) {
  const list = ref<NoteListItem[]>([])
  const loading = ref(false)
  const finished = ref(false)
  const error = ref(false)
  const page = ref(1)
  const total = ref(0)
  let generation = 0
  const empty = computed(() => finished.value && !list.value.length && !loading.value)

  async function load() {
    if (loading.value || finished.value) return
    loading.value = true
    error.value = false
    const requestedGeneration = generation
    const requestedPage = page.value
    try {
      const res = await noteApi.list({ ...query(), page: requestedPage, limit: pageSize })
      if (requestedGeneration !== generation) return
      if (requestedPage === 1) list.value = res.list
      else {
        const ids = new Set(list.value.map((note) => note.id))
        list.value.push(...res.list.filter((note) => !ids.has(note.id)))
      }
      total.value = res.total
      finished.value = requestedPage >= res.last_page
      page.value = requestedPage + 1
    } catch {
      if (requestedGeneration === generation) error.value = true
    } finally {
      if (requestedGeneration === generation) loading.value = false
    }
  }

  async function reload() {
    generation++
    page.value = 1
    finished.value = false
    loading.value = false
    await load()
  }

  function remove(id: number) {
    const before = list.value.length
    list.value = list.value.filter((n) => n.id !== id)
    if (list.value.length !== before) total.value = Math.max(0, total.value - 1)
  }

  function patch(id: number, data: Partial<NoteListItem>) {
    const row = list.value.find((n) => n.id === id)
    if (row) Object.assign(row, data)
  }

  async function togglePin(n: NoteListItem) {
    const res = await noteApi.pin(n.id, n.is_pinned ? 0 : 1)
    n.is_pinned = res.is_pinned
    feedback.success(res.is_pinned ? '已置顶' : '已取消置顶')
    await reload()
  }

  async function toggleArchive(n: NoteListItem) {
    await noteApi.archive(n.id, n.is_archived ? 0 : 1)
    feedback.success(n.is_archived ? '已取消归档' : '已归档')
    remove(n.id)
  }

  async function destroy(n: NoteListItem): Promise<boolean> {
    const ok = await feedback.confirm({ title: '删除记录', message: `「${n.title || '无标题'}」会进入回收站，可以恢复。`, danger: true, confirmText: '删除' })
    if (!ok) return false
    await noteApi.remove(n.id)
    remove(n.id)
    feedback.success('已删除')
    return true
  }

  async function moveTo(n: NoteListItem, projectId: number | null) {
    await noteApi.move([n.id], projectId || 0)
    feedback.success('已移动')
    remove(n.id)
    window.dispatchEvent(new CustomEvent('note:moved', { detail: { id: n.id, project_id: projectId } }))
  }

  watch(() => JSON.stringify(query()), () => reload(), { immediate: true })

  return { list, loading, finished, error, total, empty, load, reload, remove, patch, togglePin, toggleArchive, destroy, moveTo }
}
