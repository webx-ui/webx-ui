import type { BadgeType } from '@webx-ui/core'
import type { EventLine, PaymentStatus } from './types'

/** The colour of an attempt's status: what a manager scans a column for. */
export const statusTones: Record<PaymentStatus, BadgeType> = {
  created: 'default',
  pending: 'info',
  authorized: 'warning',
  paid: 'success',
  failed: 'danger',
  cancelled: 'default',
  expired: 'default',
}

/** The colour of a decision of the journal. */
export function outcomeTone(outcome: string | null): BadgeType {
  switch (outcome) {
    case 'applied':
      return 'success'
    case 'same':
    case 'duplicate':
    case 'ignored':
      return 'default'
    case 'rejected':
    case 'unmatched':
    case 'error':
      return 'danger'
    default:
      return 'default'
  }
}

/** The colour of the HTTP code of an event: an error is red, a redirect neutral. */
export function codeTone(event: Pick<EventLine, 'status' | 'transport_error'>): BadgeType {
  if (event.transport_error) return 'danger'
  if (event.status === null) return 'default'
  if (event.status >= 400) return 'danger'
  if (event.status >= 300) return 'default'

  return 'success'
}

/** A body's size the way it reads: bytes up to a kilobyte, then kilobytes with one decimal. */
export function size(bytes: number | null, b: string, kb: (size: string) => string): string {
  if (bytes === null) return ''
  if (bytes < 1024) return b.replace(':size', String(bytes))

  return kb((bytes / 1024).toFixed(1))
}

/**
 * A body with its indents, only on screen (§20.7): JSON parsed and printed again, XML broken at
 * its tags; anything else as it is. The stored bytes are never touched.
 */
export function pretty(body: string): string {
  const text = body.trim()

  if (text.startsWith('{') || text.startsWith('[')) {
    try {
      return JSON.stringify(JSON.parse(text), null, 2)
    } catch {
      return body
    }
  }

  if (text.startsWith('<')) {
    let depth = 0
    const lines: string[] = []

    for (const part of text.replace(/>\s*</g, '><').split(/(?=<)|(?<=>)/)) {
      if (part.trim() === '') continue
      if (part.startsWith('</')) depth = Math.max(0, depth - 1)
      lines.push('  '.repeat(depth) + part)
      if (
        part.startsWith('<') &&
        !part.startsWith('</') &&
        !part.endsWith('/>') &&
        !part.startsWith('<?') &&
        !part.startsWith('<!')
      )
        depth++
    }

    return lines.join('\n')
  }

  return body
}

/** Whether a pretty form says anything the stored one does not. */
export function formattable(body: string | null): boolean {
  if (body === null) return false

  return pretty(body) !== body
}

/** A moment as the journal writes it into a decision: ISO 8601 with its offset, UTC as often as not. */
const ISO = /\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})\b/g

/**
 * A decision of the journal with its moments in the reader's time and words — «by today at 14:29»
 * rather than «by 2026-11-03T12:29:21+00:00». Only the screen changes: the event keeps its text.
 */
export function localTimes(text: string, short: (value: string) => string): string {
  return text.replace(ISO, (moment) => short(moment) || moment)
}

/** A line of the list: an event, and the signing keys fetched for it, folded under it. */
export type FoldedLine = EventLine & { keys: EventLine[] }

/**
 * «Signing key» before every request of a gateway that signs with a token says nothing on its own:
 * a list folds each one under the request of the same action that follows it (§20.7). The journal
 * keeps the line; an event opened shows it in its trace, and a key with no request after it — or a
 * list of keys alone — stays a line of its own.
 */
export function foldKeys(events: EventLine[]): FoldedLine[] {
  const byTrace = new Map<string, EventLine[]>()

  for (const event of events) {
    const one = byTrace.get(event.trace) ?? []
    one.push(event)
    byTrace.set(event.trace, one)
  }

  const folded = new Map<number, EventLine[]>()
  const hidden = new Set<number>()

  for (const trace of byTrace.values()) {
    const ordered = [...trace].sort((a, b) => a.id - b.id)

    ordered.forEach((event, index) => {
      if (event.kind !== 'key') return

      const next = ordered.slice(index + 1).find((one) => one.kind !== 'key')

      if (!next) return

      folded.set(next.id, [...(folded.get(next.id) ?? []), event])
      hidden.add(event.id)
    })
  }

  return events
    .filter((event) => !hidden.has(event.id))
    .map((event) => ({ ...event, keys: folded.get(event.id) ?? [] }))
}
