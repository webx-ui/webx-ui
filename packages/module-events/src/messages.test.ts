import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { eventsMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-events/lang/en', `${group}.php`)

/** `'key' => '…'` out of a flat Laravel language file. */
function keysOf(group: string): string[] {
  const source = readFileSync(lang(group), 'utf8')

  return [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/**
 * The two halves say the same things. A line on one side only is a module translated everywhere
 * with one English word in the middle of it, and nothing else notices, because a missing key is
 * not there to be compared.
 *
 * Skipped only while the php half is not on this branch: EV1 writes its `lang` on its own branch
 * in parallel, and EV3 merges the two and takes the skip away.
 */
const shipped = existsSync(resolve(here, '../../../php/packages/module-events/lang/en'))

describe.skipIf(!shipped)('the English here matches the English the server ships', () => {
  it.each(['module', 'panel', 'event', 'category'])('%s', (group) => {
    const ours = Object.keys(eventsMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf(group))
  })
})
