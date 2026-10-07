import { flushPromises, shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey } from '@webx-ui/module-admin'
import MediaManager from './MediaManager.vue'

/*
 * How many times the library is asked, and nothing else: opening the manager used to ask for
 * the first folder's files twice — once on mount and once more when choosing that folder
 * tripped the watcher on it.
 */
const directories = vi.fn(() => Promise.resolve([{ id: 7, parent_id: null, title: 'Library' }]))
const files = vi.fn(() =>
  Promise.resolve({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 40, total: 0 },
    stats: { size: 0 },
  }),
)

vi.mock('./api', () => ({
  createMediaApi: () => ({ directories, files, thumb: () => '' }),
}))

describe('MediaManager', () => {
  it('asks for the files of the folder it opens once', async () => {
    shallowMount(MediaManager, {
      global: { provide: { [adminKey as symbol]: { can: () => true } } },
    })

    await flushPromises()

    expect(directories).toHaveBeenCalledTimes(1)
    expect(files).toHaveBeenCalledTimes(1)
    expect(files).toHaveBeenCalledWith(expect.objectContaining({ directory_id: 7, page: 1 }))
  })
})
