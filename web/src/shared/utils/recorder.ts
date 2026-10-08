/**
 * MediaRecorder 封装：Android Chrome 产出 webm/opus，iOS Safari 产出 mp4/aac，后端统一转 mp3
 */
export interface RecordingResult {
  blob: Blob
  mimeType: string
  duration: number
  filename: string
}

export function recorderSupported() {
  return typeof window !== 'undefined' && 'MediaRecorder' in window && !!navigator.mediaDevices?.getUserMedia
}

function pickMime(): string {
  const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/aac']
  for (const c of candidates) {
    if (MediaRecorder.isTypeSupported?.(c)) return c
  }
  return ''
}

export function extFor(mime: string) {
  if (mime.includes('webm')) return 'webm'
  if (mime.includes('mp4') || mime.includes('aac') || mime.includes('m4a')) return 'm4a'
  if (mime.includes('ogg')) return 'ogg'
  if (mime.includes('wav')) return 'wav'
  return 'webm'
}

export class VoiceRecorder {
  private recorder: MediaRecorder | null = null
  private stream: MediaStream | null = null
  private chunks: BlobPart[] = []
  private startedAt = 0
  private analyser: AnalyserNode | null = null
  private ctx: AudioContext | null = null
  mimeType = ''

  async start() {
    this.stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } })
    this.mimeType = pickMime()
    this.recorder = this.mimeType ? new MediaRecorder(this.stream, { mimeType: this.mimeType, audioBitsPerSecond: 64000 }) : new MediaRecorder(this.stream)
    this.mimeType = this.recorder.mimeType || this.mimeType
    this.chunks = []
    this.recorder.ondataavailable = (e) => {
      if (e.data.size) this.chunks.push(e.data)
    }
    try {
      this.ctx = new AudioContext()
      const source = this.ctx.createMediaStreamSource(this.stream)
      this.analyser = this.ctx.createAnalyser()
      this.analyser.fftSize = 256
      source.connect(this.analyser)
    } catch {
      this.analyser = null
    }
    this.recorder.start(1000)
    this.startedAt = Date.now()
  }

  /** 0-1 的当前音量，用于画个跳动的波形 */
  level(): number {
    if (!this.analyser) return 0
    const data = new Uint8Array(this.analyser.frequencyBinCount)
    this.analyser.getByteTimeDomainData(data)
    let sum = 0
    for (const v of data) {
      const d = (v - 128) / 128
      sum += d * d
    }
    return Math.min(1, Math.sqrt(sum / data.length) * 3)
  }

  elapsed() {
    return this.startedAt ? Math.floor((Date.now() - this.startedAt) / 1000) : 0
  }

  stop(): Promise<RecordingResult> {
    return new Promise((resolve, reject) => {
      const rec = this.recorder
      if (!rec) return reject(new Error('未开始录音'))
      const duration = this.elapsed()
      rec.onstop = () => {
        const mime = this.mimeType || rec.mimeType || 'audio/webm'
        const blob = new Blob(this.chunks, { type: mime })
        this.cleanup()
        const stamp = new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '').replace(/(\d{8})(\d{6})/, '$1_$2')
        resolve({ blob, mimeType: mime, duration, filename: `录音_${stamp}.${extFor(mime)}` })
      }
      rec.state !== 'inactive' ? rec.stop() : rec.onstop?.(new Event('stop'))
    })
  }

  cancel() {
    try {
      if (this.recorder && this.recorder.state !== 'inactive') {
        this.recorder.onstop = null
        this.recorder.stop()
      }
    } catch {
      /* ignore */
    }
    this.cleanup()
  }

  private cleanup() {
    this.stream?.getTracks().forEach((t) => t.stop())
    this.stream = null
    this.recorder = null
    this.ctx?.close().catch(() => {})
    this.ctx = null
    this.analyser = null
    this.startedAt = 0
  }
}
