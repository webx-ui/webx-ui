import { computed, ref, shallowRef } from 'vue'

/**
 * One placeholder a text field offers: `[name]` in the text, which whoever prints the text swaps
 * for something else — a phone number kept in settings, the year, a company name.
 *
 * The field never swaps anything itself. It stores the brackets as typed and only helps to type
 * them: a list that opens on `[`, a button with every placeholder there is, and the known ones
 * drawn as chips so they read as one thing rather than as punctuation.
 */
export interface TokenOption {
  /** What goes between the brackets: `/^[a-z][a-z0-9_-]{0,63}$/`. */
  name: string
  /** What it stands for right now, shown beside the name as a hint. */
  value?: string
  /** One line about what it is, for a name that does not say. */
  description?: string
}

/** The props every field that offers placeholders shares. */
export interface TokenFieldProps {
  /** Placeholders to suggest on `[` and to draw as chips. Nothing changes without them. */
  tokens?: TokenOption[]
  /** Heading of the list the help button opens. */
  tokensTitle?: string
  /** Accessible name and tooltip of the help button. */
  tokensLabel?: string
}

export const TOKEN_NAME = /^[a-z][a-z0-9_-]{0,63}$/

/**
 * A placeholder in the text, the way the site reads it: `[name]`, arguments after a space
 * (`[phone format=intl]`), and `[[name]]` as the brackets themselves. Kept in step with the
 * server's own pattern, so a chip is drawn exactly where the site will put something.
 */
