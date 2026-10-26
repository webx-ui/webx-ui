/**
 * Numbers as a person reads them in a language — the panel's copy of `Breakdown::number()`.
 * Money has no fraction when it is whole and two digits when it is not («12.5» reads as a typo);
 * a rate or an area has up to two, as needed. Not part of the parity: the server's
 * `NumberFormatter` and `Intl.NumberFormat` differ in their spaces, and the numbers are what has
 * to agree.
 */
export function formatNumber(value: number, locale: string, money = true): string {
  const whole = Math.abs(value - Math.round(value)) < 0.005

  try {
    return new Intl.NumberFormat(locale || undefined, {
      minimumFractionDigits: money && !whole ? 2 : 0,
      maximumFractionDigits: money && whole ? 0 : 2,
    }).format(value)
  } catch {
    return String(value)
  }
}

/** A sum in its currency; a code `Intl` does not know is printed after the number. */
export function formatMoney(value: number, currency: string, locale: string): string {
  const whole = Math.abs(value - Math.round(value)) < 0.005

  try {
    return new Intl.NumberFormat(locale || undefined, {
      style: 'currency',
      currency,
      currencyDisplay: 'narrowSymbol',
      minimumFractionDigits: whole ? 0 : 2,
      maximumFractionDigits: whole ? 0 : 2,
    }).format(value)
  } catch {
    return `${formatNumber(value, locale)} ${currency}`.trim()
  }
}
