import type { Recipient, RecipientProblem, RecipientState } from './types'

/**
 * Whether a letter would reach somebody, judged on the recipients as they stand in the editor.
 *
 * The server says it for what was saved (`InboxForm.recipients`): who is deleted, who is
 * switched off. The editor has to say it before a save too, so this reads the list being edited
 * and borrows the server's verdict only for the administrators it already knows about — one
 * picked a moment ago from the list of people who may read the section is somebody who exists.
 */

/** Loose on purpose: the server checks the address for real, this only spots an empty line. */
const ADDRESS = /^[^\s@]+@[^\s@]+$/

export function isAdmin(recipient: Recipient): recipient is { admin_id: number } {
  return 'admin_id' in recipient
}

/** Why this recipient would not get a letter, or null when it would. */
export function problemOf(
  recipient: Recipient,
  reported: RecipientState[] = [],
): RecipientProblem | null {
  if (isAdmin(recipient)) {
    const known = reported.find(
      (one) => one.type === 'admin' && one.admin_id === recipient.admin_id,
    )

    return known?.problem ?? null
  }

  return ADDRESS.test((recipient.email ?? '').trim()) ? null : 'invalid_email'
}

/** Whether anybody on the list would be written to. */
export function reachesAnybody(
  recipients: Recipient[] | undefined,
  reported: RecipientState[] = [],
): boolean {
  return (recipients ?? []).some((recipient) => problemOf(recipient, reported) === null)
}
