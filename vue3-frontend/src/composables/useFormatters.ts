import { storeToRefs } from 'pinia'
import { usePreferencesStore } from '../stores/preferences'
import { formatDate, formatDateTime, formatTime } from '../helpers/timezone'
import { formatMoney } from '../helpers/money'

/** Locale- and timezone-aware formatters bound to the user's preferences */
export function useFormatters() {
  const prefs = usePreferencesStore()
  const { formatOptions, intl } = storeToRefs(prefs)

  return {
    dateTime: (value: string | null | undefined) => formatDateTime(value, formatOptions.value),
    date: (value: string | null | undefined) => formatDate(value, formatOptions.value),
    time: (value: string | null | undefined) => formatTime(value, formatOptions.value),
    money: (cents: number, currency: string) => formatMoney(cents, currency, intl.value),
    number: (value: number) => new Intl.NumberFormat(intl.value).format(value)
  }
}
