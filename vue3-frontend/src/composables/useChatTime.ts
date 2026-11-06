import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '../stores/preferences'
import { formatDateTime } from '../helpers/timezone'

const DAY_MS = 86_400_000

/** Chat time labels in the viewer's timezone and language: "14:05", "Yesterday", "Mon 3 Nov" */
export function useChatTime() {
  const { t } = useI18n()
  const { formatOptions } = storeToRefs(usePreferencesStore())

  /** Calendar day (`YYYY-MM-DD`) of an instant in the viewer's zone */
  function dayKey(value: string | number | Date): string {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: formatOptions.value.timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).format(new Date(value))
  }

  const isToday = (iso: string) => dayKey(iso) === dayKey(Date.now())
  const isYesterday = (iso: string) => dayKey(iso) === dayKey(Date.now() - DAY_MS)

  function clock(iso: string): string {
    return formatDateTime(iso, formatOptions.value, { dateStyle: undefined, timeStyle: 'short' })
  }

  /** Separator between days in a conversation */
  function dayLabel(iso: string): string {
    if (isToday(iso)) return t('chat.today')
    if (isYesterday(iso)) return t('chat.yesterday')
    return formatDateTime(iso, formatOptions.value, { dateStyle: undefined, timeStyle: undefined, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  }

  /** Compact stamp for the room list: time today, else the date */
  function stamp(iso: string): string {
    if (isToday(iso)) return clock(iso)
    if (isYesterday(iso)) return t('chat.yesterday')
    return formatDateTime(iso, formatOptions.value, { dateStyle: undefined, timeStyle: undefined, day: 'numeric', month: 'short' })
  }

  /** Full date and time, for tooltips and mute expiries */
  function full(iso: string): string {
    return formatDateTime(iso, formatOptions.value, { dateStyle: 'medium', timeStyle: 'short' })
  }

  return { dayKey, dayLabel, clock, stamp, full }
}
