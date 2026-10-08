import axios, { type AxiosRequestConfig } from 'axios'
import { feedback } from '@/shared/ui/feedback'

const TOKEN_KEY = 'memo_token'

export const getToken = (): string => {
  try {
    return localStorage.getItem(TOKEN_KEY) || ''
  } catch {
    return ''
  }
}

export const setToken = (token: string) => {
  try {
    token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY)
  } catch {
    /* 私密模式等场景忽略 */
  }
}

export class ApiError extends Error {
  code: number
  constructor(message: string, code: number) {
    super(message)
    this.code = code
  }
}

export interface RequestOptions extends AxiosRequestConfig {
  /** 不弹出错误提示 */
  silent?: boolean
}

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '/api',
  timeout: 120000,
  withCredentials: true,
})

http.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(
  (res) => {
    const body = res.data
    if (body && typeof body === 'object' && 'code' in body) {
      if (body.code === 0) return body.data
      const err = new ApiError(body.msg || '请求失败', body.code)
      if (!(res.config as RequestOptions).silent) feedback.error(err.message)
      return Promise.reject(err)
    }
    return body
  },
  (error) => {
    const status: number = error.response?.status || 0
    const msg: string = error.response?.data?.msg || (error.code === 'ECONNABORTED' ? '请求超时' : '网络异常，请稍后重试')
    if (status === 401) {
      setToken('')
      window.dispatchEvent(new CustomEvent('auth:expired'))
    }
    if (!(error.config as RequestOptions | undefined)?.silent && status !== 401) feedback.error(msg)
    return Promise.reject(new ApiError(msg, status))
  },
)

export const request = {
  get: <T>(url: string, params?: Record<string, unknown>, options: RequestOptions = {}) =>
    http.get(url, { params, ...options }) as unknown as Promise<T>,
  post: <T>(url: string, data?: unknown, options: RequestOptions = {}) =>
    http.post(url, data, options) as unknown as Promise<T>,
  put: <T>(url: string, data?: unknown, options: RequestOptions = {}) =>
    http.put(url, data, options) as unknown as Promise<T>,
  delete: <T>(url: string, params?: Record<string, unknown>, options: RequestOptions = {}) =>
    http.delete(url, { params, ...options }) as unknown as Promise<T>,
  upload: <T>(url: string, form: FormData, onProgress?: (percent: number) => void, options: RequestOptions = {}) =>
    http.post(url, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 0,
      onUploadProgress: (e) => {
        if (onProgress && e.total) onProgress(Math.round((e.loaded / e.total) * 100))
      },
      ...options,
    }) as unknown as Promise<T>,
}

export default http
