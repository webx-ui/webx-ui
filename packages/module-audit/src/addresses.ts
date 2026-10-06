import type { BadgeType } from '@webx-ui/core'

/** What tells a redirect's target from its source when the rest of the address is the same. */
export type AddressDifference = 'slash' | 'www' | 'https'

/**
 * The small differences between where a link points and where it lands: a trailing slash, `www.`,
 * `http` → `https`. Empty when the addresses differ in anything else (the page really moved) or
 * either is not an address — then there is nothing small to name.
 */
export function differences(from: string, to: string): AddressDifference[] {
  let source: URL
  let target: URL

  try {
    source = new URL(from)
    target = new URL(to, from)
  } catch {
    return []
  }

  const found: AddressDifference[] = []

  if (source.protocol === 'http:' && target.protocol === 'https:') found.push('https')
  else if (source.protocol !== target.protocol) return []

  const bare = (host: string) => host.replace(/^www\./, '')

  if (source.host !== target.host) {
    if (bare(source.host) !== bare(target.host)) return []
    found.push('www')
  }

  const trimmed = (path: string) => path.replace(/\/+$/, '')

  if (source.pathname !== target.pathname) {
    if (trimmed(source.pathname) !== trimmed(target.pathname)) return []
    found.push('slash')
  }

  if (source.search !== target.search || source.hash !== target.hash) return []

  return found
}

/** How many characters stay at the end of a shortened address: where a slug or a slash differs. */
const TAIL = 14

/**
 * An address cut in two for an ellipsis in the middle: the head gives way, the tail stays —
 * the end of an address is what tells two of them apart.
 */
export function splitMiddle(text: string): [string, string] {
  if (text.length <= TAIL * 2) return [text, '']

  return [text.slice(0, -TAIL), text.slice(-TAIL)]
}

/** The colour of an answer's code. */
export function statusType(value: unknown): BadgeType {
  const code = Number(value)

  if (code >= 500 || code === 0) return 'danger'
  if (code >= 400) return 'warning'
  // A permanent redirect only asks for the link to be updated; a temporary one may move again.
  if (code === 301 || code === 308) return 'info'
  if (code >= 300) return 'warning'

  return 'success'
}

/** The codes with a line of their own in the dictionary; the rest are explained by their class. */
const EXPLAINED = [
  200, 201, 204, 206, 301, 302, 303, 304, 307, 308, 400, 401, 403, 404, 405, 406, 408, 410, 415,
  429, 451, 500, 502, 503, 504,
]

/** The dictionary key of what an answer means: `code-404`, `code-5xx`, or `code-none`. */
export function statusKey(code: number | null | undefined): string {
  if (!code) return 'code-none'
  if (EXPLAINED.includes(code)) return `code-${code}`
  if (code >= 100 && code < 600) return `code-${Math.floor(code / 100)}xx`

  return 'code-none'
}
