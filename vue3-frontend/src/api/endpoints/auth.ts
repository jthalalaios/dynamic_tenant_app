import { http } from '../http'
import type { Resource } from '../../types/api'
import type { Session, TenantType, TokenResponse } from '../../types/models'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  /** The theme picks the workspace to join (one per theme) */
  type: TenantType
  name: string
  email: string
  password: string
  timezone?: string
  locale?: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface VerifyEmailParams {
  id: string
  hash: string
  expires: string
  signature: string
}

export interface AppConfig {
  /** Themes the API serves */
  themes: TenantType[]
  /** Theme the API bound this request to (from X-App-Theme) */
  theme: TenantType | null
  /** Display name of the workspace each theme's sign-ups join */
  workspaces: Partial<Record<TenantType, string>>
  locales: string[]
  features: { registration: boolean; email_verification: boolean }
}

export interface ProfilePayload {
  name?: string
  timezone?: string | null
  locale?: string | null
  current_password?: string
  password?: string
  password_confirmation?: string
}

export const authApi = {
  login: (payload: LoginPayload) => http.post<TokenResponse>('/auth/login', payload),
  register: (payload: RegisterPayload) => http.post<TokenResponse>('/auth/register', payload),
  me: () => http.get<Resource<Session>>('/auth/me').then((r) => r.data),
  logout: () => http.post<void>('/auth/logout', undefined, { skipAuthHandler: true }),
  forgotPassword: (email: string) => http.post<{ message: string }>('/auth/forgot-password', { email }),
  resetPassword: (payload: ResetPasswordPayload) => http.post<{ message: string }>('/auth/reset-password', payload),
  updateProfile: (payload: ProfilePayload) => http.patch<Resource<Session>>('/auth/profile', payload).then((r) => r.data),
  /** Forwards the emailed link's parameters; the signature covers path + query */
  verifyEmail: ({ id, hash, expires, signature }: VerifyEmailParams) =>
    http.post<{ message: string; verified: boolean }>(
      `/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`,
      undefined,
      { params: { expires, signature } }
    ),
  resendVerification: () => http.post<{ message: string; verified: boolean }>('/auth/email/resend')
}

export const appApi = {
  config: () => http.get<AppConfig>('/app')
}
