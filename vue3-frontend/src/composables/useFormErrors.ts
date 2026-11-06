import { ref } from 'vue'
import { ApiError } from '../api'
import type { ValidationErrors } from '../types/api'

/**
 * Field-level validation errors from a 422 response, for inline display.
 * Returns true when the error was a validation error (and was captured).
 */
export function useFormErrors() {
  const errors = ref<ValidationErrors>({})

  function capture(err: unknown): boolean {
    const apiError = ApiError.from(err)
    errors.value = apiError.isValidationError ? apiError.errors : {}
    return apiError.isValidationError
  }

  const first = (field: string): string | undefined => errors.value[field]?.[0]
  const clear = () => (errors.value = {})

  return { errors, capture, first, clear }
}
