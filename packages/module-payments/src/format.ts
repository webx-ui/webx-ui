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
