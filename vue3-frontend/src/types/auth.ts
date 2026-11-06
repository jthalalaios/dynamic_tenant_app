export interface AuthResponse {
  token?: string
  user?: Record<string, any>
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
}

export interface ForgotPayload {
  email: string
}
