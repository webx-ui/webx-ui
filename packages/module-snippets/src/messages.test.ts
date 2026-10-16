import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { snippetsMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const lang = (group: string) =>
  resolve(here, '../../../php/packages/module-snippets/lang/en', `${group}.php`)

/** `'key' => …` at the top level of a Laravel language file — a nested list is one key. */
function keysOf(group: string): string[] {
  const source = readFileSync(lang(group), 'utf8')

  return [...source.matchAll(/^ {4}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/** `'key' => '…'` inside the nested list `name` of a language file. */
function nestedKeysOf(group: string, name: string): string[] {
  const source = readFileSync(lang(group), 'utf8')
  const body = source.split(`    '${name}' => [`)[1]?.split('    ],')[0] ?? ''

  return [...body.matchAll(/^ {8}'([^']+)' => /gm)].map((match) => match[1]!).sort()
}

/**
 * The two halves say the same things. A line on one side only is a module translated everywhere
 * with one English word in the middle of it, and nothing else notices, because a missing key is
 * not there to be compared.
 */
describe('the English here matches the English the server ships', () => {
  it.each(['module', 'screen', 'panel'])('%s', (group) => {
    const ours = Object.keys(snippetsMessages[group] ?? {}).sort()

    expect(ours).toEqual(keysOf(group))
  })

  it.each(['places', 'consent'])('module.%s', (name) => {
    const ours = Object.keys((snippetsMessages.module?.[name] ?? {}) as object).sort()

    expect(ours).toEqual(nestedKeysOf('module', name))
  })
})
