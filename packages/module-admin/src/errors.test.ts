// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { errorText } from './errors'
import { HttpError } from './http'

const t = (key: string) => key

function refused(message: string, errors: Record<string, string[]> = {}): HttpError {
  return new HttpError(message, 422, errors, null, { message, errors })
}

describe('errorText', () => {
  it('says the first refusal of a form without the summary Laravel adds after it', () => {
    const error = refused('Enter a phone or an e-mail. (and 1 more error)', {
      'customer.phone': ['Enter a phone or an e-mail.'],
      'customer.email': ['Enter a phone or an e-mail.'],
    })

    expect(errorText(error, t)).toBe('Enter a phone or an e-mail.')
  })

  it('keeps a refusal the module wrote whole', () => {
    expect(errorText(refused('The order is paid already.'), t)).toBe('The order is paid already.')
    expect(
      errorText(refused('Not now: the list is out of date.', { list: ['Not now'] }), t),
    ).toBe('Not now: the list is out of date.')
  })

  it('speaks for every other status in the panel words', () => {
    expect(errorText(new HttpError('No query results', 404), t)).toBe('errors.gone')
  })
})
