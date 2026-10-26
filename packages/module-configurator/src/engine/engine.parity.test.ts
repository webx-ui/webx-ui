import { readdirSync, readFileSync } from 'node:fs'
import { dirname, resolve as resolvePath } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { price, resolve, select, set } from './engine'
import { validate } from './validator'

/**
 * The cases both engines are held to (§8): the directory `PricingParityTest` reads on the server,
 * read here from the monorepo — no copy, so a case changed is a case changed for both. A case is
 * checked as the server checks it: the schema first, then the values resolved strictly, then the
 * visitor's actions in order, then the price on the case's own day.
 */

const here = dirname(fileURLToPath(import.meta.url))
const directory = resolvePath(
  here,
  '../../../../php/packages/module-configurator/resources/fixtures/pricing',
)

interface Case {
  name: string
  today: string
  schema: unknown
  values: Record<string, unknown>
  actions?: ({ select: [string, string] } | { set: [string, number] })[]
  expect: Record<string, unknown> & {
    errors?: { path: string; code: string }[]
    lines?: Record<string, unknown>[]
    normalized?: Record<string, unknown>
  }
}

const cases: [string, Case][] = readdirSync(directory)
  .filter((file) => file.endsWith('.json'))
  .sort()
  .map((file) => [
    file.replace(/\.json$/, ''),
    JSON.parse(readFileSync(resolvePath(directory, file), 'utf8')) as Case,
  ])

/** Numbers are doubles in both copies; this is room for the last bit, not for a cent. */
const TOLERANCE = 1e-6

function numbers(
  expected: Record<string, unknown>,
  actual: Record<string, unknown>,
  where: string,
): void {
  expect(Object.keys(actual), `${where}: keys`).toEqual(Object.keys(expected))

  for (const [key, value] of Object.entries(expected)) {
    if (typeof value === 'number') {
      expect(typeof actual[key], `${where}.${key}`).toBe('number')
      expect(Math.abs((actual[key] as number) - value), `${where}.${key}`).toBeLessThan(TOLERANCE)
    } else {
      expect(actual[key], `${where}.${key}`).toEqual(value)
    }
  }
}

describe('the parity cases', () => {
  it('are there, as many as the server counts', () => {
    expect(cases.length).toBeGreaterThanOrEqual(22)
  })

  it.each(cases)('%s', (_file, one) => {
    const problems = validate(one.schema, 'en')

    if (problems.length > 0) {
      expect(problems.map(({ path, code }) => ({ path, code }))).toEqual(one.expect.errors ?? [])

      return
    }

    let state = resolve(one.schema, one.values)

    if (state.errors.length > 0) {
      expect(state.errors).toEqual(one.expect.errors ?? [])

      return
    }

    expect(one.expect.errors, `${one.name}: expected errors, got none`).toBeUndefined()

    for (const action of one.actions ?? []) {
      state =
        'select' in action
          ? select(one.schema, state, action.select[0], String(action.select[1]))
          : set(one.schema, state, action.set[0], Number(action.set[1]))
    }

    const quote = price(one.schema, state, one.today)

    numbers(one.expect.normalized ?? {}, quote.normalized, `${one.name}: normalized`)
    expect(quote.hidden, `${one.name}: hidden`).toEqual(one.expect.hidden)

    for (const key of ['area', 'subtotal', 'total_before_discount', 'total'] as const) {
      expect(
        Math.abs(quote[key] - (one.expect[key] as number)),
        `${one.name}: ${key}`,
      ).toBeLessThan(TOLERANCE)
    }

    expect(quote.lines, `${one.name}: lines`).toHaveLength(one.expect.lines?.length ?? 0)

    one.expect.lines?.forEach((line, at) => {
      numbers(
        line,
        quote.lines[at] as unknown as Record<string, unknown>,
        `${one.name}: line ${at}`,
      )
    })
  })
})
