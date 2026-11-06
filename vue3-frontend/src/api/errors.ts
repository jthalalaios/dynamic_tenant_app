import { isAxiosError } from 'axios'
import type { ValidationErrors } from '../types/api'

/**
 * Normalised error for every failed API call.
 * Interceptors reject with this, so callers never deal with raw Axios errors.
 */
export class ApiError extends Error {
  readonly status: number
  /** Machine readable code for domain rule violations, e.g. `user_offline` */
  readonly code: string | null
  readonly errors: ValidationErrors

  constructor(message: string, status: number, code: string | null = null, errors: ValidationErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.errors = errors
  }

  get isNetworkError(): boolean {
    return this.status === 0
  }

  get isValidationError(): boolean {
    return this.status === 422 && Object.keys(this.errors).length > 0
  }

  /** First message for a field (or the first validation message overall) */
  firstError(field?: string): string | null {
    if (field) return this.errors[field]?.[0] ?? null
    return Object.values(this.errors)[0]?.[0] ?? null
  }

  static from(error: unknown): ApiError {
    if (error instanceof ApiError) return error

    if (isAxiosError(error)) {
      const status = error.response?.status ?? 0
      const body = (error.response?.data ?? {}) as { message?: string; code?: string; errors?: ValidationErrors }
      return new ApiError(body.message || error.message, status, body.code ?? null, body.errors ?? {})
    }

    return new ApiError(error instanceof Error ? error.message : String(error), 0)
  }
}
