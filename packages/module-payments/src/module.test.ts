// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { payments } from './module'

describe('payments()', () => {
  it('is three sections, the methods first with the tab of an order', () => {
    const sections = payments()

    expect(sections.map((one) => [one.id, one.path])).toEqual([
      ['payments', '/payments'],
      ['payments-attempts', '/payments/attempts'],
      ['payments-exchange', '/payments/exchange'],
    ])
    expect(Object.keys(sections[0]?.types ?? {})).toEqual(['wx-payments-order'])
    // The tab «Payment» the server patches onto the order's card draws, it edits nothing.
    expect(sections[0]?.types?.['wx-payments-order']?.kind).toBe('display')
  })

  it('moves as a whole', () => {
    expect(payments({ path: '/money' }).map((one) => one.routes?.[0]?.path)).toEqual([
      '/money',
      '/money/attempts',
      '/money/exchange',
    ])
  })
})
