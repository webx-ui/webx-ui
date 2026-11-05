import {
  escapeHtml,
  htmlPlain,
  knows,
  renderTemplate,
  type Escape,
  type TemplateValues,
} from '@webx-ui/core'
import type { NotificationEvent } from './types'

export { escapeHtml, renderTemplate, TemplateRefusal, type TemplateValues } from '@webx-ui/core'

/**
 * The live preview of a channel's message (§7): the template and the values of the event's examples
 * through the provider's escaping. The Mustache of it is `@webx-ui/core`'s; what is here is what a
 * channel adds. `render.test.ts` and the server's `PreviewParityTest` read the same cases out of
 * `tests/fixtures/render.json`, so a difference between the two shows as a red test rather than as
 * a message that looks one way here and another in the chat.
 */

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
    event === null
      ? undefined
      : (name: string, sections: string[]) => knows(event.variables, name, sections)
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
