import type { TariffRow } from './types'

/**
 * The price of a row on one line, the way the offered block prints it: the symbol before the
 * number, the period after it (“$750 /mo”), or the words where there is no number (“On request”).
 * `''` when there is neither — the row says nothing rather than “0”.
 *
 * The number follows the same rule as the card's `amount` (§4.1): no fraction when it is whole,
 * two places when it is not, the separators of the panel's language. Zero is a number (decision
 * 16): “$0”, and “Free” is the words.
 */
export function priceLine(
  row: Pick<TariffRow, 'price' | 'symbol' | 'currency' | 'period' | 'price_text'>,
  locale: string,
): string {
  if (row.price === null || row.price === undefined) return (row.price_text ?? '').trim()

  const whole = Number.isInteger(row.price)
  const amount = new Intl.NumberFormat(locale, {
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: whole ? 0 : 2,
  }).format(row.price)
  const symbol = row.symbol ?? row.currency ?? ''
  const period = (row.period ?? '').trim()

  return [`${symbol}${amount}`, period].filter((part) => part !== '').join(' ')
}
