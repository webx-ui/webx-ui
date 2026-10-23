import { describe, expect, it } from 'vitest'
import { DEFAULT_TOOLS, groupTools } from './tools'

describe('groupTools', () => {
  it('splits the list where the dividers are', () => {
    expect(groupTools(['bold', 'italic', 'divider', 'h2', 'divider', 'undo'])).toEqual([
      ['bold', 'italic'],
      ['h2'],
      ['undo'],
    ])
  })

  it('makes no empty group of a divider at an end or of two in a row', () => {
    expect(groupTools(['divider', 'bold', 'divider', 'divider', 'italic', 'divider'])).toEqual([
      ['bold'],
      ['italic'],
    ])
    expect(groupTools(['divider'])).toEqual([])
  })

  it('loses no button of the default toolbar', () => {
    expect(groupTools(DEFAULT_TOOLS).flat()).toEqual(DEFAULT_TOOLS.filter((t) => t !== 'divider'))
  })
})
