import { describe, expect, it } from 'vitest'
import { foldKeys, localTimes } from './format'
import type { EventLine } from './types'

const line = (id: number, kind: string, trace = 'T1'): EventLine => ({
  id,
  trace,
  direction: 'out',
  kind,
  gateway: 'gateway-a',
  method_key: 'gateway-a-card',
  payment_id: 1,
  order_id: 1,
  source: 'poll',
  http_method: 'POST',
  status: 200,
  transport_error: null,
  request_size: null,
  response_size: null,
  truncated: false,
  at: '2026-11-03T12:00:00+00:00',
  duration_ms: 10,
  signature: null,
  reported: null,
  status_before: null,
  status_after: null,
  outcome: null,
  message: null,
  error: false,
})

describe('foldKeys', () => {
  it('folds a signing key under the request of its action that follows it', () => {
    const folded = foldKeys([line(1, 'key'), line(2, 'status'), line(3, 'key', 'T2')])

    expect(folded.map((one) => one.id)).toEqual([2, 3])
    expect(folded[0]?.keys.map((one) => one.id)).toEqual([1])
    // A key with nothing after it in its action stays a line of its own.
    expect(folded[1]?.keys).toEqual([])
  })

  it('reads the actions by id, whatever order the list is in', () => {
    const folded = foldKeys([line(4, 'capture'), line(3, 'key'), line(2, 'status'), line(1, 'key')])

    expect(folded.map((one) => [one.id, one.keys.map((key) => key.id)])).toEqual([
      [4, [3]],
      [2, [1]],
    ])
  })
})

describe('localTimes', () => {
  it('puts every moment of a decision in the words of the panel', () => {
    expect(
      localTimes(
        'no final answer of the gateway by 2026-11-03T12:29:21+00:00',
        () => 'today at 14:29',
      ),
    ).toBe('no final answer of the gateway by today at 14:29')
    expect(localTimes('nothing to change', () => 'x')).toBe('nothing to change')
  })
})
