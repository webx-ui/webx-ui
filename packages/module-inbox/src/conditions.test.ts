// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { cleanCondition, describeCondition, problemsOf } from './conditions'
import type { InboxField } from './types'

const field = (id: number, key: string, type: InboxField['type'], options = {}): InboxField =>
  ({
    id,
    key,
    name: key,
    type,
    title: { en: key[0]!.toUpperCase() + key.slice(1) },
    options,
  }) as unknown as InboxField

const purpose = field(1, 'purpose', 'radio', {
  choices: [
    { value: 'agency', label: { en: 'Agency meeting', ru: 'Встреча агентства' } },
    { value: 'question', label: 'Just a question' },
  ],
})
const note = field(2, 'note', 'text')
const agent = field(3, 'agent', 'consent')

describe('conditions, as the panel reads them', () => {
  it('cleans a condition the way the server keeps it', () => {
    expect(cleanCondition({ purpose: ['agency', 'agency', ' '], agent: true, '': ['x'] })).toEqual({
      purpose: ['agency'],
      agent: ['1'],
    })
    expect(cleanCondition({ purpose: [] })).toBeNull()
    expect(cleanCondition('purpose')).toBeNull()
  })

  it('finds what the server would refuse, by its code', () => {
    const asked = field(4, 'asked', 'text', {
      when: { purpose: ['agency', 'tender'], note: ['x'] },
      unless: { gone: ['1'], later: ['a'] },
    })
    const later = field(5, 'later', 'select', { choices: [{ value: 'a', label: 'A' }] })

    expect(problemsOf(asked, [purpose, note, agent, asked, later])).toEqual([
      { code: 'unknown_value', key: 'when', name: 'purpose', value: 'tender' },
      { code: 'not_choice', key: 'when', name: 'note' },
      { code: 'unknown_field', key: 'unless', name: 'gone' },
      { code: 'not_before', key: 'unless', name: 'later' },
    ])

    const fine = field(6, 'fine', 'text', { when: { purpose: ['agency'], agent: ['1'] } })
    expect(problemsOf(fine, [purpose, agent, fine])).toEqual([])
  })

  it('says a condition in the labels of what it names', () => {
    const fields = [purpose, agent]

    expect(
      describeCondition({ purpose: ['agency', 'question'], agent: ['1'] }, fields, 'ru', 'Ticked'),
    ).toBe('Purpose = Встреча агентства, Just a question · Agent = Ticked')
    expect(describeCondition({ gone: ['x'] }, fields, 'en', 'Ticked')).toBe('gone = x')
  })
})
