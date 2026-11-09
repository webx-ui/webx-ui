import { localizedValue, type LocalizedValue } from '@webx-ui/core'
import type { FieldChoice, FieldType, InboxField } from './types'

/**
 * When a field is shown (FORM_STEPS §4), as the panel reads and writes it: `options.when` /
 * `options.unless`, `{ "<name of a field>": ["<value>", …] }` — "or" inside a list, "and"
 * between names. Mirrors `WebxUi\Inbox\Fields\Conditions`; the server decides and checks, this
 * only draws and warns before a save would be refused.
 */
export type Condition = Record<string, string[]>

export type ConditionKey = 'when' | 'unless'

export const CONDITION_KEYS: ConditionKey[] = ['when', 'unless']

/** The types a condition may name: the ones whose answer is one of a written-down list. */
export const CONDITION_SOURCES: FieldType[] = ['select', 'radio', 'checkbox', 'consent']

/** What a ticked consent sends, and so what a condition on it counts on. */
export const TICKED = '1'

/** The codes the server refuses with, in the same words (`condition.*`). */
export type ConditionProblemCode = 'unknown_field' | 'not_choice' | 'not_before' | 'unknown_value'

export interface ConditionProblem {
  code: ConditionProblemCode
  key: ConditionKey
  /** The field the condition names. */
  name: string
  /** The value it counts on, for `unknown_value`. */
  value?: string
}

/** A condition as kept: names to non-empty lists, nothing empty — null is "always". */
export function cleanCondition(input: unknown): Condition | null {
  if (input === null || typeof input !== 'object' || Array.isArray(input)) return null

  const condition: Condition = {}

  for (const [name, values] of Object.entries(input as Record<string, unknown>)) {
    const key = name.trim()
    const list = (Array.isArray(values) ? values : [values])
      .map((value) => (value === true ? TICKED : typeof value === 'string' ? value.trim() : ''))
      .filter((value) => value !== '')

    if (key !== '' && list.length > 0) condition[key] = [...new Set(list)]
  }

  return Object.keys(condition).length === 0 ? null : condition
}

export function conditionOf(options: unknown, key: ConditionKey): Condition | null {
  return cleanCondition((options as Record<string, unknown> | null)?.[key])
}

export function isSource(type: FieldType): boolean {
  return CONDITION_SOURCES.includes(type)
}

/** The answers a condition may count on: the field's choices, or the tick of a consent. */
export function valuesOf(field: InboxField, ticked: string): FieldChoice[] {
  if (field.type === 'consent') return [{ value: TICKED, label: ticked }]

  return Array.isArray(field.options.choices) ? field.options.choices : []
}

/** What is wrong with a field's conditions, among the fields of its form in their order. */
export function problemsOf(field: InboxField, fields: InboxField[]): ConditionProblem[] {
  const at = fields.findIndex((one) => one.id === field.id)
  const problems: ConditionProblem[] = []

  for (const key of CONDITION_KEYS) {
    for (const [name, values] of Object.entries(conditionOf(field.options, key) ?? {})) {
      const index = fields.findIndex((one) => one.key === name)
      const source = fields[index]

      if (source === undefined) {
        problems.push({ code: 'unknown_field', key, name })
      } else if (!isSource(source.type)) {
        problems.push({ code: 'not_choice', key, name })
      } else if (at >= 0 && index >= at) {
        problems.push({ code: 'not_before', key, name })
      } else {
        const known = valuesOf(source, '').map((choice) => choice.value)

        for (const value of values) {
          if (!known.includes(value)) problems.push({ code: 'unknown_value', key, name, value })
        }
      }
    }
  }

  return problems
}

/**
 * A condition in words, out of the labels of what it names: «Purpose = Agency, Pre-register ·
 * Agent = Ticked». A name or value the form no longer has is shown as it is written.
 */
export function describeCondition(
  condition: Condition,
  fields: InboxField[],
  locale: string,
  ticked: string,
): string {
  return Object.entries(condition)
    .map(([name, values]) => {
      const source = fields.find((one) => one.key === name)

      if (source === undefined) return `${name} = ${values.join(', ')}`

      const choices = valuesOf(source, ticked)
      const words = values.map((value) => {
        const choice = choices.find((one) => one.value === value)

        return choice === undefined ? value : labelOf(choice.label, locale, value)
      })

      return `${localizedValue(source.title, locale, source.key)} = ${words.join(', ')}`
    })
    .join(' · ')
}

export function labelOf(label: LocalizedValue | string, locale: string, fallback: string): string {
  return localizedValue(label, locale, fallback) || fallback
}
