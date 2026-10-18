import type { AdminContext } from '@webx-ui/module-admin'

/**
 * Who may open the library to choose from it: `media.view`, or `media.manage`, which names no
 * `view` by itself and still has to see what it moves and deletes (§5.4 of the permissions
 * spec). The server answers the same pair — this only keeps a field from offering a dialog
 * that would open on a 403.
 */
export const MEDIA_BROWSE = ['media.view', 'media.manage']

/**
 * Whether this administrator may browse the library. Without a panel — a demo page, a test —
 * there is nobody to ask, and the field behaves as it always has.
 */
export function canBrowseMedia(admin: AdminContext | null): boolean {
  return admin === null || MEDIA_BROWSE.some((permission) => admin.can(permission))
}
