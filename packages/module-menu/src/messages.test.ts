import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { menuMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-menu/lang/en', `${group}.php`)

/** `'key' => '…'` out of a flat Laravel language file. */
function keysOf(group: string): string[] {
  const source = readFileSync(lang(group), 'utf8')
  const keys = [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!)

  return keys.sort()
}

/**
 * The two halves say the same things.
 *
 * A line that exists on one side only is the failure that looks like nothing: a module
 * translated into ten languages with one English dialog in the middle of it.
 */
describe('the English here matches the English the server ships', () => {
  it.each(['module', 'menu'])('%s', (group) => {
    const ours = Object.keys(menuMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf(group))
  })
})
