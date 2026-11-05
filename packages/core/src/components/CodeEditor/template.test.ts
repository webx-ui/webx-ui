import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import {
  escapeForFormat,
  knows,
  refuseInCdata,
  renderTemplate,
  snippetOf,
  templateMessages,
  TemplateRefusal,
  type TemplateFormat,
} from './template'

const here = dirname(fileURLToPath(import.meta.url))
const library = resolve(here, '../../../../../php/packages/templates')

interface Case {
  name: string
  format: TemplateFormat
  options?: { delimiter?: string }
  template: string
  values: Record<string, unknown>
  text?: string
  unknown?: string[]
  error?: string
  cdata_error?: string
}

/* The same file `ParityTest` reads on the server: one set of cases, two renderers. */
const { cases } = JSON.parse(readFileSync(`${library}/tests/fixtures/render.json`, 'utf8')) as {
  cases: Case[]
}

function refusalOf(run: () => unknown): TemplateRefusal {
  try {
    run()
  } catch (error) {
    if (error instanceof TemplateRefusal) return error

    throw error
  }

  throw new Error('accepted')
}

describe('the browser renders what the server writes', () => {
  it.each(cases.map((one) => [one.name, one] as const))('%s', (_, one) => {
    const escape = escapeForFormat(one.format, one.options)

    if (one.cdata_error !== undefined) {
      const refusal = refusalOf(() => refuseInCdata(one.template))

      expect(refusal.key).toBe('template-cdata')
      expect(refusal.replace).toEqual({ name: one.cdata_error })

      return
    }

    if (one.error !== undefined) {
      expect(refusalOf(() => renderTemplate(one.template, {}, escape)).key).toBe(one.error)

      return
    }

    refuseInCdata(one.template)

    const rendered = renderTemplate(one.template, one.values, escape)

    expect(rendered.text).toBe(one.text)

    if (one.unknown !== undefined) expect(rendered.unknown).toEqual(one.unknown)
  })
})

describe('knows', () => {
  const variables = [
    { name: 'name' },
    { name: 'images', fields: [{ name: 'url', label: 'Address' }] },
    { name: 'field.*' },
  ]

  it('reads a name the way the server does', () => {
    expect(knows(variables, 'url', ['images'])).toBe(true)
    expect(knows(variables, 'name', ['images'])).toBe(true)
    expect(knows(variables, 'field.phone', [])).toBe(true)
    expect(knows(variables, 'url', [])).toBe(false)
  })

  it('takes first and last inside a list and nowhere else', () => {
    expect(knows(variables, 'first', ['images'])).toBe(true)
    expect(knows(variables, 'last', ['images'])).toBe(true)
    expect(knows(variables, 'first', [])).toBe(false)
    expect(knows(variables, 'first', ['name'])).toBe(false)
  })
})

describe('snippetOf', () => {
  it('writes what a click on a variable puts in', () => {
    expect(snippetOf({ name: 'note' })).toBe('{{note}}')
    expect(snippetOf({ name: 'note' }, 'has')).toBe('{{#note}}{{note}}{{/note}}')
    expect(snippetOf({ name: 'note' }, 'empty')).toBe('{{^note}}{{/note}}')
    expect(snippetOf({ name: 'field.*' })).toBe('{{field.name}}')
    expect(snippetOf({ name: 'items', fields: [{ name: 'qty', label: 'Qty' }] })).toBe(
      '{{#items}}\n{{qty}}\n{{/items}}',
    )
  })
})

describe('the English of the refusals', () => {
  it('is the English the server ships', () => {
    const lang = readFileSync(`${library}/lang/en/errors.php`, 'utf8')
    const lines = Object.fromEntries(
      [...lang.matchAll(/^ {4}'([^']+)' => '((?:[^'\\]|\\.)*)',$/gm)].map((line) => [
        line[1],
        line[2],
      ]),
    )

    expect(templateMessages.errors).toEqual(lines)
  })
})
