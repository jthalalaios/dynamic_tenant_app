import axios, { type AxiosRequestConfig, type InternalAxiosRequestConfig } from 'axios'
import { ApiError } from './errors'
import type { ListParams, Paginated, Resource } from '../types/api'

/**
 * Runtime hooks wired up by the app at boot (see main.ts).
 * Keeps this module free of store/router imports, so there are no import cycles.
 */
export interface HttpHooks {
  getToken: () => string | null
  getLocale: () => string
  getTimezone: () => string
  /** Standalone theme sent as X-App-Theme (null in multi mode) */
  getTheme: () => string | null
  onUnauthorized: () => void
  /** The API refused a feature because the email is not verified yet */
  onEmailUnverified: () => void
  /** Websocket connection id, sent as X-Socket-ID so the API skips echoing our own events back */
  getSocketId: () => string | null
}

let hooks: HttpHooks = {
  getToken: () => null,
  getLocale: () => 'en',
  getTimezone: () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
  getTheme: () => null,
  onUnauthorized: () => {},
  onEmailUnverified: () => {},
  getSocketId: () => null
}

export function configureHttp(next: Partial<HttpHooks>): void {
  hooks = { ...hooks, ...next }
}

declare module 'axios' {
  interface AxiosRequestConfig {
    /** Skip the global 401 handler (e.g. for logout, which may legitimately 401) */
    skipAuthHandler?: boolean
  }
}

const API_ROOT = (import.meta.env.VITE_API_URL || 'http://localhost:8080/api').replace(/\/+$/, '')

/** Raw Axios instance; prefer the typed `http` helpers below */
export const httpClient = axios.create({
  baseURL: `${API_ROOT}/v1`,
  timeout: 20_000,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
})

httpClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = hooks.getToken()
  if (token) config.headers.set('Authorization', `Bearer ${token}`)
  config.headers.set('Accept-Language', hooks.getLocale())
  config.headers.set('X-Timezone', hooks.getTimezone())
  const theme = hooks.getTheme()
  if (theme) config.headers.set('X-App-Theme', theme)
  const socketId = hooks.getSocketId()
  if (socketId) config.headers.set('X-Socket-ID', socketId)
  return config
})

httpClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    const apiError = ApiError.from(error)
    const config = axios.isAxiosError(error) ? error.config : undefined

    if (apiError.status === 401 && !config?.skipAuthHandler) {
      hooks.onUnauthorized()
    }

    if (apiError.status === 403 && apiError.code === 'email_unverified') {
      hooks.onEmailUnverified()
    }

    return Promise.reject(apiError)
  }
)

/** Drop empty filters so they don't end up as `?search=` */
function cleanParams(params?: ListParams): ListParams | undefined {
  if (!params) return undefined
  return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))
}

/** Typed helpers that resolve to the response body */
export const http = {
  get: <T>(url: string, params?: ListParams, config?: AxiosRequestConfig) =>
    httpClient.get<T>(url, { ...config, params: cleanParams(params) }).then((r) => r.data),
  post: <T>(url: string, body?: unknown, config?: AxiosRequestConfig) =>
    httpClient.post<T>(url, body, config).then((r) => r.data),
  put: <T>(url: string, body?: unknown, config?: AxiosRequestConfig) =>
    httpClient.put<T>(url, body, config).then((r) => r.data),
  patch: <T>(url: string, body?: unknown, config?: AxiosRequestConfig) =>
    httpClient.patch<T>(url, body, config).then((r) => r.data),
  delete: <T = void>(url: string, config?: AxiosRequestConfig) =>
    httpClient.delete<T>(url, config).then((r) => r.data)
}

/**
 * Minimal CRUD client for a REST resource, e.g. `resource<Patient>('/doctor/patients')`.
 */
export function resource<T, TPayload = Partial<T>>(basePath: string) {
  return {
    list: (params?: ListParams) => http.get<Paginated<T>>(basePath, params),
    show: (id: number) => http.get<Resource<T>>(`${basePath}/${id}`).then((r) => r.data),
    create: (payload: TPayload) => http.post<Resource<T>>(basePath, payload).then((r) => r.data),
    update: (id: number, payload: Partial<TPayload>) =>
      http.patch<Resource<T>>(`${basePath}/${id}`, payload).then((r) => r.data),
    remove: (id: number) => http.delete(`${basePath}/${id}`)
  }
}
