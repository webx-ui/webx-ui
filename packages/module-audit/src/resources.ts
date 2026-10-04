import type { AuditResourceRow } from './types'

/**
 * Whether the browser is worth asking for a picture of the page: the run reached it and it
 * answered. One past the run's limit or on the stand was never asked, and one that failed for the
 * run would fail here too — the card keeps the placeholder for those, with nothing to open.
 */
export function viewable(row: AuditResourceRow): boolean {
  return row.checked && row.status !== null && row.status < 400 && row.error === null
}
