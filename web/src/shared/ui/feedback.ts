/**
 * 反馈抽象：toast / loading / confirm。
 * 桌面端由 Naive UI 实现，移动端由 Vant 实现，业务逻辑只依赖这里。
 */
export interface LoadingHandle {
  update(message: string): void
  close(): void
}

export interface ConfirmOptions {
  title: string
  message?: string
  danger?: boolean
  confirmText?: string
  cancelText?: string
}

export interface FeedbackImpl {
  toast(message: string, type?: 'success' | 'error' | 'info' | 'warning'): void
  loading(message: string): LoadingHandle
  confirm(options: ConfirmOptions): Promise<boolean>
}

let impl: FeedbackImpl = {
  toast: (m) => console.log('[toast]', m),
  loading: () => ({ update() {}, close() {} }),
  confirm: async () => window.confirm('确定？'),
}

export function setFeedback(f: FeedbackImpl) {
  impl = f
}

export const feedback = {
  toast: (message: string, type?: 'success' | 'error' | 'info' | 'warning') => impl.toast(message, type),
  success: (message: string) => impl.toast(message, 'success'),
  error: (message: string) => impl.toast(message, 'error'),
  loading: (message: string) => impl.loading(message),
  confirm: (options: ConfirmOptions) => impl.confirm(options),
}
