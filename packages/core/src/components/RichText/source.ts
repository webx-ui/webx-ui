/**
 * The HTML behind the editor, as a person reads and edits it.
 *
 * Tiptap writes the document as one line, which is unreadable past a paragraph. Blocks go on
 * lines of their own here, and whatever holds other blocks indents them. Whitespace between
 * blocks means nothing to the parser, so the document that comes back is the one that went in.
 */

/** Tags whose children are blocks: each child gets a line, one step further in. */
const CONTAINERS = new Set([
  'ul',
  'ol',
  'li',
  'blockquote',
  'table',
  'colgroup',
  'thead',
  'tbody',
  'tfoot',
  'tr',
  'th',
  'td',
  'div',
  'figure',
])

/** Tags that start a line and keep their content on it. */
const LINES = new Set([
  'p',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'pre',
  'hr',
  'img',
  'iframe',
  'col',
  'figcaption',
])

const VOID = new Set(['hr', 'img', 'br', 'col', 'wbr', 'source', 'input'])

const TAG = /<(\/?)([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*?(\/?)>/g

export function formatHtml(html: string, indent = '  '): string {
  let out = ''
  let depth = 0
  /** Inside a line block nothing breaks: a newline there would be a space in the text. */
  let inLine = 0
  let inPre = 0
  let last = 0

  const breakLine = (): void => {
    out += `\n${indent.repeat(depth)}`
  }

  for (const match of html.matchAll(TAG)) {
    const [token, closing, rawName, selfClosing] = match
    const name = rawName.toLowerCase()
    out += html.slice(last, match.index)
    last = match.index + token.length

    if (inPre > 0 && name !== 'pre') {
      out += token
      continue
    }

    const free = inLine === 0
    const isVoid = VOID.has(name) || selfClosing === '/'

    if (CONTAINERS.has(name) && free) {
      if (closing) {
        depth = Math.max(0, depth - 1)
        breakLine()
        out += token
      } else {
        breakLine()
        out += token
        if (!isVoid) depth += 1
      }
      continue
    }

    if (LINES.has(name)) {
      if (!closing && free) breakLine()
      out += token
      if (name === 'pre') inPre += closing ? -1 : 1
      if (!isVoid) inLine += closing ? -1 : 1
      inLine = Math.max(0, inLine)
      continue
    }

    out += token
  }

  out += html.slice(last)

  return out.replace(/^\n/, '')
}

/** Tags the editor writes under another name: not a loss, only a spelling. */
const SPELLINGS: Record<string, string> = { b: 'strong', i: 'em', del: 's', strike: 's' }

function census(html: string): Map<string, number> {
  const counts = new Map<string, number>()
  const add = (key: string): void => {
    counts.set(key, (counts.get(key) ?? 0) + 1)
  }
  const template = document.createElement('template')
  template.innerHTML = html

  for (const element of template.content.querySelectorAll('*')) {
    const tag = element.tagName.toLowerCase()
    const name = SPELLINGS[tag] ?? tag
    add(`<${name}>`)
    for (const attribute of element.getAttributeNames()) add(`${attribute} on <${name}>`)
  }

  return counts
}

/**
 * What a document loses on its way into the editor: the tags and attributes the editor's schema
 * does not know, each named once. ProseMirror drops them without a word, which is fine for a
 * paste and not for markup a person has just typed by hand.
 *
 * Counted rather than merely listed, so that one `<span>` the editor keeps does not vouch for
 * the nine it drops. What the editor adds — a paragraph around bare text, its own `rel` on a
 * link — is not a loss and is not reported.
 */
export function lostMarkup(before: string, after: string): string[] {
  const had = census(before)
  const kept = census(after)
  const lost: string[] = []

  for (const [key, count] of had) {
    if ((kept.get(key) ?? 0) < count) lost.push(key)
  }

  // A dropped tag takes its attributes with it; naming both says the same thing twice.
  const droppedTags = new Set(lost.filter((key) => key.startsWith('<')))

  return lost.filter((key) => {
    const on = / on (<[^>]+>)$/.exec(key)
    return !on || !droppedTags.has(on[1])
  })
}
