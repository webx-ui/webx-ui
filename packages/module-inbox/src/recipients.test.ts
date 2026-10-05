import { describe, expect, it } from 'vitest'
import { problemOf, reachesAnybody } from './recipients'
import type { RecipientState } from './types'

const retired: RecipientState = {
  type: 'admin',
  admin_id: 7,
  name: 'Ada',
  email: 'ada@example.test',
  receives: false,
  problem: 'admin_inactive',
}

/**
 * Whether a form would tell anybody, judged on the list being edited: the server's word for the
 * administrators it knows, the shape of an address for the ones being typed.
 */
describe('recipients', () => {
  it('says nobody is reached by an empty list', () => {
    expect(reachesAnybody(undefined)).toBe(false)
    expect(reachesAnybody([])).toBe(false)
  })

  it('takes the server at its word about an administrator it knows', () => {
    expect(problemOf({ admin_id: 7 }, [retired])).toBe('admin_inactive')
    expect(reachesAnybody([{ admin_id: 7 }], [retired])).toBe(false)
  })

  it('trusts an administrator just picked from the list of people who may read the section', () => {
    expect(reachesAnybody([{ admin_id: 8 }], [retired])).toBe(true)
  })

  it('does not count a blank or misshapen address as somebody', () => {
    expect(reachesAnybody([{ email: '' }, { email: 'sales' }])).toBe(false)
    expect(reachesAnybody([{ email: 'sales@example.test' }])).toBe(true)
  })
})
