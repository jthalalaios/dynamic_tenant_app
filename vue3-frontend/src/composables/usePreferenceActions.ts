import { authApi } from '../api'
import type { LocaleCode } from '../helpers/language'
import { useAuthStore, usePreferencesStore } from '../stores'
import { useNotify } from './useNotify'

/**
 * Change locale/timezone: applied locally right away and, for signed-in
 * users, saved to their profile so every device follows.
 */
export function usePreferenceActions() {
  const auth = useAuthStore()
  const prefs = usePreferencesStore()
  const notify = useNotify()

  async function persist(payload: { locale?: LocaleCode; timezone?: string }): Promise<void> {
    if (!auth.isAuthenticated) return
    try {
      await auth.applySession(await authApi.updateProfile(payload))
    } catch (err) {
      notify.error(err)
    }
  }

  async function changeLocale(code: LocaleCode): Promise<void> {
    await prefs.setLocale(code)
    await persist({ locale: code })
  }

  async function changeTimezone(tz: string): Promise<void> {
    prefs.setTimezone(tz)
    await persist({ timezone: tz })
  }

  return { changeLocale, changeTimezone }
}
