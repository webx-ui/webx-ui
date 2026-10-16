/**
 * A Telegram message as the chat draws it, for the preview: only the tags Telegram takes
 * (`TelegramMarkup::TAGS`), links only to the web and to Telegram, and everything else as text.
 *
 * The template is the administrator's and the values are the event's examples, but the preview
 * still never hands HTML to the page as it is: what is not on the list is not drawn.
 */
const TAGS = new Set([
  'b',
  'strong',
  'i',
  'em',
  'u',
  'ins',
  's',
  'strike',
  'del',
  'a',
  'code',
  'pre',
  'blockquote',
  'tg-spoiler',
  'span',
])

function copy(from: Node, into: Node, document: Document): void {
  for (const child of Array.from(from.childNodes)) {
    if (child.nodeType === Node.TEXT_NODE) {
      into.appendChild(document.createTextNode(child.textContent ?? ''))
      continue
    }

    if (!(child instanceof Element)) continue

    const tag = child.tagName.toLowerCase()

    if (!TAGS.has(tag) || (tag === 'span' && !child.classList.contains('tg-spoiler'))) {
      copy(child, into, document)
      continue
    }

    const made = document.createElement(tag === 'tg-spoiler' ? 'span' : tag)

    if (tag === 'tg-spoiler' || tag === 'span') made.className = 'tg-spoiler'

    if (tag === 'a') {
      const href = child.getAttribute('href') ?? ''

      if (/^(https?:|tg:)/i.test(href)) {
        made.setAttribute('href', href)
        made.setAttribute('target', '_blank')
        made.setAttribute('rel', 'noopener noreferrer')
      }
    }

    copy(child, made, document)
    into.appendChild(made)
  }
}

export function telegramHtml(body: string): string {
  const parsed = new DOMParser().parseFromString(`<body>${body}</body>`, 'text/html')
  const out = document.implementation.createHTMLDocument('')
  const root = out.createElement('div')

  copy(parsed.body, root, out)

  return root.innerHTML
}
