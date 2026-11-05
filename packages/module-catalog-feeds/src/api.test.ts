// @vitest-environment node
import { describe, expect, it, vi } from 'vitest'
import type { AdminContext } from '@webx-ui/module-admin'
import { createFeedsApi, formValues, FORM_FIELDS, payloadOf } from './api'
import type { FeedValues } from './types'

const values = Object.fromEntries(
  FORM_FIELDS.map((field) => [field, field]),
) as unknown as FeedValues

describe('what a form sends', () => {
  it('shows the currency and does not send it: a feed is in the site’s', () => {
    const form = formValues({ ...values, currency: 'EUR' })

    expect(form.currency).toBe('EUR')
    expect(payloadOf(form)).not.toHaveProperty('currency')
    expect(Object.keys(payloadOf(form))).toEqual([...FORM_FIELDS])
  })

  it('leaves out what the server keeps for itself', () => {
    const form = formValues({ ...values, access_key: 'x', id: 7 } as unknown as FeedValues)

    expect(payloadOf(form)).not.toHaveProperty('access_key')
    expect(payloadOf(form)).not.toHaveProperty('id')
  })
})

describe('createFeedsApi()', () => {
  const http = {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn().mockResolvedValue({ data: {} }),
    put: vi.fn().mockResolvedValue({}),
    delete: vi.fn().mockResolvedValue(undefined),
  }
  const api = createFeedsApi({ apiPath: '/api/cms', http } as unknown as AdminContext)

  it('sends the feed the exclusions of which count, with the values of the form', async () => {
    await api.preview({ item: 'x' }, 3, [9])

    expect(http.post).toHaveBeenCalledWith('/api/cms/catalog/feeds/preview', {
      item: 'x',
      feed: 3,
      product_ids: [9],
    })
  })

  it('leaves an empty search out of the address', async () => {
    await api.exclusions(3, '', 2)

    expect(http.get).toHaveBeenCalledWith('/api/cms/catalog/feeds/3/exclusions?page=2')
  })
})
