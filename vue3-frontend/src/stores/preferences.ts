import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { detectLocale, intlLocale, isSupportedLocale, loadLocale, type LocaleCode } from '../helpers/language'
import { detectTimezone, isValidTimezone } from '../helpers/timezone'
import { storage } from '../helpers/storage'

const TZ_KEY = 'timezone'

/**
 * Locale + timezone the UI renders with and sends to the API on every request.
 * Persisted locally for guests; for signed-in users the server-side preference wins.
 */
export const usePreferencesStore = defineStore('preferences', () => {
  const storedTz = storage.get(TZ_KEY)

  const locale = ref<LocaleCode>(detectLocale())
  const timezone = ref<string>(isValidTimezone(storedTz) ? storedTz : detectTimezone())

  /** BCP-47 tag for Intl formatters */
  const intl = computed(() => intlLocale(locale.value))
  /** Ready-made options for helpers/timezone formatters */
  const formatOptions = computed(() => ({ timezone: timezone.value, locale: intl.value }))

  async function setLocale(code: LocaleCode): Promise<void> {
    await loadLocale(code)
    locale.value = code
  }

  function setTimezone(tz: string): void {
    if (!isValidTimezone(tz)) return
    timezone.value = tz
    storage.set(TZ_KEY, tz)
  }

  /** Adopt the preferences the API resolved for the session */
  async function applyServer(prefs: { timezone: string; locale: string }): Promise<void> {
    setTimezone(prefs.timezone)
    if (isSupportedLocale(prefs.locale) && prefs.locale !== locale.value) {
      await setLocale(prefs.locale)
    }
  }

  return { locale, timezone, intl, formatOptions, setLocale, setTimezone, applyServer }
})
