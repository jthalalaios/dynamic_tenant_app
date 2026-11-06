/** Format integer minor units (cents) as currency */
export function formatMoney(cents: number, currency: string, locale: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(cents / 100)
}

/** Parse a user-entered major amount ("12.50") into cents without float drift */
export function toCents(amount: number | null | undefined): number {
  return Math.round((amount ?? 0) * 100)
}
