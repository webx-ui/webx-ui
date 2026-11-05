import Mustache from 'mustache'
import type { MustacheVariable } from './types'

/**
 * Mustache in the browser: a template the way the server reads it, for a preview that shows what
 * goes out. `mustache.js` — the same syntax `webx-ui/templates` renders with `mustache/mustache` —
 * only parses: its rendering differs from PHP's where it matters — `'0'` is a value to JavaScript
 * and empty to PHP, an escaping function is global to it — so the tree it parses is walked here by
 * the rules of the server's `Template`.
 *
 * `template.test.ts` and the server's `ParityTest` read the same cases out of
 * `php/packages/templates/tests/fixtures/render.json`, so a difference between the two shows as a
 * red test rather than as a preview that lies.
 */

/** Values as the consumer gives them: flat, `field.phone` with a dot. */
export type TemplateValues = Record<string, unknown>

/**
 * How a value goes into the text. `cdata` says it is a `{ $cdata }`: only the `xml` format has
 * anything to do with that, a function that leaves it out gets the text like any value.
 */
export type Escape = (value: string, cdata?: boolean) => string

/** The formats of `Escapes` on the server. */
export type TemplateFormat = 'xml' | 'json' | 'csv' | 'text'

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

/** What every element of a list has besides its own fields. */
export const POSITIONS = ['first', 'last']

/**
 * The English of the refusals, for `i18n.defaults('webx-templates', …)`: the server ships the same
 * lines in ten languages under `webx-templates::errors`, and `template.test.ts` keeps them equal.
 */
export const templateMessages = {
  errors: {
    'template-unclosed': 'The section {{#:section}} is not closed: end it with {{/:section}}.',
    'template-unexpected': '{{/:section}} closes a section that was never opened.',
    'template-tag': 'The tag {{:tag is not closed: end it with }}.',
    'template-name':
      '{{:name}} is not a variable name: lowercase letters, digits and _, one dot at most.',
    'template-partial': 'Partials ({{>name}}) are not available in a message template.',
    'template-delimiters':
      'Changing the delimiters ({{=<% %>=}}) is not available in a message template.',
    'template-pragma':
      'Pragmas and blocks ({{%…}}, {{$…}}) are not available in a message template.',
    'template-cdata':
      'A variable cannot stand inside a literal CDATA block: {{:name}} would be escaped twice. Remove the CDATA around it and use the _cdata variable, such as {{description_cdata}}.',
  },
}

/** Telegram's: `htmlspecialchars(…, ENT_QUOTES | ENT_HTML401)`. */
export function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

/* eslint-disable-next-line no-control-regex -- what an XML 1.0 parser refuses, by its code points */
const NOT_XML = /[\x00-\x08\x0B\x0C\x0E-\x1F\uFFFE\uFFFF]/g

function escapeXml(value: string, cdata = false): string {
  const clean = value.replace(NOT_XML, '')

  if (cdata) return `<![CDATA[${clean.replaceAll(']]>', ']]]]><![CDATA[>')}]]>`

  return clean
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

/** The inside of a JSON string: the quotes are the template's. */
function escapeJson(value: string): string {
  return JSON.stringify(value).slice(1, -1)
}

function escapeCsv(value: string, delimiter: string): string {
  if (!/["\r\n]/.test(value) && !value.includes(delimiter)) return value

  return `"${value.replace(/"/g, '""')}"`
}

/** `Escapes::for()`: how a value goes into a file of a format. */
export function escapeForFormat(
  format: TemplateFormat,
  options: { delimiter?: string } = {},
): Escape {
  if (format === 'xml') return escapeXml
  if (format === 'json') return escapeJson
  if (format === 'csv') return (value) => escapeCsv(value, options.delimiter ?? ',')

  return (value) => value
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

  return positioned(context) as Record<string, unknown>
}

/** `Template::positioned()`: `first` and `last` on the elements of every list; its own field wins. */
function positioned(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map((item, at) => {
      const inner = positioned(item)

      return isMap(inner)
        ? { first: at === 0 ? '1' : '', last: at === value.length - 1 ? '1' : '', ...inner }
        : inner
    })
  }

  if (!isMap(value)) return value

  return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, positioned(item)]))
}

function isMap(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** `Cdata` as the server sends it. */
function isCdata(value: unknown): value is { $cdata: string } {
  return isMap(value) && typeof value.$cdata === 'string'
}

/** PHP's `empty()`, which is what decides a section there. An empty `Cdata` is null. */
function empty(value: unknown): boolean {
  if (value === null || value === undefined || value === false) return true
  if (isCdata(value)) return value.$cdata === ''
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
      if (isCdata(found)) out += empty(found) ? '' : escape(found.$cdata, true)
      else out += isMap(found) || Array.isArray(found) ? '' : escape(scalar(found))

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

/**
 * `Variables::knows()`: a field of an enclosing list — `first` and `last` are every list's — or one
 * of the variables.
 */
export function knows(
  variables: Pick<MustacheVariable, 'name' | 'fields'>[],
  name: string,
  sections: string[],
): boolean {
  for (const section of [...sections].reverse()) {
    const fields = variables.find((variable) => variable.name === section)?.fields ?? []

    if (
      fields.length > 0 &&
      (POSITIONS.includes(name) || fields.some((one) => one.name === name))
    ) {
      return true
    }
  }

  return variables.some(
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

/**
 * `Template::refuseInCdata()`: a variable inside a literal `<![CDATA[ … ]]>` of the template, which
 * would be escaped for XML in a block that prints entities as they are.
 *
 * @throws TemplateRefusal
 */
export function refuseInCdata(template: string): void {
  const tokens = parse(template)

  check(tokens)

  let inside = false

  const visit = (nodes: Token[]): void => {
    for (const token of nodes) {
      const [type, value] = token

      if (type === 'text') {
        const opened = value.lastIndexOf('<![CDATA[')
        const closed = value.lastIndexOf(']]>')

        if (opened !== -1 || closed !== -1) inside = opened !== -1 && opened > closed
      } else if (inside && (type === 'name' || type === '&')) {
        throw new TemplateRefusal('template-cdata', { name: value.trim() })
      }

      if (token[4]) visit(token[4])
    }
  }

  visit(tokens)
}

/** What a click on a variable puts into the template. */
export function snippetOf(
  variable: MustacheVariable,
  kind: 'value' | 'has' | 'empty' = 'value',
): string {
  const name = variable.name.endsWith('.*') ? `${variable.name.slice(0, -2)}.name` : variable.name

  if ((variable.fields?.length ?? 0) > 0) {
    const fields = (variable.fields ?? []).map((field) => `{{${field.name}}}`).join(' ')

    return `{{#${name}}}\n${fields}\n{{/${name}}}`
  }

  if (kind === 'has') return `{{#${name}}}{{${name}}}{{/${name}}}`
  if (kind === 'empty') return `{{^${name}}}{{/${name}}}`

  return `{{${name}}}`
}
