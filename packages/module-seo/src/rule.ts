import { localizedValue, type LocalizedValue } from '@webx-ui/core'
import type { SeoUrlInput, SeoUrlRule, SeoValue } from './types'

/** The fields the card owns, as opposed to the ones the rule form owns. */
const SEO_FIELDS = [
  'title',
  'h1',
  'description',
  'keywords',
  'og_title',
  'og_description',
  'og_image',
  'canonical',
  'robots',
  'json_ld',
] as const

/**
 * What a rule is called in a table.
 *
 * Any language rather than the current one: a record half translated is the normal state of a
 * site being worked on, and a blank cell says less than the language that does have the words.
 */
export function ruleTitle(rule: SeoUrlRule, locale?: string): string {
  return localizedValue(rule.title as LocalizedValue | undefined, locale)
}

/** The SEO half of a rule, for the card to edit. */
export function seoOf(rule: SeoUrlRule | null): SeoValue {
  if (!rule) return {}

  const value: SeoValue = {}

  for (const key of SEO_FIELDS) {
    const one = rule[key]

    if (one !== null && one !== undefined) {
      Object.assign(value, { [key]: one })
    }
  }

  return value
}

/**
 * What the form sends: the rule's own fields, with the card's value spread back over them.
 *
 * Every field of the card goes, present or not — a `PUT` replaces the whole rule, so a
 * description somebody cleared has to arrive as nothing rather than not arrive at all.
 */
export function ruleInput(
  own: Pick<SeoUrlInput, 'match_type' | 'pattern' | 'priority' | 'is_active'>,
  seo: SeoValue,
): SeoUrlInput {
  const input: SeoUrlInput = { ...own }

  for (const key of SEO_FIELDS) {
    Object.assign(input, { [key]: seo[key] ?? null })
  }

  return input
}
