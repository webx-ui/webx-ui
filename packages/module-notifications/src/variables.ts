import type { MustacheVariable } from '@webx-ui/core'
import type { NotificationEvent } from './types'

/** The variables of an event as the editor's suggestions take them. */
export function variablesOf(event: NotificationEvent | null | undefined): MustacheVariable[] {
  return (event?.variables ?? []).map((variable) => ({
    name: variable.name,
    label: variable.label,
    example: variable.example,
    fields: variable.fields.length > 0 ? variable.fields : undefined,
  }))
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

/** The addressees of an event a provider can send to: the visitor's e-mail, a chat id. */
export function addresseesOf(event: NotificationEvent | null | undefined, address: string) {
  return (event?.variables ?? []).filter((variable) => variable.address === address)
}
