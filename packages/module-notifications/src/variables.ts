import type { MustacheVariable } from '@webx-ui/core'
import type { NotificationEvent } from './types'

export { snippetOf } from '@webx-ui/core'

/** The variables of an event as the editor's suggestions take them. */
export function variablesOf(event: NotificationEvent | null | undefined): MustacheVariable[] {
  return (event?.variables ?? []).map((variable) => ({
    name: variable.name,
    label: variable.label,
    example: variable.example,
    fields: variable.fields.length > 0 ? variable.fields : undefined,
  }))
}

/** The addressees of an event a provider can send to: the visitor's e-mail, a chat id. */
export function addresseesOf(event: NotificationEvent | null | undefined, address: string) {
  return (event?.variables ?? []).filter((variable) => variable.address === address)
}
