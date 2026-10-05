import type { Component } from 'vue'
import type { AdminContext } from '@webx-ui/module-admin'
import type { AvatarResolver } from './session'

/**
 * The photograph's field and its address, from whatever library this panel has.
 *
 * This package does not depend on the media one, and it used to take both pieces from the
 * panel's own `admin.ts` — which nobody wrote, because `webx:panel --sync` does not, so a site
 * with a library still had no photographs. The library already registers its field as a screen
 * type and answers "where does this key live now" for the editors; the same two answer here.
 * Without a library both are null, and a person is their initials.
 */
export function libraryAvatarField(admin: AdminContext): Component | null {
  return admin.types['wx-media']?.component ?? null
}

export function libraryAvatarResolver(admin: AdminContext): AvatarResolver | null {
  const urls = admin.assetUrls

  return urls ? async (key) => (await urls([key]))[key] ?? null : null
}
