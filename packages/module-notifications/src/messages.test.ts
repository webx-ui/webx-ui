// @vitest-environment node
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { notificationsMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  readFileSync(
    resolve(here, '../../../php/packages/module-notifications/lang/en', `${group}.php`),
    'utf8',
  )

/** `'key' => …` at the top level of a Laravel language file — a nested list is one key. */
function keysOf(group: string): string[] {
  return [...lang(group).matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/** `'key' => '…'` inside the nested list `name` of a language file. */
function nestedKeysOf(group: string, name: string): string[] {
  const body = lang(group).split(`    '${name}' => [`)[1]?.split('    ],')[0] ?? ''

  return [...body.matchAll(/^ {8}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/** The English of a top-level line, as PHP has it in single quotes. */
function lineOf(group: string, key: string): string | undefined {
  const escaped = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const found = new RegExp(`^ {4}'${escaped}' => '((?:[^'\\\\]|\\\\.)*)',$`, 'm').exec(lang(group))

  return found?.[1]?.replace(/\\'/g, "'").replace(/\\\\/g, '\\')
}

/**
 * The two halves say the same things. A line on one side only is a module translated everywhere
 * with one English word in the middle of it, and nothing else notices, because a missing key is
 * not there to be compared.
 */
describe('the English here matches the English the server ships', () => {
  it('panel: the same keys', () => {
    expect(Object.keys(notificationsMessages.panel ?? {}).sort()).toEqual(keysOf('panel'))
  })

  it.each(['panel', 'screen', 'errors'])('%s: the same words', (group) => {
    for (const [key, words] of Object.entries(notificationsMessages[group] ?? {})) {
      expect([key, lineOf(group, key)]).toEqual([key, words])
    }
  })

  it.each(['statuses', 'reasons'])('module.%s', (name) => {
    const ours = Object.keys((notificationsMessages.module?.[name] ?? {}) as object).sort()

    expect(ours).toEqual(nestedKeysOf('module', name))
  })
})
