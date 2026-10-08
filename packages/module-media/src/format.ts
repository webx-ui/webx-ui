import { useAdmin } from '@webx-ui/module-admin'

const UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte'] as const

/**
 * Bytes as somebody would say them, in their language: «32 МБ» in a Russian panel, «32 MB» in an
 * English one — the unit is a word like any other, and the browser already knows it in every
 * language the panel has.
 */
export function readable(bytes: number, locale = 'en'): string {
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
    return readable(bytes, 'en')
  }
}

/**
 * What a file is, in one line: `JPG · image/jpeg · 1200×800 · 240 kB`.
 *
 * Names in the library are titles without an extension, so this is where the format is said
 * in words — the line under a card in a field, and the tip over one in the grid.
 */
export function details(
  file: {
    extension: string
    mime?: string
    width: number | null
    height: number | null
    size: number
  },
  locale = 'en',
): string {
  return [
    file.extension ? file.extension.toUpperCase() : null,
    file.mime || null,
    file.width && file.height ? `${file.width}×${file.height}` : null,
    readable(file.size, locale),
  ]
    .filter(Boolean)
    .join(' · ')
}

/**
 * The language sizes are said in: the panel's, read when a size is drawn so a switch of
 * language redraws it. English outside a panel, where there is no panel to ask.
 */
export function usePanelLocale(): () => string {
  try {
    const { i18n } = useAdmin()

    return () => i18n?.state?.locale ?? 'en'
  } catch {
    return () => 'en'
  }
}

/** The file name out of a stored key, for a file the library can no longer be asked about. */
export function nameFromPath(path: string): string {
  return path.split('/').pop() || path
}
