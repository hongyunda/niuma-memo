import { request } from '@/shared/utils/request'
import type {
  Attachment, Dashboard, NoteDetail, NoteListItem, NotePayload, NoteQuery, Paginated,
  Project, ProjectResource, ResourceCategory, Reminder, ReminderPayload, SearchResult, Settings, Tag, User, PushConfig,
} from './types'

export const authApi = {
  login: (username: string, password: string) =>
    request.post<{ token: string; user: User }>('/auth/login', { username, password }),
  me: () => request.get<User>('/auth/me', undefined, { silent: true }),
  logout: () => request.post<null>('/auth/logout', undefined, { silent: true }),
  password: (old_password: string, new_password: string) => request.put<null>('/auth/password', { old_password, new_password }),
}

export const dashboardApi = {
  get: () => request.get<Dashboard>('/dashboard'),
}

export const projectApi = {
  list: (params: { status?: number | 'all' } = {}) =>
    request.get<{ list: Project[] }>('/projects', params),
  create: (data: Partial<Project>) => request.post<Project>('/projects', data),
  get: (id: number) => request.get<Project>(`/projects/${id}`),
  resources: (id: number, params: { category?: ResourceCategory; keyword?: string; page?: number; limit?: number } = {}) => request.get<Paginated<ProjectResource>>(`/projects/${id}/resources`, params),
  update: (id: number, data: Partial<Project>) => request.put<Project>(`/projects/${id}`, data),
  remove: (id: number, withNotes = false) => request.delete<null>(`/projects/${id}`, { with_notes: withNotes ? 1 : 0 }),
  sort: (ids: number[]) => request.put<null>('/projects/sort', { ids }),
}

export const noteApi = {
  list: (params: NoteQuery = {}) => request.get<Paginated<NoteListItem>>('/notes', params as Record<string, unknown>),
  create: (data: NotePayload) => request.post<NoteDetail>('/notes', data),
  get: (id: number) => request.get<NoteDetail>(`/notes/${id}`),
  update: (id: number, data: NotePayload) => request.put<NoteDetail>(`/notes/${id}`, data),
  generateTitle: (id: number) => request.post<NoteDetail>(`/notes/${id}/title`, undefined, { silent: true, timeout: 180000 }),
  remove: (id: number) => request.delete<null>(`/notes/${id}`),
  pin: (id: number, is_pinned: number) => request.put<NoteDetail>(`/notes/${id}/pin`, { is_pinned }),
  archive: (id: number, is_archived: number) => request.put<NoteDetail>(`/notes/${id}/archive`, { is_archived }),
  move: (ids: number[], project_id: number) => request.put<null>('/notes/move', { ids, project_id }),
  trash: (page = 1) => request.get<Paginated<NoteListItem>>('/notes/trash', { page }),
  restore: (id: number) => request.put<null>(`/notes/${id}/restore`),
  force: (id: number) => request.delete<null>(`/notes/${id}/force`),
}

export const attachmentApi = {
  upload: (file: File | Blob, extra: { note_id?: number; project_id?: number; filename?: string } = {}, onProgress?: (p: number) => void) => {
    const form = new FormData()
    const name = extra.filename || (file instanceof File ? file.name : 'file.bin')
    form.append('file', file, name)
    if (extra.note_id) form.append('note_id', String(extra.note_id))
    if (extra.project_id) form.append('project_id', String(extra.project_id))
    return request.upload<Attachment>('/attachments', form, onProgress)
  },
  update: (id: number, data: Partial<Pick<Attachment, 'note_id' | 'project_id' | 'original_name' | 'transcript'>>) =>
    request.put<Attachment>(`/attachments/${id}`, data),
  remove: (id: number) => request.delete<null>(`/attachments/${id}`),
  transcribe: (id: number, force = false) =>
    request.post<Attachment>(`/attachments/${id}/transcribe`, { force: force ? 1 : 0 }, { silent: true, timeout: 0 }),
}

export const tagApi = {
  list: () => request.get<{ list: Tag[] }>('/tags'),
  create: (name: string, color = '') => request.post<Tag>('/tags', { name, color }),
  update: (id: number, data: Partial<Tag>) => request.put<Tag>(`/tags/${id}`, data),
  remove: (id: number) => request.delete<null>(`/tags/${id}`),
}

export const reminderApi = {
  list: (params: { scope?: 'upcoming' | 'overdue' | 'done' | 'all'; note_id?: number; page?: number; limit?: number } = {}) =>
    request.get<Paginated<Reminder>>('/reminders', params),
  calendar: (month: string) => request.get<{ month: string; days: Record<string, Reminder[]> }>('/reminders/calendar', { month }),
  create: (data: ReminderPayload) => request.post<Reminder>('/reminders', data),
  update: (id: number, data: Partial<ReminderPayload>) => request.put<Reminder>(`/reminders/${id}`, data),
  remove: (id: number) => request.delete<null>(`/reminders/${id}`),
  snooze: (id: number, minutes: number) => request.put<Reminder>(`/reminders/${id}/snooze`, { minutes }),
  done: (id: number) => request.put<Reminder>(`/reminders/${id}/done`),
}

export const pushApi = {
  subscribe: (sub: PushSubscriptionJSON) => request.post<{ id: number }>('/push/subscribe', sub),
  unsubscribe: (endpoint: string) => request.delete<null>('/push/subscribe', { endpoint }),
  test: (channel: string) => request.post<{ ok: boolean }>('/push/test', { channel }),
}

export const settingApi = {
  get: () => request.get<Settings>('/settings'),
  updatePush: (data: PushConfig) => request.put<PushConfig>('/settings/push', data),
}

export const searchApi = {
  query: (q: string, params: { project_id?: number; page?: number } = {}) =>
    request.get<SearchResult>('/search', { q, ...params }),
}
