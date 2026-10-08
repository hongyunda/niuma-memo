export interface User {
  id: number
  username: string
  nickname: string
  avatar: string
  push_config: PushConfig | null
  last_login_at: string | null
}

export interface PushConfig {
  bark_key?: string
  bark_server?: string
  wecom_webhook?: string
  default_channels?: string[]
}

export interface Project {
  id: number
  name: string
  description: string | null
  status: 1 | 2
  sort: number
  created_at: string
  updated_at: string
  note_count?: number
  last_note_at?: string | null
}

export type TitleStatus = 'idle' | 'pending' | 'processing' | 'generated' | 'disabled' | 'failed'

export interface Tag {
  id: number
  name: string
  color: string
  note_count?: number
}

export type FileType = 'image' | 'audio' | 'video' | 'pdf' | 'word' | 'excel' | 'ppt' | 'other'

export interface Attachment {
  id: number
  note_id: number | null
  project_id: number | null
  file_type: FileType
  original_name: string
  mime: string
  size: number
  duration: number
  width: number
  height: number
  url: string
  thumb_url: string
  transcript: string | null
  asr_status: 0 | 1 | 2 | 3 | 4
  asr_error: string
  created_at: string
  note?: { id: number; title: string; project_id: number | null } | null
}

export interface Reminder {
  id: number
  note_id: number | null
  title: string
  remind_at: string
  advance_minutes: number
  repeat_type: 0 | 1 | 2 | 3 | 4 | 5
  repeat_until: string | null
  channels: string
  status: 1 | 2 | 3 | 4
  snooze_until: string | null
  last_sent_at: string | null
  created_at: string
  updated_at: string
  note?: { id: number; title: string; project_id: number | null } | null
}

export interface NoteBase {
  id: number
  project_id: number | null
  title: string
  title_status?: TitleStatus
  is_pinned: 0 | 1
  is_archived: 0 | 1
  created_at: string
  updated_at: string
  deleted_at?: string | null
  tags: Tag[]
}

export interface NoteListItem extends NoteBase {
  summary: string
  attachment_count: number
  images: string[]
  has_audio: boolean
  next_reminder: Reminder | null
  attachments: Pick<Attachment, 'id' | 'file_type' | 'original_name' | 'thumb_url' | 'duration' | 'asr_status' | 'size'>[]
}

export interface NoteDetail extends NoteBase {
  content: string
  content_text: string
  project: Pick<Project, 'id' | 'name'> | null
  attachments: Attachment[]
  reminders: Reminder[]
}

export interface Paginated<T> {
  list: T[]
  total: number
  page: number
  limit: number
  last_page: number
}

export interface Dashboard {
  today_reminders: Reminder[]
  recent_notes: NoteListItem[]
}

export type ResourceCategory = 'files' | 'image' | 'video' | 'link' | 'table' | 'pdf' | 'other'
export interface ProjectResource {
  id: string
  kind: 'file' | 'link' | 'table'
  title: string
  url?: string
  html?: string
  note_id: number | null
  note_title: string
  updated_at: string
  attachment?: Attachment
}

export interface Settings {
  ai: { enabled: boolean }
  attachment_storage: { driver: 'cos' | 'local'; configured: boolean }
  push_config: PushConfig
  channels: Record<string, boolean>
  vapid_public: string
  asr: { provider: string; enabled: boolean }
  ffmpeg: boolean
  storage: { used: number; count: number }
  upload_max_mb: number
}

export interface SearchResult {
  q: string
  notes: Paginated<NoteListItem>
  attachments: Attachment[]
}

export interface NotePayload {
  project_id?: number | null
  title?: string
  content?: string
  tags?: (string | number)[]
  is_pinned?: number
  is_archived?: number
  attachment_ids?: number[]
  reminder?: Partial<ReminderPayload>
}

export interface ReminderPayload {
  note_id?: number | null
  title?: string
  remind_at: string
  advance_minutes?: number
  repeat_type?: number
  repeat_until?: string | null
  channels?: string[]
}

export interface NoteQuery {
  project_id?: number | string
  tag_id?: number
  keyword?: string
  archived?: '0' | '1' | 'all'
  pinned?: number
  date_from?: string
  date_to?: string
  page?: number
  limit?: number
}
