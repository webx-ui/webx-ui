import { createModal } from '@webx-ui/core'
import AdminPicker from './AdminPicker.vue'
import type { Admin } from './types'

export interface AdminPickerOptions {
  /** Heading of the dialog, when the default is not specific enough. */
  title?: string
  /** Turns an avatar key into an address. Omitted, rows show initials. */
  resolveAvatar?: (key: string) => Promise<string | null>
}

const pick = createModal<Admin[], AdminPickerOptions & { multiple?: boolean }>(AdminPicker)

/**
 * One administrator, chosen from a dialog.
 *
 * ```ts
 * const owner = await selectAdmin()
 * if (owner) task.assignedTo = owner.id
 * ```
 *
 * Resolves with `undefined` when the dialog was closed without choosing. Needs `admins.view`,
 * which is deliberately separate from managing them: assigning work to somebody is not the
 * same as being allowed to edit their account.
 */
export async function selectAdmin(options: AdminPickerOptions = {}): Promise<Admin | undefined> {
  const chosen = await pick({ ...options, multiple: false })

  return chosen?.[0]
}

/**
 * Several administrators, ticked and confirmed.
 *
 * Resolves with `undefined` when the dialog was closed, and never with an empty list — the
 * confirming button is disabled until somebody is ticked, so "none" can only mean "cancelled".
 */
export function selectAdmins(options: AdminPickerOptions = {}): Promise<Admin[] | undefined> {
  return pick({ ...options, multiple: true })
}
