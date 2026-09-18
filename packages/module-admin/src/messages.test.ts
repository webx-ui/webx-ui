import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { adminMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-admin/lang/en', `${group}.php`)

/** `'key' => '…'` out of a flat Laravel language file. */
function keysOf(group: string): string[] {
  const source = readFileSync(lang(group), 'utf8')
  const keys = [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!)

  return keys.sort()
}

/**
 * The two halves say the same things.
 *
 * `screens` is left out on purpose: those lines label screens the server assembles, and the
 * browser never asks for one by key.
 */
describe('the English here matches the English the server ships', () => {
  it.each(['shell', 'nav', 'dates', 'errors', 'notes'])('%s', (group) => {
    const ours = Object.keys(adminMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf(group))
  })
})
