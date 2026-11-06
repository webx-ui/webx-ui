import {
  escapeForFormat,
  knows,
  refuseInCdata,
  renderTemplate,
  type MustacheVariable,
} from '@webx-ui/core'
import type { FeedFormat, FeedSample } from './types'

/**
 * The preview of §8.3 of the feeds spec: the server counts the values once, and the browser fills
 * the template with them on every key, escaping as the file would. `preview.test.ts` reads what the
 * server wrote for the same feeds out of `tests/fixtures/preview.json`, so a difference between the
 * two is a red test, not a file that looks one way here and another in the marketplace.
 */

export interface FeedParts {
  format: FeedFormat
  head?: string | null
  item?: string | null
  foot?: string | null
  separator?: string | null
  options?: Record<string, unknown> | null
}

export interface FeedVariableParts {
  item: MustacheVariable[]
  frame: MustacheVariable[]
}

export interface RenderedFeed {
  text: string
  /** The names no variable answers to, as `{{name}}` in a template would print nothing. */
  unknown: string[]
}

/** `FeedFileCheck::delimiter()`: one byte, or a comma. */
function delimiterOf(options: FeedParts['options']): string {
  const delimiter = options?.delimiter

  return typeof delimiter === 'string' && new TextEncoder().encode(delimiter).length === 1
    ? delimiter
    : ','
}

function render(
  template: string | null | undefined,
  values: Record<string, unknown>,
  parts: FeedParts,
  variables: MustacheVariable[] | undefined,
): RenderedFeed {
  const source = template ?? ''

  if (source === '') return { text: '', unknown: [] }
  if (parts.format === 'xml') refuseInCdata(source)

  const known =
    variables === undefined
      ? undefined
      : (name: string, sections: string[]) => knows(variables, name, sections)

  return renderTemplate(
    source,
    values,
    escapeForFormat(parts.format, { delimiter: delimiterOf(parts.options) }),
    known,
  )
}

/** One product, as it stands in the file. */
export function renderItem(
  parts: FeedParts,
  values: Record<string, unknown>,
  variables?: FeedVariableParts,
): RenderedFeed {
  return render(parts.item, values, parts, variables?.item)
}

/** The sample as a file: the head, the products with what stands between them, the foot. */
export function renderFile(
  parts: FeedParts,
  sample: Pick<FeedSample, 'products' | 'frame'>,
  variables?: FeedVariableParts,
): RenderedFeed {
  const head = render(parts.head, sample.frame, parts, variables?.frame)
  const foot = render(parts.foot, sample.frame, parts, variables?.frame)
  const items = sample.products.map((product) => renderItem(parts, product.values, variables))

  return {
    text: head.text + items.map((item) => item.text).join(parts.separator ?? '') + foot.text,
    unknown: [...new Set([head, ...items, foot].flatMap((rendered) => rendered.unknown))],
  }
}

export interface ParseHint {
  message: string
  line: number | null
}

/**
 * A word of warning while typing, by the browser's own parsers. Only a hint: what decides is the
 * server's check (§8.3), and a csv has no parser here to ask.
 */
export function parseHint(format: FeedFormat, text: string): ParseHint | null {
  if (text.trim() === '') return null

  if (format === 'json') {
    try {
      JSON.parse(text)

      return null
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error)
      const at = /position (\d+)/.exec(message)

      return {
        message,
        line: at ? text.slice(0, Number(at[1])).split('\n').length : null,
      }
    }
  }

  if (format === 'xml') {
    const found = new DOMParser()
      .parseFromString(text, 'application/xml')
      .querySelector('parsererror')

    if (found === null) return null

    const message = (found.textContent ?? '').trim()
    const at = /line (\d+)/.exec(message)

    return { message, line: at ? Number(at[1]) : null }
  }

  return null
}
