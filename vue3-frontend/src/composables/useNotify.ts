import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { ApiError } from '../api'

/**
 * Toast helpers with consistent API error handling.
 */
export function useNotify() {
  const toast = useToast()
  const { t } = useI18n()

  function success(detail: string, summary = t('common.success')): void {
    toast.add({ severity: 'success', summary, detail, life: 3000 })
  }

  function info(detail: string, summary = t('common.info')): void {
    toast.add({ severity: 'info', summary, detail, life: 3000 })
  }

  /** Shows the most useful message from any thrown value */
  function error(err: unknown, fallback = t('errors.generic')): void {
    const apiError = err instanceof ApiError ? err : ApiError.from(err)
    // 401s are handled globally (redirect to sign-in); don't stack a toast on top
    if (apiError.status === 401) return

    const detail = apiError.isNetworkError
      ? t('errors.network')
      : apiError.firstError() ?? (apiError.message || fallback)

    toast.add({ severity: 'error', summary: t('common.error'), detail, life: 5000 })
  }

  return { success, info, error }
}
