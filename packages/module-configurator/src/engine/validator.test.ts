import { describe, expect, it } from 'vitest'
import { isSwatch, validate } from './validator'

const base = {
  version: 1,
  currency: 'UAH',
  steps: [{ key: 'kind', type: 'buttons', label: 'Kind', options: [{ value: 'a', label: 'A' }] }],
}

const codes = (schema: unknown) => validate(schema, 'en').map(({ path, code }) => `${path} ${code}`)

/*
 * The JS check underlines before a save what the server would refuse; the parity cases hold the
 * paths and codes of the pricing ones, and these the rest the editor shows most.
 */
describe('validate()', () => {
  it('passes a whole document', () => {
    expect(codes(base)).toEqual([])
  })

  it('refuses what is not a document at all', () => {
    expect(codes([1])).toEqual([' schema.invalid'])
    expect(codes({ ...base, version: 2, currency: 'uah' })).toEqual([
      'version schema.version',
      'currency currency.invalid',
    ])
  })

  it('says where a text, a key and a value are wrong', () => {
    const doc = {
      ...base,
      steps: [
        {
          key: 'Kind',
          type: 'buttons',
          label: { en: '' },
          options: [{ value: 'A B', label: 'A' }, { value: 'b' }],
        },
      ],
    }

    expect(codes(doc)).toEqual([
      'steps.0.key key.invalid',
      'steps.0.label label.missing',
      'steps.0.options.0.value value.invalid',
      'steps.0.options.1.label label.missing',
    ])
  })

  it('refuses a condition on a step below or on a value nobody has', () => {
    const doc = {
      ...base,
      steps: [
        { ...base.steps[0], when: { glass: ['x'] } },
        {
          key: 'glass',
          type: 'buttons',
          label: 'Glass',
          when: { kind: ['z'] },
          options: [{ value: 'x', label: 'X' }],
        },
      ],
    }

    expect(codes(doc)).toEqual([
      'steps.0.when.glass condition.forward',
      'steps.1.when.kind.0 condition.unknown_value',
    ])
  })

  it('tells a card picture that is not a library key once', () => {
    const doc = {
      ...base,
      steps: [
        {
          ...base.steps[0],
          type: 'cards',
          options: [{ value: 'a', label: 'A', image: { id: 35 } }],
        },
      ],
    }

    expect(codes(doc)).toEqual(['steps.0.options.0.image image.missing'])
  })

  it('refuses a discount out of range and a date that is not one', () => {
    expect(codes({ ...base, discount: { percent: 100, until: '2026-02-30' } })).toEqual([
      'discount.percent discount.percent',
      'discount.until discount.until',
    ])
  })
})

describe('isSwatch()', () => {
  it('takes colours and gradients of hex colours, nothing that could carry other CSS', () => {
    expect(isSwatch('#59616a')).toBe(true)
    expect(isSwatch('linear-gradient(135deg, #b8862b 0%, #f3d27a 100%)')).toBe(true)
    expect(isSwatch('url(x.png)')).toBe(false)
    expect(isSwatch('linear-gradient(red, blue)')).toBe(false)
    expect(isSwatch('#fff; background: url(x)')).toBe(false)
  })
})
