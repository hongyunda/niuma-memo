import imageCompression from 'browser-image-compression'
import { attachmentApi } from '@/shared/api'
import type { Attachment } from '@/shared/api/types'

const IMAGE_MAX_MB = 2

/** 截图粘贴在部分浏览器只出现在 items 中。 */
export function clipboardImages(data: DataTransfer | null): File[] {
  if (!data) return []
  const images = Array.from(data.items || [])
    .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
    .map((item) => item.getAsFile())
    .filter((file): file is File => !!file)
  return images.length ? images : Array.from(data.files || []).filter((file) => file.type.startsWith('image/'))
}

export async function compressImage(file: File): Promise<File> {
  if (!file.type.startsWith('image/') || file.type === 'image/gif' || file.size < 600 * 1024) return file
  try {
    const out = await imageCompression(file, {
      maxSizeMB: IMAGE_MAX_MB,
      maxWidthOrHeight: 2200,
      useWebWorker: true,
      initialQuality: 0.85,
    })
    return new File([out], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: out.type || file.type })
  } catch {
    return file
  }
}

export interface UploadTarget {
  note_id?: number
  project_id?: number
}

export async function uploadFile(file: File | Blob, target: UploadTarget = {}, onProgress?: (p: number) => void, filename?: string): Promise<Attachment> {
  const prepared = file instanceof File ? await compressImage(file) : file
  return attachmentApi.upload(prepared, { ...target, filename: filename || (prepared instanceof File ? prepared.name : undefined) }, onProgress)
}

export function pickFiles(options: { accept?: string; multiple?: boolean; capture?: 'environment' | 'user' } = {}): Promise<File[]> {
  return new Promise((resolve) => {
    const input = document.createElement('input')
    input.type = 'file'
    if (options.accept) input.accept = options.accept
    if (options.multiple) input.multiple = true
    if (options.capture) input.setAttribute('capture', options.capture)
    input.style.display = 'none'
    input.onchange = () => {
      resolve(Array.from(input.files || []))
      input.remove()
    }
    // 用户取消选择时也要清理
    input.addEventListener('cancel', () => {
      resolve([])
      input.remove()
    })
    document.body.appendChild(input)
    input.click()
  })
}

export function escapeHtml(s: string) {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string)
}

/** 纯文本 → 段落 HTML */
export function textToHtml(text: string) {
  return text
    .split(/\r?\n/)
    .map((line) => `<p>${escapeHtml(line) || '<br>'}</p>`)
    .join('')
}
