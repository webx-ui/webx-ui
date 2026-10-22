import { computed, inject, type ComputedRef } from 'vue'
import { i18nKey } from './i18n'
import TRANSLITERATION from './transliteration.json'

/**
 * Letter by letter, the Latin a URL can hold — per language, as the server does it.
 *
 * The server is what actually decides an address: `WebxUi\Localization\Slug`, which is
 * `Str::slug($title, '-', $locale)`. This is the field following the title as it is typed, so
 * that what the editor sees before saving is what they get. The table is the server's own,
 * written out by `scripts/transliteration.php`: «Хіт» is `khit` in Ukrainian and `hit` in Russian,
 * and without a language Cyrillic reads the English way, `xit`.
 */
const TABLES = TRANSLITERATION as Record<string, Record<string, string>>

const merged = new Map<string, Record<string, string>>()

function tableOf(locale: string): Record<string, string> {
  const language = locale.toLowerCase().split(/[-_]/)[0] ?? 'en'
  let table = merged.get(language)

  if (table === undefined) {
    table = { ...TABLES.en, ...(language === 'en' ? {} : (TABLES[language] ?? {})) }
    merged.set(language, table)
  }

  return table
}

/** For a character class: the separator is `-` or `_`, and `-` means a range there. */
function escape(text: string): string {
  return text.replace(/[\\\]^-]/g, (character) => `\\${character}`)
}

/**
 * A title as the last part of an address: lower case, Latin, words joined by hyphens — or by
 * another separator, as a machine name joins them with `_` — transliterated by the language the
 * title is written in. The steps are `Str::slug`'s, in its order.
 */
export function slugify(title: string, separator = '-', locale = 'en'): string {
  const table = tableOf(locale)
  const flip = separator === '-' ? '_' : '-'
  const sep = escape(separator)

  return Array.from(title, (character) => table[character] ?? character)
    .join('')
    .replace(new RegExp(`[${escape(flip)}]+`, 'gu'), separator)
    .replaceAll('@', `${separator}at${separator}`)
    .toLowerCase()
    .replace(new RegExp(`[^${sep}\\p{L}\\p{N}\\s]+`, 'gu'), '')
    .replace(new RegExp(`[${sep}\\s]+`, 'gu'), separator)
    .replace(new RegExp(`^[${sep}]+|[${sep}]+$`, 'gu'), '')
}

/**
 * The site's main language — the one a new record is named in, and so the one its address is
 * transliterated by (`Locales::defaultCode()` on the server).
 */
export function useDefaultLocale(): ComputedRef<string> {
  const i18n = inject(i18nKey, null)

  return computed(() => {
    const locales = i18n?.state.contentLocales ?? []

    return (locales.find((locale) => locale.default) ?? locales[0])?.code ?? 'en'
  })
}