const TOKEN =
  /(\[?)\[([a-z][a-z0-9_-]{0,63})((?:\s+[a-z][a-z0-9_-]*=(?:"[^"\]]*"|'[^'\]]*'|[^\s\]"']+))*)\s*\](\]?)/g

export interface TokenSpan {
  from: number
  to: number
  name: string
}

/** Where the known placeholders of a text are. Unknown names and `[[escaped]]` ones are text. */
export function tokenSpans(text: string, known: ReadonlySet<string>): TokenSpan[] {
  if (known.size === 0 || !text.includes('[')) return []

  const spans: TokenSpan[] = []

  for (const match of text.matchAll(TOKEN)) {
    const [whole, open = '', name = '', , close = ''] = match

    if (!known.has(name) || (open !== '' && close !== '')) continue

    const from = (match.index ?? 0) + open.length
    spans.push({ from, to: from + whole.length - open.length - close.length, name })
  }

  return spans
}

export interface TokenSegment {
  text: string
  token: string | null
}

/** The text cut at its placeholders — what a mirror behind a plain field draws. */
export function tokenSegments(text: string, known: ReadonlySet<string>): TokenSegment[] {
  const segments: TokenSegment[] = []
  let at = 0

  for (const span of tokenSpans(text, known)) {
    if (span.from > at) segments.push({ text: text.slice(at, span.from), token: null })
    segments.push({ text: text.slice(span.from, span.to), token: span.name })
    at = span.to
  }

  if (at < text.length || segments.length === 0)
    segments.push({ text: text.slice(at), token: null })

  return segments
}

/**
 * The placeholder being typed right before the caret: `[` and the start of a name. A `[` after
 * another `[` is the start of an escaped one, and nobody wants a list for that.
 */
export function tokenQuery(before: string): { from: number; query: string } | null {
  const match = /(?:^|[^[])\[([a-z][a-z0-9_-]{0,63})?$/.exec(before)

  if (!match) return null

  const query = match[1] ?? ''

  return { from: before.length - query.length - 1, query }
}

/** The text a chosen placeholder is written as. */
export function tokenText(token: TokenOption): string {
  return `[${token.name}]`
}

/** Where the list hangs from: a rectangle in the viewport, and the element it scrolls with. */
export interface TokenAnchor {
  getBoundingClientRect: () => DOMRect
  contextElement?: Element
}

export function tokenAnchor(rect: DOMRect, owner: Element | null): TokenAnchor {
  return { getBoundingClientRect: () => rect, contextElement: owner ?? undefined }
}

/** A rectangle by hand: `new DOMRect` is not everywhere these fields are rendered. */
export function rectOf(x: number, y: number, width: number, height: number): DOMRect {
  return {
    x,
    y,
    width,
    height,
    left: x,
    top: y,
    right: x + width,
    bottom: y + height,
    toJSON: () => ({ x, y, width, height }),
  }
}

/**
 * Under the field, at the caret's column: the list of a one-line field hangs from the field's
 * bottom edge rather than from the caret, so it never covers the line being typed.
 */
export function fieldAnchor(
  owner: HTMLElement | null,
  caret: DOMRect | null,
): TokenAnchor | undefined {
  if (!owner) return undefined

  const box = owner.getBoundingClientRect()
  const x = caret ? caret.left : box.left

  return tokenAnchor(rectOf(x, box.top, 0, box.height), owner)
}

/**
 * The rectangle of one character of an element's text, by its offset — the caret's place in a
 * mirror that draws the same text as the field in the same box. Null where layout is not
 * measured (a test), and the caller falls back to the field.
 */
export function textRect(root: HTMLElement, offset: number): DOMRect | null {
  if (typeof document === 'undefined' || typeof document.createRange !== 'function') return null

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  let left = offset
  let node = walker.nextNode() as Text | null

  while (node) {
    const length = node.data.length

    if (left <= length) {
      const range = document.createRange()
      range.setStart(node, left)
      range.setEnd(node, left)

      const rect =
        typeof range.getBoundingClientRect === 'function' ? range.getBoundingClientRect() : null

      return rect && (rect.height > 0 || rect.width > 0) ? rect : null
    }

    left -= length
    node = walker.nextNode() as Text | null
  }

  return null
}

export interface TokenRange {
  from: number
  to: number
}

/**
 * The list of placeholders and what the keyboard does to it, for any field — where the text is
 * and how a choice is written back is the field's business.
 *
 * Two ways in: typing (`[ph` before the caret, the list filtered by it, the choice replacing it)
 * and browsing (the help button, every placeholder, the choice going where the caret is).
 */
export function useTokenMenu(tokens: () => TokenOption[] | undefined) {
  const open = ref(false)
  const browsing = ref(false)
  const query = ref('')
  const active = ref(0)
  const anchor = shallowRef<TokenAnchor | undefined>(undefined)
  let range: TokenRange = { from: 0, to: 0 }

  /* A name the site would not read as a placeholder is not offered as one. */
  const all = computed(() => (tokens() ?? []).filter((token) => TOKEN_NAME.test(token.name)))
  const known = computed<ReadonlySet<string>>(() => new Set(all.value.map((token) => token.name)))
  const items = computed(() =>
    browsing.value ? all.value : all.value.filter((token) => token.name.startsWith(query.value)),
  )

  function close(): void {
    open.value = false
    browsing.value = false
  }

  /**
   * After a keystroke: opens, narrows or closes the list by what is before the caret. A prefix
   * that matches nothing closes it rather than showing "nothing" — `[` is also just a bracket.
   */
  function suggest(before: string, caret: number, at: () => TokenAnchor | undefined): void {
    const found = tokenQuery(before)

    if (!found || !all.value.some((token) => token.name.startsWith(found.query))) {
      close()
      return
    }

    const wasOpen = open.value && !browsing.value

    browsing.value = false
    query.value = found.query
    range = { from: caret - before.length + found.from, to: caret }
    active.value = wasOpen ? Math.min(active.value, items.value.length - 1) : 0
    anchor.value = at()
    open.value = true
  }

  /** The help button: everything, inserted over `at` — the caret, or what is selected. */
  function browse(at: TokenRange, where: TokenAnchor | undefined): void {
    browsing.value = true
    query.value = ''
    range = at
    active.value = 0
    anchor.value = where
    open.value = true
  }

  function choose(token: TokenOption, insert: (token: TokenOption, at: TokenRange) => void): void {
    const at = range
    close()
    insert(token, at)
  }

  /** True when the key was the list's, and the field should not act on it. */
  function keydown(
    event: KeyboardEvent,
    insert: (token: TokenOption, at: TokenRange) => void,
  ): boolean {
    // Keys of an input method belong to it: Enter there picks a word, not a placeholder.
    if (!open.value || event.isComposing) return false

    const count = items.value.length

    switch (event.key) {
      case 'ArrowDown':
        active.value = count ? (active.value + 1) % count : 0
        break
      case 'ArrowUp':
        active.value = count ? (active.value - 1 + count) % count : 0
        break
      case 'Enter':
      case 'Tab': {
        const token = items.value[active.value]
        if (!token) return false
        choose(token, insert)
        break
      }
      case 'Escape':
        close()
        break
      default:
        return false
    }

    event.preventDefault()
    // Escape inside a dialog closes the list, not the dialog.
    event.stopPropagation()

    return true
  }

  return {
    open,
    browsing,
    items,
    active,
    anchor,
    all,
    known,
    close,
    suggest,
    browse,
    choose,
    keydown,
  }
}

export type TokenMenu = ReturnType<typeof useTokenMenu>
