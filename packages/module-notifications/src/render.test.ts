import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { escapeFor, examplesOf, renderMessage, TemplateRefusal } from './render'
import type { NotificationEvent } from './types'

const here = dirname(fileURLToPath(import.meta.url))

interface Case {
  name: string
  provider: string
  template: string
  subject?: string
  values: Record<string, unknown>
  repeat?: { name: string; text: string; times: number }
  body?: string
  plain?: string
  subject_out?: string
  truncated?: boolean
  length?: number
  unknown?: string[]
  error?: string
}

/* The same file `PreviewParityTest` reads on the server: one set of cases, two renderers. */
const { cases } = JSON.parse(
  readFileSync(
    resolve(here, '../../../php/packages/module-notifications/tests/fixtures/render.json'),
    'utf8',
  ),
) as { cases: Case[] }

const LIMITS: Record<string, number | null> = { telegram: 4096, email: null }

describe('the preview renders what the server sends', () => {
  it.each(cases.map((one) => [one.name, one] as const))('%s', (_, one) => {
    const values = { ...one.values }

    if (one.repeat) values[one.repeat.name] = one.repeat.text.repeat(one.repeat.times)

    const render = () =>
      renderMessage({
        provider: one.provider,
        template: one.template,
        subject: one.subject ?? null,
        hasSubject: one.provider === 'email',
        limit: LIMITS[one.provider] ?? null,
        values,
      })

    if (one.error) {
      expect(render).toThrow(TemplateRefusal)

      try {
        render()
      } catch (error) {
        expect((error as TemplateRefusal).key).toBe(one.error)
      }

      return
    }

    const message = render()

    if (one.body !== undefined) expect(message.body).toBe(one.body)
    if (one.plain !== undefined) expect(message.plain).toBe(one.plain)
    if (one.subject_out !== undefined) expect(message.subject).toBe(one.subject_out)

    if (one.truncated !== undefined) {
      expect(message.truncated).toBe(one.truncated)
      expect([...message.plain]).toHaveLength(one.length ?? 0)
      expect(message.body).toBe(escapeFor(one.provider)(message.plain))
    }

    expect(message.unknown).toEqual(one.unknown ?? [])
  })
})

describe('the examples of an event', () => {
  const event: NotificationEvent = {
    key: 'shop.order',
    label: 'Order placed',
    module: 'shop',
    sample: {},
    variables: [
      { name: 'number', label: 'Number', example: '42', address: null, fields: [] },
      { name: 'field.*', label: 'A field', example: '', address: null, fields: [] },
      {
        name: 'items',
        label: 'Lines',
        example: [{ name: 'Tea' }],
        address: null,
        fields: [{ name: 'name', label: 'Name' }],
      },
    ],
  }

  it('are the values of a test, without the families', () => {
    expect(examplesOf(event)).toEqual({ number: '42', items: [{ name: 'Tea' }] })
  })

  it('know the fields of a list and the families, so neither is reported', () => {
    const message = renderMessage({
      provider: 'email',
      template: '{{number}} {{field.phone}} {{#items}}{{name}}{{/items}} {{nope}}',
      values: examplesOf(event),
      event,
    })

    expect(message.body).toBe('42  Tea ')
    expect(message.unknown).toEqual(['nope'])
  })
})
