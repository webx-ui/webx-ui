import { describe, expect, it } from 'vitest'
import {
  forgetKey,
  forgetValue,
  newStep,
  renameKey,
  renameValue,
  setText,
  written,
} from './document'
import { validate } from './engine/validator'
import type { ConfiguratorSchema } from './engine/types'

function schema(): ConfiguratorSchema {
  return {
    version: 1,
    currency: 'UAH',
    initial: { kind: 'corner', w: 900 },
    steps: [
      {
        key: 'kind',
        type: 'buttons',
        label: 'Kind',
        options: [
          {
            value: 'corner',
            label: 'Corner',
            area: 'w*h/1e6',
            defaults: { w: 900 },
            limits: { h: [1000, 2000] },
          },
          { value: 'niche', label: 'Niche', area: '(w+1)*h/1e6' },
        ],
      },
      {
        key: 'size',
        type: 'dimensions',
        title: 'Sizes',
        fields: [
          { key: 'w', label: 'Width', min: 100, max: 2000 },
          { key: 'h', label: 'Height', min: 100, max: 2000, when: { kind: ['corner', 'niche'] } },
        ],
      },
    ],
    previews: [{ when: { kind: ['niche'] }, image: 'media/x.svg' }],
  }
}

/*
 * The constructor renames and removes in place; what was built on a key must follow it, or the
 * check would call the document broken after a rename nobody meant as a break.
 */
describe('editing the document', () => {
  it('a step renamed takes its conditions and the starting choice along', () => {
    const doc = schema()

    // The editor sets the key itself; the references follow it.
    renameKey(doc, 'kind', 'shape')
    doc.steps[0]!.key = 'shape'

    expect(doc.steps[1]!.fields![1]!.when).toEqual({ shape: ['corner', 'niche'] })
    expect(doc.previews![0]!.when).toEqual({ shape: ['niche'] })
    expect(doc.initial).toEqual({ shape: 'corner', w: 900 })
    expect(validate(doc, 'en')).toEqual([])
  })

  it('a size field renamed takes the formulas, defaults and limits along, and only whole words', () => {
    const doc = schema()

    renameKey(doc, 'w', 'width')

    expect(doc.steps[0]!.options!.map((one) => one.area)).toEqual([
      'width*h/1e6',
      '(width+1)*h/1e6',
    ])
    expect(doc.steps[0]!.options![0]!.defaults).toEqual({ width: 900 })
    expect(doc.initial).toEqual({ kind: 'corner', width: 900 })
  })

  it('a value renamed or removed is renamed or dropped in every condition', () => {
    const doc = schema()

    renameValue(doc, 'kind', 'niche', 'recess')
    expect(doc.previews![0]!.when).toEqual({ kind: ['recess'] })

    forgetValue(doc, 'kind', 'corner')
    expect(doc.steps[1]!.fields![1]!.when).toEqual({ kind: ['recess'] })
    expect(doc.initial).toEqual({ w: 900 })

    forgetKey(doc, 'kind')
    expect(doc.steps[1]!.fields![1]!.when).toBeUndefined()
    expect(doc.previews![0]!.when).toEqual({})
  })

  it('an empty text is no text at all', () => {
    const item: Record<string, unknown> = { label: 'Old' }

    setText(item, 'label', { uk: '', en: '  ' })
    expect(item).toEqual({})
    expect(written({ uk: 'Скло', en: '' })).toEqual({ uk: 'Скло' })
  })

  it('a new step has a free key and something in it', () => {
    const doc = schema()
    const step = newStep(doc, 'dimensions', 'en')

    expect(step.key).toBe('size_2')
    expect(step.fields?.[0]?.key).not.toBe('w')
  })
})
