import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { coreTypes, typesTable } from './registry'

const here = (path: string) => fileURLToPath(new URL(path, import.meta.url))

/**
 * The guide and the docs site carry copies of what this package defines. These tests
 * fail when a copy falls behind, so the list of types and the JSON schemas never live
 * in two places for real.
 */
describe('documentation stays in step with the package', () => {
  it('the type table in guide/screens.md is generated from the registry', () => {
    const guide = readFileSync(here('../../../apps/docs/guide/screens.md'), 'utf8')
    // Markdown wants a blank line between an HTML comment and a table, hence the trim.
    const match = guide.match(/<!-- types:start -->([\s\S]*?)<!-- types:end -->/)

    expect(match, 'markers <!-- types:start --> / <!-- types:end --> missing').not.toBeNull()
    expect(match![1]!.trim()).toBe(typesTable(coreTypes))
  })

  it.each(['screen', 'patch'])('the docs site serves the %s schema verbatim', (name) => {
    const source = readFileSync(here(`../schemas/${name}.schema.json`), 'utf8')
    const served = readFileSync(here(`../../../apps/docs/public/schema/${name}.json`), 'utf8')

    expect(JSON.parse(served)).toEqual(JSON.parse(source))
  })
})
