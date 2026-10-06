import { describe, expect, it } from 'vitest'
import { differences, splitMiddle, statusType } from './addresses'

describe('differences', () => {
  it('names a trailing slash', () => {
    expect(
      differences(
        'https://www.linkedin.com/in/someone-59b49931/',
        'https://www.linkedin.com/in/someone-59b49931',
      ),
    ).toEqual(['slash'])
  })

  it('names www and https together', () => {
    expect(differences('http://shop.com/a', 'https://www.shop.com/a/')).toEqual([
      'https',
      'www',
      'slash',
    ])
  })

  it('is empty when the page really moved', () => {
    expect(differences('https://shop.com/old', 'https://shop.com/new')).toEqual([])
    expect(differences('https://shop.com/a?x=1', 'https://shop.com/a/?x=2')).toEqual([])
    expect(differences('https://shop.com/a', 'http://shop.com/a')).toEqual([])
  })

  it('reads a relative target against the source', () => {
    expect(differences('https://shop.com/a', '/a/')).toEqual(['slash'])
  })

  it('is empty for what is not an address', () => {
    expect(differences('', 'https://shop.com')).toEqual([])
  })
})

describe('splitMiddle', () => {
  it('keeps a short address whole', () => {
    expect(splitMiddle('https://shop.com/a')).toEqual(['https://shop.com/a', ''])
  })

  it('keeps the end of a long one', () => {
    const [head, tail] = splitMiddle('https://www.linkedin.com/in/someone-else-59b49931/')

    expect(tail).toBe('else-59b49931/')
    expect(head + tail).toBe('https://www.linkedin.com/in/someone-else-59b49931/')
  })
})

describe('statusType', () => {
  it('colours a temporary redirect apart from a permanent one', () => {
    expect(statusType(301)).toBe('info')
    expect(statusType(302)).toBe('warning')
    expect(statusType(404)).toBe('warning')
    expect(statusType(500)).toBe('danger')
    expect(statusType(200)).toBe('success')
  })
})
