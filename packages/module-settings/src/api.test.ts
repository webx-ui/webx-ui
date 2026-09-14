import { describe, expect, it, vi } from 'vitest'
import type { AdminContext } from '@webx-ui/module-admin'
import { createSettingsApi } from './api'

function context(get: ReturnType<typeof vi.fn>, put: ReturnType<typeof vi.fn>): AdminContext {
  return { apiPath: '/api/cms', http: { get, put } } as unknown as AdminContext
}

describe('createSettingsApi', () => {
  it('reads the values from under the panel API path', async () => {
    const get = vi
      .fn()
      .mockResolvedValue({ data: { values: { 'general.project-name': { en: 'Acme' } } } })
    const api = createSettingsApi(context(get, vi.fn()))

    await expect(api.load()).resolves.toEqual({ 'general.project-name': { en: 'Acme' } })
    expect(get).toHaveBeenCalledWith('/api/cms/settings')
  })

  it('sends the values back under a `values` key and returns what the server kept', async () => {
    const put = vi
      .fn()
      .mockResolvedValue({ data: { values: { 'general.project-name': { en: 'Globex' } } } })
    const api = createSettingsApi(context(vi.fn(), put))

    await expect(api.save({ 'general.project-name': { en: 'Globex' }, extra: 1 })).resolves.toEqual(
      {
        'general.project-name': { en: 'Globex' },
      },
    )
    expect(put).toHaveBeenCalledWith('/api/cms/settings', {
      values: { 'general.project-name': { en: 'Globex' }, extra: 1 },
    })
  })
})
