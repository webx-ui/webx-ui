import type { InboxField } from './types'

/*
 * The steps of a form (FORM_STEPS §3.1), as the panel reads them out of the list of fields: a row
 * of type `step` starts one, and the fields after it, up to the next, are its step. The same rules
 * as `WebxUi\Inbox\Fields\Steps` — the server decides; this is what the list says beside a row.
 */

/** More do not fit the indicator on a phone: `Steps::MAX`. */
export const MAX_STEPS = 10

export function isStep(field: InboxField): boolean {
  return field.type === 'step'
}

/** The fields a visitor answers: no step rows, nothing switched off, nothing of a step switched off. */
export function questionsOf(fields: InboxField[]): InboxField[] {
  let on = true

  return fields.filter((field) => {
    if (isStep(field)) {
      on = field.is_enabled

      return false
    }

    return on && field.is_enabled
  })
}

/** Whether the fields before the first step row make a first step of their own. */
export function startsUnnamed(fields: InboxField[]): boolean {
  const first = fields.findIndex(isStep)

  return first > 0
}

/** How many steps the form has: 0 for a flat form. */
export function stepCount(fields: InboxField[]): number {
  const rows = fields.filter(isStep).length

  return rows === 0 ? 0 : rows + (startsUnnamed(fields) ? 1 : 0)
}

/** The number each step row shows, by id: the fields above the first row are step 1. */
export function stepNumbers(fields: InboxField[]): Map<number, number> {
  const numbers = new Map<number, number>()
  let number = startsUnnamed(fields) ? 1 : 0

  for (const field of fields) {
    if (isStep(field)) numbers.set(field.id, ++number)
  }

  return numbers
}

/** Step rows with no field after them, by id: the server's `step.empty`, and nothing on the site. */
export function emptySteps(fields: InboxField[]): Set<number> {
  const empty = new Set<number>()

  fields.forEach((field, index) => {
    const next = fields[index + 1]

    if (isStep(field) && (next === undefined || isStep(next))) empty.add(field.id)
  })

  return empty
}

/** Whether a field stands under a step row — drawn indented under it in the list. */
export function insideStep(fields: InboxField[], field: InboxField): boolean {
  const at = fields.indexOf(field)

  return !isStep(field) && fields.slice(0, Math.max(at, 0)).some(isStep)
}
