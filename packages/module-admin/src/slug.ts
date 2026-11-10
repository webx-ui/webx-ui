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

/** How a code field is shaped: the node's `separator` and `maxlength`. */
export interface CodeShape {
  /** Between words: `-` (the default) or `_`. */
  separator?: '-' | '_'
  /** Cut to this many characters; none — no cut. */
  max?: number
  /** The language a word in another alphabet is read by. */
  locale?: string
  /**
   * Still being typed: a separator at the end stays, so that the next word has somewhere to go —
   * «brake » is `brake-` until it is `brake-pads`, and `brake` once the field is left.
   */
  typing?: boolean
}

/**
 * What a code field does with whatever is typed into it, instead of refusing it: «тормозные
 * колодки» is `tormoznye-kolodki`, `Brake_Pads` is `brake-pads`. The same as `Slug::code()` on
 * the server, which a save through an agent or an import goes by.
 */
export function codify(text: string, shape: CodeShape = {}): string {
  const separator = shape.separator ?? '-'
  const code = slugify(text, separator, shape.locale)
    // `Str::slug`'s steps keep a letter of a script it has no table for, and an `_` beside a `-`.
    .replace(/[^a-z0-9]+/g, separator)
    .replace(/^[-_]+|[-_]+$/g, '')
  const cut = (shape.max === undefined ? code : code.slice(0, shape.max)).replace(/[-_]+$/, '')

  const open = shape.typing === true && cut !== '' && /[\s_-]$/u.test(text)

  return open && (shape.max === undefined || cut.length < shape.max) ? `${cut}${separator}` : cut
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
