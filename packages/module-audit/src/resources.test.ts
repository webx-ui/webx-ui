import { describe, expect, it } from 'vitest'
import { viewable } from './resources'
import type { AuditResourceRow } from './types'

const picture = (extra: Partial<AuditResourceRow> = {}): AuditResourceRow => ({
  id: 1,
  url: 'https://shop.example.com/storage/spade.jpg',
  kind: 'img',
  alt: null,
  host_class: 'own',
  checked: true,
  status: 200,
  error: null,
  location: null,
  content_type: 'image/jpeg',
  bytes: 421_000,
  cache_control: null,
  compression: null,
  width: null,
  height: null,
  ...extra,
})

describe('viewable', () => {
  it('asks the browser only for a picture that answered the run', () => {
    expect(viewable(picture())).toBe(true)
    // A redirect is followed by the browser as it was by the run.
    expect(viewable(picture({ status: 301 }))).toBe(true)
  })

  it('keeps the placeholder for the stand, the unchecked and the broken', () => {
    expect(viewable(picture({ host_class: 'dev', checked: false, status: null }))).toBe(false)
    expect(viewable(picture({ status: 404 }))).toBe(false)
    expect(viewable(picture({ status: 500 }))).toBe(false)
    expect(viewable(picture({ status: null, error: 'Connection refused' }))).toBe(false)
  })
})
