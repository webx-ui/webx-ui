/** The forms a counted line is written in. Languages without `few` or `many` repeat `other`. */
export type PluralForm = 'one' | 'few' | 'many' | 'other'

/**
 * Which form of a counted line to use for `count` in `locale`.
 *
 * The panel has no plural syntax of its own, so a line that has to carry its number up front —
 * "5 pages are deleted" — is written once per CLDR category and picked here. `zero` and `two`
 * fall back on `other`: none of the panel's languages writes them differently.
 */
export function pluralForm(count: number, locale: string): PluralForm {
  let form: Intl.LDMLPluralRule

  try {
    form = new Intl.PluralRules(locale).select(count)
  } catch {
    // A locale the browser does not know: English rules are the least wrong guess.
    form = new Intl.PluralRules('en').select(count)
  }

  return form === 'one' || form === 'few' || form === 'many' ? form : 'other'
}
