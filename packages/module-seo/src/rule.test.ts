import { describe, expect, it } from 'vitest'
import { ruleInput, ruleTitle, seoOf } from './rule'
import type { SeoUrlRule } from './types'

function rule(fields: Partial<SeoUrlRule> = {}): SeoUrlRule {
  return {
    id: 1,
    match_type: 'exact',
    pattern: '/about',
    priority: 0,
    is_active: true,
    title: {},
    h1: {},
    description: {},
    keywords: {},
    og_title: {},
    og_description: {},
    og_image: null,
    canonical: null,
    robots: null,
    json_ld: null,
    created_at: null,
    updated_at: null,
    ...fields,
  }
}

describe('a rule and the card that edits it', () => {
  it('names a rule in whatever language it was written in', () => {
    // Half translated is the normal state of a site being worked on, and a blank cell in a
    // table says less than the language that does have the words.
    expect(ruleTitle(rule({ title: { uk: 'Про нас' } }), 'en')).toBe('Про нас')
    expect(ruleTitle(rule({ title: { en: 'About', uk: 'Про нас' } }), 'uk')).toBe('Про нас')
    expect(ruleTitle(rule())).toBe('')
  })

  it('hands the card only the fields the card owns', () => {
    const value = seoOf(rule({ title: { en: 'About' }, robots: 'noindex' }))

    expect(value).toEqual({
      title: { en: 'About' },
      h1: {},
      description: {},
      keywords: {},
      og_title: {},
      og_description: {},
      robots: 'noindex',
    })
    expect(value).not.toHaveProperty('pattern')
    expect(value).not.toHaveProperty('id')
  })

  it('sends every field back, including the ones somebody emptied', () => {
    // A `PUT` replaces the whole rule. A description that was cleared has to arrive as nothing
    // rather than not arrive at all, or the server keeps the old one.
    const input = ruleInput(
      { match_type: 'mask', pattern: '/catalog/*', priority: 10, is_active: true },
      { title: { en: 'Catalogue' } },
    )

    expect(input.pattern).toBe('/catalog/*')
    expect(input.title).toEqual({ en: 'Catalogue' })
    expect(input.description).toBeNull()
    expect(input.og_image).toBeNull()
    expect(input.json_ld).toBeNull()
  })
})
