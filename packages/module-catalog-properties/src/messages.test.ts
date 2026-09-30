import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { catalogPropertiesMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (locale: string, group: string) =>
  resolve(here, '../../../php/packages/module-catalog-properties/lang', locale, `${group}.php`)

/** `'key' => '…'` out of a Laravel language file, the top level of it. */
function keysOf(locale: string, group: string): string[] {
  const source = readFileSync(lang(locale, group), 'utf8')

  return [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/**
 * The two halves say the same things. A line on one side only is a module translated everywhere
 * with one English word in the middle of it, and nothing else notices, because a missing key is
 * not there to be compared.
 */
describe('the English here matches the English the server ships', () => {
  it.each(Object.keys(catalogPropertiesMessages))('%s', (group) => {
    const ours = Object.keys(catalogPropertiesMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf('en', group))
  })
})

describe('the Russian has every line the English has', () => {
  it.each(Object.keys(catalogPropertiesMessages))('%s', (group) => {
    expect(keysOf('ru', group)).toEqual(keysOf('en', group))
  })
})
