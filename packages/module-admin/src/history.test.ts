import { describe, expect, it } from 'vitest'
import { historyValue } from './history'

describe('historyValue', () => {
  it('reads a list of names as the names', () => {
    expect(historyValue(['Skirts', 'Home'], '—')).toBe('Skirts, Home')
  })

  it('says nothing for an empty list', () => {
    expect(historyValue([], '—')).toBe('—')
  })

  it('keeps JSON for a list of rows', () => {
    expect(historyValue([{ q: 'Why?' }], '—')).toBe('[{"q":"Why?"}]')
  })
})
