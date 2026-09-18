/**
 * The little of Markdown a page of help needs, turned into HTML.
 *
 * Not a Markdown library. This exists so that one piece of prose can serve two readers — the
 * panel shows it in a dialog, an agent reads the same text over MCP as `text/markdown` — and
 * so it has to understand exactly what such a page is written with: headings, paragraphs,
 * lists, fenced code, and inline code, links and emphasis inside a line.
 *
 * Everything is escaped before any tag is emitted, so the output contains only the tags built
 * here. That is what makes it safe to hand to `v-html`: the text comes from the server's
 * dictionary, which is a site's own `lang` files, and a page of help is not a place where
 * anybody should have to think about whether it is.
 */

export function renderMarkdown(source: string): string {
  const lines = source.replace(/\r\n?/g, '\n').split('\n')
  const out: string[] = []

  let index = 0

  while (index < lines.length) {
    const line = lines[index] ?? ''

    if (line.trim() === '') {
      index += 1
      continue
    }

    const fence = /^```([\w-]*)\s*$/.exec(line)

    if (fence) {
      const code: string[] = []
      index += 1

      while (index < lines.length && !/^```\s*$/.test(lines[index] ?? '')) {
        code.push(lines[index] ?? '')
        index += 1
      }

      // The closing fence, or the end of the text when the writer forgot one.
      index += 1
      out.push(`<pre><code>${escape(code.join('\n'))}</code></pre>`)
      continue
    }

    const heading = /^(#{1,4})\s+(.*)$/.exec(line)

    if (heading) {
      // Levels start at 2: the dialog's own title is the first heading on screen.
      const level = Math.min(heading[1]!.length + 1, 5)
      out.push(`<h${level}>${inline(heading[2] ?? '')}</h${level}>`)
      index += 1
      continue
    }

    if (isItem(line)) {
      const ordered = /^\s*\d+[.)]\s+/.test(line)
      const items: string[] = []

      while (index < lines.length && isItem(lines[index] ?? '')) {
        items.push((lines[index] ?? '').replace(/^\s*(?:[-*+]|\d+[.)])\s+/, ''))
        index += 1

        /*
         * An item that runs onto the next line is still that item. Without this the rest of a
         * wrapped sentence became a paragraph of its own, outside the list and after it —
         * which is how a page of prose written at 96 columns falls apart on the way in.
         */
        while (index < lines.length) {
          const next = lines[index] ?? ''

          if (next.trim() === '' || isItem(next) || /^(#{1,4})\s/.test(next) || /^```/.test(next)) {
            break
          }

          items[items.length - 1] += ` ${next.trim()}`
          index += 1
        }
      }

      const tag = ordered ? 'ol' : 'ul'
      out.push(`<${tag}>${items.map((item) => `<li>${inline(item)}</li>`).join('')}</${tag}>`)
      continue
    }

    // A paragraph runs to the next blank line; a single newline inside one is not a break.
    const paragraph: string[] = []

    while (index < lines.length) {
      const next = lines[index] ?? ''

      if (next.trim() === '' || isItem(next) || /^(#{1,4})\s/.test(next) || /^```/.test(next)) break

      paragraph.push(next.trim())
      index += 1
    }

    out.push(`<p>${inline(paragraph.join(' '))}</p>`)
  }

  return out.join('')
}

/*
 * What a code span is held out of the line by while the rest is marked up. A NUL, because
 * it is the one character a page of help cannot contain — written as an escape rather than
 * put in the file, where it would be a control character nobody reviewing this can see.
 */
const HOLE = '\u0000'

function isItem(line: string): boolean {
  return /^\s*(?:[-*+]|\d+[.)])\s+/.test(line)
}

/**
 * What can appear inside a line. Code first, and what it holds is put back only after the rest
 * has been marked up — otherwise an underscore or a bracket inside a code span is read as
 * emphasis or as a link, which is exactly what a code span is for avoiding.
 */
function inline(text: string): string {
  const spans: string[] = []

  const withHoles = text.replace(/`([^`]+)`/g, (_, code: string) => {
    spans.push(`<code>${escape(code)}</code>`)

    return `${HOLE}${spans.length - 1}${HOLE}`
  })

  const marked = escape(withHoles)
    .replace(
      /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
      '<a href="$2" target="_blank" rel="noopener">$1</a>',
    )
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')

  return marked.replace(
    new RegExp(`${HOLE}(\\d+)${HOLE}`, 'g'),
    (_, at: string) => spans[Number(at)] ?? '',
  )
}

function escape(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}
