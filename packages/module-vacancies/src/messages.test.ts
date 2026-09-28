import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { employmentKey, vacanciesMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-vacancies/lang/en', `${group}.php`)

/** `'key' => '…'` out of a flat Laravel language file. */
function keysOf(group: string): string[] {
  const source = readFileSync(lang(group), 'utf8')

  return [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/**
 * The two halves say the same things. A line on one side only is a module translated everywhere
 * with one English word in the middle of it, and nothing else notices, because a missing key is
 * not there to be compared.
 */
describe('the English here matches the English the server ships', () => {
  it.each(['module', 'panel', 'editor', 'category'])('%s', (group) => {
    const ours = Object.keys(vacanciesMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf(group))
  })

  /* The site's group: the panel borrows a few of its words, and each of them has to be there. */
  it('vacancy', () => {
    const theirs = keysOf('vacancy')

    for (const key of Object.keys(vacanciesMessages.vacancy ?? {})) {
      expect(theirs).toContain(key)
    }
  })
})

describe('employment types', () => {
  it('are named by their schema.org code', () => {
    expect(employmentKey('FULL_TIME')).toBe('vacancy.employment.FULL_TIME')
    expect(employmentKey('PER_DIEM')).toBe('vacancy.employment.PER_DIEM')
  })

  it('have a word for each of the eight Google lists', () => {
    const codes = [
      'FULL_TIME',
      'PART_TIME',
      'CONTRACTOR',
      'TEMPORARY',
      'INTERN',
      'VOLUNTEER',
      'PER_DIEM',
      'OTHER',
    ]

    for (const code of codes) {
      const employment = vacanciesMessages.vacancy?.employment as Record<string, string> | undefined

      expect(employment?.[code]).toBeTruthy()
    }
  })
})
