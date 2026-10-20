import Mustache from 'mustache'
import type { NotificationEvent } from './types'

/**
 * The live preview of a channel's message (§7): the template Mustache reads — `mustache.js`, the
 * same syntax the server renders with `mustache/mustache` — and the values of the event's examples
 * through the provider's escaping. `render.test.ts` and the server's `PreviewParityTest` read the
 * same cases out of `tests/fixtures/render.json`, so a difference between the two shows as a red
 * test rather than as a message that looks one way here and another in the chat.
 *
 * `mustache.js` only parses: its rendering differs from PHP's where it matters to a notification —
 * `'0'` is a value to JavaScript and empty to PHP, an escaping function is global to it — so the
 * tree it parses is walked here by the rules of `Mustache\Template` on the server.
 */

/** Values as the consumer gives them: flat, `field.phone` with a dot. */
export type TemplateValues = Record<string, unknown>

/** How a value goes into a message: the provider's escaping (`Provider::escape`). */
export type Escape = (value: string) => string

/** A template the server would refuse, with the key of `errors.*` that says why. */
export class TemplateRefusal extends Error {
  constructor(
    public readonly key: string,
    public readonly replace: Record<string, string> = {},
  ) {
    super(key)
    this.name = 'TemplateRefusal'
  }
}

type Token = [string, string, number, number, Token[]?, number?]

const NAME = /^[a-z0-9_]+(\.[a-z0-9_]+)?$/

/** Telegram's: `htmlspecialchars(…, ENT_QUOTES | ENT_HTML401)`. */
export function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

/** A subject is one line: a value with line breaks would break the header. */
export function oneLine(value: string): string {
  return value.replace(/[\r\n]+/g, ' ')
}

/** Each provider's escaping, by key; a provider nobody knows here prints values as they are. */
export function escapeFor(provider: string, template = ''): Escape {
  if (isHtmlLetter(provider, template)) return escapeHtmlValue

  return provider === 'telegram' ? escapeHtml : (value) => value
}

/** `EmailMarkup::isHtml()`: a letter's template that starts with a tag is HTML. */
export function isHtmlLetter(provider: string, template: string): boolean {
  return provider === 'email' && /^\s*<[a-zA-Z]/.test(template)
}

/** `Html::escape()`: a value in an HTML message, the visitor's line breaks kept as `<br>`. */
export function escapeHtmlValue(value: string): string {
  return escapeHtml(value).replace(/\r\n|\r|\n/g, '<br>')
}

function decodeEntities(text: string): string {
  const area = document.createElement('textarea')

  area.innerHTML = text

  return area.value
}

const PARAGRAPHS = 'p|h[1-6]|table|ul|ol|blockquote|pre'
const LINES = 'tr|div'

/** `Html::plain()`: the text of an HTML letter, for its plain part — the same steps, in order. */
export function htmlPlain(html: string): string {
  let text = html.replace(/[ \t\r\n\f]+/g, ' ')

  text = text.replace(
    /<a\b[^>]*?\bhref\s*=\s*"([^"]*)"[^>]*>([\s\S]*?)<\/a\s*>/gi,
    (_, rawHref: string, inner: string) => {
      const href = decodeEntities(rawHref)
      const words = decodeEntities(inner.replace(/<[^>]*>/g, '')).trim()

      if (words === '' || words === href) return href

      return /^(https?:|mailto:)/i.test(href) ? `${inner} (${rawHref})` : inner
    },
  )

  text = text.replace(/<br\s*\/?>/gi, '\n')
  text = text.replace(/<\/t[dh]\s*>\s*(?=<t[dh][\s>])/gi, ': ')
  text = text.replace(/<li\b[^>]*>/gi, '\n- ')
  text = text.replace(new RegExp(`<hr\\b[^>]*>|</?(?:${PARAGRAPHS})\\b[^>]*>`, 'gi'), '\n\n')
  text = text.replace(new RegExp(`<div\\b[^>]*>|</(?:${LINES})\\s*>`, 'gi'), '\n')
  text = decodeEntities(text.replace(/<[^>]*>/g, ''))

  const lines = text.split('\n').map((line) => line.replace(/ {2,}/g, ' ').trim())

  return lines
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

