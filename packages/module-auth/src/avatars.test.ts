import { describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import type { AdminContext } from '@webx-ui/module-admin'
import { libraryAvatarField, libraryAvatarResolver } from './avatars'

const MediaField = defineComponent({ name: 'MediaField', render: () => null })

function context(withLibrary: boolean): AdminContext {
  return {
    types: withLibrary ? { 'wx-media': { component: MediaField, kind: 'field' } } : {},
    assetUrls: withLibrary
      ? vi.fn(async (paths: string[]) =>
          Object.fromEntries(paths.map((path) => [path, `/storage/${path}`])),
        )
      : null,
  } as unknown as AdminContext
}

describe('photographs from the library', () => {
  it('take the field and the address from a panel that has a library', async () => {
    const admin = context(true)

    expect(libraryAvatarField(admin)).toBe(MediaField)
    expect(await libraryAvatarResolver(admin)?.('media/ab/me.jpg')).toBe('/storage/media/ab/me.jpg')
  })

  it('leave a panel without one with initials', () => {
    const admin = context(false)

    expect(libraryAvatarField(admin)).toBeNull()
    expect(libraryAvatarResolver(admin)).toBeNull()
  })
})
