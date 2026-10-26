import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import type { Messages } from '@webx-ui/module-admin'
import { configuratorsMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-configurator/lang/en', `${group}.php`)

/** Every key of a Laravel language file as a dotted path, nested arrays included. */
function keysOf(group: string): string[] {
  const keys: string[] = []
  const path: string[] = []

  for (const line of readFileSync(lang(group), 'utf8').split('\n')) {
    const open = /^( +)'([^']+)' => \[$/.exec(line)
    const leaf = /^( +)'([^']+)' => /.exec(line)

    if (open) {
      path.splice(open[1]!.length / 4 - 1, Infinity, open[2]!)
    } else if (/^ +\],$/.test(line)) {
      path.pop()
    } else if (leaf) {
      path.splice(leaf[1]!.length / 4 - 1)
      keys.push([...path, leaf[2]!].join('.'))
    }
  }

  return keys.sort()
}

function flat(messages: Messages, prefix = ''): string[] {
  return Object.entries(messages).flatMap(([key, value]) =>
    typeof value === 'string' ? [`${prefix}${key}`] : flat(value, `${prefix}${key}.`),
  )
}

/**
 * The two halves say the same things. A line on one side only is a module translated everywhere
 * with one English word in the middle of it, and nothing else notices.
 */
describe('the English here matches the English the server ships', () => {
  it.each(['module', 'configurator', 'errors', 'site'])('%s', (group) => {
    expect(flat(configuratorsMessages[group] ?? {}).sort()).toEqual(keysOf(group))
  })
})
