import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { inboxMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-inbox/lang/en', `${group}.php`)

/** `'key' => '…'` out of a flat Laravel language file. */
function keysOf(group: string): string[] {
  const source = readFileSync(lang(group), 'utf8')

  return [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/**
 * The two halves say the same things.
 *
 * This package ships English defaults so a key never reaches the screen while the dictionary
 * is still on its way; the server ships the same lines in ten languages and overrides them. A
 * line that exists on one side only is the failure that looks like nothing — a module
 * translated everywhere with one English dialog in the middle of it — and nothing else
 * notices it, because a missing key is not there to be compared.
 */
describe('the English here matches the English the server ships', () => {
  it.each(['module', 'panel', 'fields'])('%s', (group) => {
    const ours = Object.keys(inboxMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf(group))
  })
})
