const UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte'] as const

/**
 * Bytes as somebody would say them, in their language: «61 kB» in an English panel, «61 кБ» in a
 * Russian one. The unit is a word like any other, and the browser already knows it.
 */
export function readableSize(bytes: number, locale = 'en'): string {
  let size = bytes
  let unit = 0

  while (size >= 1024 && unit < UNITS.length - 1) {
    size /= 1024
    unit++
  }

  const digits = size >= 10 || unit === 0 ? 0 : 1

  try {
    return new Intl.NumberFormat(locale, {
      style: 'unit',
      unit: UNITS[unit],
      unitDisplay: 'short',
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    }).format(size)
  } catch {
    // A locale the browser does not know: the English way, which every reader can read.
    return locale === 'en' ? `${bytes} B` : readableSize(bytes, 'en')
  }
}
