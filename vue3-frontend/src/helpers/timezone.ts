/**
 * Timezone helpers. The API stores UTC and returns ISO-8601 strings already
 * shifted to the user's timezone (offset included), so formatting an instant
 * with `timeZone: tz` and reading its wall-clock parts give the same result.
 */

export const FALLBACK_TIMEZONE = 'UTC'

export function detectTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || FALLBACK_TIMEZONE
  } catch {
    return FALLBACK_TIMEZONE
  }
}

export function isValidTimezone(tz: unknown): tz is string {
  if (typeof tz !== 'string' || tz === '') return false
  try {
    new Intl.DateTimeFormat('en', { timeZone: tz })
    return true
  } catch {
    return false
  }
}

let cachedZones: string[] | null = null

/** Every IANA zone the browser knows, falling back to a short list on old engines */
export function listTimezones(): string[] {
  if (cachedZones) return cachedZones
  const supported = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : []
  const zones = supported.length ? [...supported] : ['UTC', 'Europe/London', 'Europe/Athens', 'America/New_York', 'Asia/Tokyo']
  if (!zones.includes('UTC')) zones.unshift('UTC')
  cachedZones = zones
  return zones
}

/** e.g. "GMT+03:00" for the given zone at the given instant */
export function timezoneOffsetLabel(tz: string, at: Date = new Date()): string {
  try {
    const part = new Intl.DateTimeFormat('en-US', { timeZone: tz, timeZoneName: 'longOffset' })
      .formatToParts(at)
      .find((p) => p.type === 'timeZoneName')
    return part?.value === 'GMT' ? 'GMT+00:00' : part?.value ?? ''
  } catch {
    return ''
  }
}

type DateInput = string | number | Date | null | undefined

function toDate(value: DateInput): Date | null {
  if (value === null || value === undefined || value === '') return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

export interface FormatOptions {
  timezone: string
  locale: string
}

export function formatDateTime(value: DateInput, { timezone, locale }: FormatOptions, options: Intl.DateTimeFormatOptions = {}): string {
  const date = toDate(value)
  if (!date) return '—'
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', ...options, timeZone: timezone }).format(date)
}

export function formatDate(value: DateInput, opts: FormatOptions): string {
  return formatDateTime(value, opts, { dateStyle: 'medium', timeStyle: undefined })
}

export function formatTime(value: DateInput, opts: FormatOptions): string {
  return formatDateTime(value, opts, { dateStyle: undefined, timeStyle: 'short' })
}

/** Today's calendar date (`YYYY-MM-DD`) in the given zone */
export function todayIn(timezone: string): string {
  // en-CA formats as ISO-like YYYY-MM-DD
  return new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
}

const pad = (n: number) => String(n).padStart(2, '0')

/**
 * Wall-clock `Date` for a picker from an API timestamp: "2026-11-03T09:00:00+02:00"
 * becomes a Date showing 09:00 regardless of the browser's own timezone.
 */
export function wallTimeToDate(iso: string | null | undefined): Date | null {
  const match = iso?.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/)
  if (!match) return null
  const [, y, m, d, h, min] = match.map(Number)
  return new Date(y, m - 1, d, h, min)
}

/** Picker `Date` back to wall time (`YYYY-MM-DD HH:mm:ss`) for the API to read in the user's zone */
export function dateToWallTime(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`
}

/** Picker `Date` to a plain `YYYY-MM-DD` */
export function dateToIsoDay(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