function parse(template: string): Token[] {
  try {
    return Mustache.parse(template, ['{{', '}}']) as unknown as Token[]
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error)
    const section = /section "([^"]*)"/.exec(message)?.[1] ?? ''

    if (message.startsWith('Unopened section')) {
      throw new TemplateRefusal('template-unexpected', { section })
    }

    if (message.startsWith('Unclosed section')) {
      throw new TemplateRefusal('template-unclosed', { section })
    }

    const at = Number(/at (\d+)/.exec(message)?.[1] ?? template.lastIndexOf('{{'))
    const tag = template
      .slice(at)
      .replace(/^\{\{\{?[#^/!&>=]?/, '')
      .trim()

    throw new TemplateRefusal('template-tag', { tag })
  }
}

/** The checks of `Template::tokens()`: what Mustache has and a message template may not use. */
function check(tokens: Token[]): void {
  for (const token of tokens) {
    const [type, value] = token

    if (type === '>') throw new TemplateRefusal('template-partial')
    if (type === '=') throw new TemplateRefusal('template-delimiters')
    if (type === 'text' || type === '!') continue

    const name = value.trim()

    if (name.startsWith('<')) throw new TemplateRefusal('template-partial')
    if (name.startsWith('%') || name.startsWith('$')) throw new TemplateRefusal('template-pragma')
    if (!NAME.test(name)) throw new TemplateRefusal('template-name', { name })

    if (token[4]) check(token[4])
  }
}

/** Flat values nested the way Mustache reads a dot: `field.phone` is `field` → `phone`. */
function nest(values: TemplateValues): Record<string, unknown> {
  const context: Record<string, unknown> = {}

  for (const [name, value] of Object.entries(values)) {
    if (!name.includes('.')) {
      context[name] = value
      continue
    }

    const [head, ...tail] = name.split('.')
    const key = head as string
    const current = context[key]

    if (current === undefined || isMap(current)) {
      context[key] = { ...(isMap(current) ? current : {}), [tail.join('.')]: value }
    }
  }

  return context
}

function isMap(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** PHP's `empty()`, which is what decides a section there. */
function empty(value: unknown): boolean {
  if (value === null || value === undefined || value === false) return true
  if (value === '' || value === '0' || value === 0) return true
  if (Array.isArray(value)) return value.length === 0
  if (isMap(value)) return Object.keys(value).length === 0

  return false
}

/** `Context::findDot()`: the first part up the stack, the rest inside it. */
function find(stack: unknown[], name: string): unknown {
  const [first, ...rest] = name.split('.')
  let value: unknown = undefined

  for (let at = stack.length - 1; at >= 0; at--) {
    const frame = stack[at]

    if (isMap(frame) && Object.prototype.hasOwnProperty.call(frame, first as string)) {
      value = frame[first as string]
      break
    }
  }

  for (const chunk of rest) {
    if (value === '' || value === undefined) return value
    value = isMap(value) && Object.prototype.hasOwnProperty.call(value, chunk) ? value[chunk] : ''
  }

  return value
}

function scalar(value: unknown): string {
  if (typeof value === 'string') return value
  if (typeof value === 'number') return String(value)
  if (value === true) return '1'

  return ''
}

function walk(tokens: Token[], stack: unknown[], escape: Escape): string {
  let out = ''

  for (const token of tokens) {
    const [type, value] = token

    if (type === 'text') {
      out += value
      continue
    }

    if (type === '!') continue

    const found = find(stack, value.trim())

    if (type === 'name' || type === '&') {
      out += isMap(found) || Array.isArray(found) ? '' : escape(scalar(found))
      continue
    }

    const inside = token[4] ?? []

    if (type === '#' && !empty(found)) {
      for (const item of Array.isArray(found) ? found : [found]) {
        out += walk(inside, [...stack, item], escape)
      }
    }

    if (type === '^' && empty(found)) out += walk(inside, stack, escape)
  }

  return out
}

interface Usage {
  name: string
  sections: string[]
}

function usages(tokens: Token[], sections: string[] = [], into: Usage[] = []): Usage[] {
  for (const token of tokens) {
    const [type, value] = token

    if (type === 'text' || type === '!') continue

    const name = value.trim()

    into.push({ name, sections })

    if (type === '#') usages(token[4] ?? [], [...sections, name], into)
    if (type === '^') usages(token[4] ?? [], sections, into)
  }

  return into
}

/** `Template::find()`: a name in the nearest scope that has its first part; null for none. */
function lookup(scopes: unknown[], name: string): unknown {
  const [first, ...rest] = name.split('.') as [string, ...string[]]

  for (const scope of [...scopes].reverse()) {
    if (!isMap(scope) || !Object.prototype.hasOwnProperty.call(scope, first)) continue

    let value: unknown = scope[first]

    for (const chunk of rest) {
      if (!isMap(value) || !Object.prototype.hasOwnProperty.call(value, chunk)) return null
      value = value[chunk]
    }

    return value ?? null
  }

  return null
}

/** `Template::present()`: whether a name has a value where it is used. */
function present(context: Record<string, unknown>, name: string, sections: string[]): boolean {
  const scopes: unknown[] = [context]

  for (const section of sections) {
    const value = lookup(scopes, section)

    // The fields of any element: a list of rows with gaps is not a mistake.
    if (Array.isArray(value)) scopes.push(Object.assign({}, ...value.filter(isMap)))
    else if (isMap(value)) scopes.push(value)
  }

  return lookup(scopes, name) !== null
}

/** `EventDefinition::knows()`: a field of an enclosing list, or a variable of the event. */
export function knows(event: NotificationEvent, name: string, sections: string[]): boolean {
  for (const section of [...sections].reverse()) {
    const list = event.variables.find((variable) => variable.name === section)

    if (list && list.fields.length > 0 && list.fields.some((field) => field.name === name)) {
      return true
    }
  }

  return event.variables.some(
    (variable) =>
      variable.name === name ||
      (variable.name.endsWith('.*') && name.startsWith(variable.name.slice(0, -1))),
  )
}

/**
 * The template with the values in, each through `escape` — `Template::render()`. A name with no
 * value is empty, and reported unless `known` says the event declares it.
 *
 * @throws TemplateRefusal for a template the server would refuse
 */
export function renderTemplate(
  template: string,
  values: TemplateValues,
  escape: Escape,
  known?: (name: string, sections: string[]) => boolean,
): { text: string; unknown: string[] } {
  const tokens = parse(template)

  check(tokens)

  const context = nest(values)
  const unknown = new Set<string>()

  for (const usage of usages(tokens)) {
    if (!present(context, usage.name, usage.sections) && !known?.(usage.name, usage.sections)) {
      unknown.add(usage.name)
    }
  }

  return { text: walk(tokens, [context], escape), unknown: [...unknown] }
}

/** The examples of an event as values: what a test and the preview fill a template with. */
export function examplesOf(event: NotificationEvent | null | undefined): TemplateValues {
  const values: TemplateValues = {}

  for (const variable of event?.variables ?? []) {
    if (!variable.name.endsWith('.*')) values[variable.name] = variable.example
  }

  return values
}

/** Telegram's plain text: the tags dropped, the entities read. */
export function plainOf(html: string): string {
  const document = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html')

  return document.body.textContent ?? ''
}

export interface RenderedMessage {
  subject: string | null
  body: string
  plain: string
  /** Longer than the provider takes: sent as plain text, cut. */
  truncated: boolean
  unknown: string[]
  /** A letter whose template is HTML: the body is the letter's HTML. */
  html: boolean
}

export interface MessageInput {
  provider: string
  template: string
  subject?: string | null
  /** Whether the provider's messages have a subject. */
  hasSubject?: boolean
  /** The longest message the provider takes, in characters. */
  limit?: number | null
  values: TemplateValues
  event?: NotificationEvent | null
}

/** `Renderer::render()`: the message a channel would send on these values. */
export function renderMessage(input: MessageInput): RenderedMessage {
  const html = isHtmlLetter(input.provider, input.template)
  const escape = escapeFor(input.provider, input.template)
  const event = input.event ?? null
  const known =
    event === null ? undefined : (name: string, sections: string[]) => knows(event, name, sections)
  const body = renderTemplate(input.template, input.values, escape, known)
  let subject: string | null = null
  let unknown = body.unknown

  if (input.hasSubject) {
    const rendered = renderTemplate(input.subject ?? '', input.values, oneLine, known)

    subject = oneLine(rendered.text).trim()
    unknown = [...new Set([...unknown, ...rendered.unknown])]
  }

  const plain = html
    ? htmlPlain(body.text)
    : input.provider === 'telegram'
      ? plainOf(body.text)
      : body.text
  const limit = input.limit ?? null

  if (limit !== null && [...plain].length > limit) {
    const cut = `${[...plain].slice(0, limit - 1).join('')}…`

    return { subject, body: escape(cut), plain: cut, truncated: true, unknown, html }
  }

  return { subject, body: body.text, plain, truncated: false, unknown, html }
}
