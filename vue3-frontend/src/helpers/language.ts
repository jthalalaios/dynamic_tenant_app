import i18n from '../i18n'
import { storage } from './storage'

export const SUPPORTED_LOCALES = [
  { code: 'en', label: 'English', flag: '🇬🇧', intl: 'en-GB' },
  { code: 'el', label: 'Ελληνικά', flag: '🇬🇷', intl: 'el-GR' }
] as const

export type LocaleCode = (typeof SUPPORTED_LOCALES)[number]['code']

export const DEFAULT_LOCALE: LocaleCode = 'en'
const STORAGE_KEY = 'locale'

export function isSupportedLocale(code: unknown): code is LocaleCode {
  return SUPPORTED_LOCALES.some((l) => l.code === code)
}

/** "el-GR" → "el", legacy "gr" → "el", anything unknown → default */
export function normalizeLocale(raw: string | null | undefined): LocaleCode {
  const base = (raw ?? '').toLowerCase().split(/[-_]/)[0]
  const code = base === 'gr' ? 'el' : base
  return isSupportedLocale(code) ? code : DEFAULT_LOCALE
}

export function detectLocale(): LocaleCode {
  const stored = storage.get(STORAGE_KEY)
  if (isSupportedLocale(stored)) return stored
  return normalizeLocale(typeof navigator !== 'undefined' ? navigator.language : null)
}

/** BCP-47 tag for Intl formatters */
export function intlLocale(code: LocaleCode): string {
  return SUPPORTED_LOCALES.find((l) => l.code === code)?.intl ?? 'en-GB'
}

const loaded = new Set<LocaleCode>()

async function ensureMessages(code: LocaleCode): Promise<void> {
  if (loaded.has(code)) return
  const messages = await import(`../locales/${code}.json`)
  i18n.global.setLocaleMessage(code, messages.default ?? messages)
  loaded.add(code)
}

/** Lazy-load a locale's messages (code-split per language) plus the fallback, and activate it */
export async function loadLocale(code: LocaleCode): Promise<void> {
  await Promise.all([ensureMessages(code), ensureMessages(DEFAULT_LOCALE)])

  i18n.global.locale.value = code
  document.documentElement.lang = code
  storage.set(STORAGE_KEY, code)
}
