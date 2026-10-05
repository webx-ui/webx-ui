/**
 * The attribute a library picture carries its key in — the same one the server reads
 * (`Html::KEY` in the Composer half). The address beside it is a cache; this is the record.
 */
const KEY = 'data-wx-path'

/** A rich text value as a screen holds it: one document, or one per language. */
type Value = unknown

function documents(value: Value): string[] {
  if (typeof value === 'string') return [value]

  if (value !== null && typeof value === 'object') {
    return Object.values(value).filter((one): one is string => typeof one === 'string')
  }

  return []
}

/**
 * Every library key in the value, once each. Read off the markup without parsing it: the
 * attribute is written by the editor in one shape, and most documents have no pictures at all.
 */
export function richTextKeys(value: Value): string[] {
  const keys = new Set<string>()
  const pattern = new RegExp(`${KEY}="([^"]+)"`, 'g')

  for (const html of documents(value)) {
    for (const match of html.matchAll(pattern)) keys.add(decode(match[1]))
  }

  return [...keys]
}

/** `&amp;` and friends, the way the attribute was escaped when the editor wrote it. */
function decode(text: string): string {
  const area = document.createElement('textarea')
  area.innerHTML = text

  return area.value
}

function rewrite(html: string, addresses: Record<string, string | null>): string {
  const template = document.createElement('template')
  template.innerHTML = html

  let changed = false

  for (const element of template.content.querySelectorAll<HTMLElement>(`[${KEY}]`)) {
    const address = addresses[element.getAttribute(KEY) ?? '']
    // A link to a file points with `href`, a picture with `src` — as on the server.
    const attribute = element.tagName === 'A' ? 'href' : 'src'

    // A key the library no longer has keeps the address it was saved with: a broken picture
    // that still says where it came from is easier to replace than an empty one.
    if (!address || element.getAttribute(attribute) === address) continue

    element.setAttribute(attribute, address)
    changed = true
  }

  // The same string back when nothing moved, so the editor is not handed a document that only
  // differs in how the parser chose to serialise it.
  return changed ? template.innerHTML : html
}

/**
 * The value with every library picture pointed at where it lives now. A language map stays a
 * map; anything that is not a document comes back as it was.
 */
export function rewriteRichText(value: Value, addresses: Record<string, string | null>): Value {
  if (typeof value === 'string') return rewrite(value, addresses)

  if (value !== null && typeof value === 'object') {
    const entries = Object.entries(value).map(([locale, html]) => [
      locale,
      typeof html === 'string' ? rewrite(html, addresses) : html,
    ])
    const changed = entries.some(
      ([locale, html]) => html !== (value as Record<string, unknown>)[locale],
    )

    return changed ? Object.fromEntries(entries) : value
  }

  return value
}
