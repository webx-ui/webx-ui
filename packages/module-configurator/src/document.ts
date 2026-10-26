import { toRaw } from 'vue'
import type { LocalizedValue } from '@webx-ui/core'
import { CHOICE_TYPES } from './engine/engine'
import type {
  Condition,
  ConfiguratorSchema,
  FieldDoc,
  OptionDoc,
  StepDoc,
  StepType,
  Text,
} from './engine/types'
import type { Problem } from './engine/validator'

/**
 * Edits of the schema document the constructor makes (§13.2) — the parts that are more than
 * setting one value: a key that is new and free, a step renamed with every condition that names
 * it, an option taken away with every condition that chose it.
 *
 * The constructor edits the document in place, the way a form edits its values; these functions
 * do the same, so whatever holds the document sees the change.
 */

/** A text of the document as a localized field edits it: a string is the default language. */
export function localized(text: Text | undefined | null, fallback: string): LocalizedValue {
  if (typeof text === 'string') return text === '' ? {} : { [fallback]: text }

  return text && typeof text === 'object' ? { ...text } : {}
}

/**
 * A localized field's value as the document keeps it: languages left empty dropped, nothing at
 * all — no key. A text with nothing in it is not a text, and the check would call it invalid.
 */
export function written(value: LocalizedValue | string | null | undefined): Text | undefined {
  if (typeof value === 'string') return value.trim() === '' ? undefined : value

  const kept = Object.fromEntries(
    Object.entries(value ?? {}).filter(
      ([, words]) => typeof words === 'string' && words.trim() !== '',
    ),
  )

  return Object.keys(kept).length === 0 ? undefined : kept
}

/** Put a text into an item, or take the key away when it is empty. */
export function setText(item: object, key: string, value: LocalizedValue | string | null): void {
  const text = written(value)
  const target = item as Record<string, unknown>

  if (text === undefined) delete target[key]
  else target[key] = text
}

/** A text in one language: the one asked for, the default, any. */
export function textIn(text: Text | undefined | null, locale: string, fallback = ''): string {
  if (typeof text === 'string') return text
  if (!text || typeof text !== 'object') return fallback

  for (const words of [text[locale], ...Object.values(text)]) {
    if (typeof words === 'string' && words.trim() !== '') return words
  }

  return fallback
}

export function isChoice(type: unknown): boolean {
  return (CHOICE_TYPES as readonly unknown[]).includes(type)
}

/** Every key steps and size fields hold: one namespace (§5.2). */
export function takenKeys(schema: ConfiguratorSchema): string[] {
  return schema.steps.flatMap((step) => [
    step.key,
    ...(step.type === 'dimensions' ? (step.fields ?? []).map((field) => field.key) : []),
  ])
}

/** The keys of the size fields — what a formula, `defaults` and `limits` may name. */
export function fieldKeys(schema: ConfiguratorSchema): string[] {
  return schema.steps.flatMap((step) =>
    step.type === 'dimensions' ? (step.fields ?? []).map((field) => field.key) : [],
  )
}

/** `base`, or `base_2`, `base_3`… — the first that nothing holds. */
export function freeKey(base: string, taken: string[], separator = '_'): string {
  if (!taken.includes(base)) return base

  for (let n = 2; ; n++) {
    const candidate = `${base}${separator}${n}`

    if (!taken.includes(candidate)) return candidate
  }
}

/** The steps a condition of the step at `index` may name: choice steps above it (§5.6). */
export function stepsAbove(schema: ConfiguratorSchema, index: number): StepDoc[] {
  return schema.steps.slice(0, Math.max(0, index)).filter((step) => isChoice(step.type))
}

/** A new step of a type, with one option or one field so that it is never empty. */
export function newStep(schema: ConfiguratorSchema, type: StepType, locale: string): StepDoc {
  const taken = takenKeys(schema)
  const key = freeKey(type === 'dimensions' ? 'size' : 'step', taken)

  if (type === 'dimensions') {
    const field = newField([...taken, key], locale)

    return { key, type, title: { [locale]: '' }, fields: [field] }
  }

  return {
    key,
    type,
    label: { [locale]: '' },
    options: [newOption({ key, type, options: [] }, locale)],
  }
}

/**
 * A new option of a step. On the main step it gets a formula — every option of the main step
 * needs one (§7.1) — copied from the option above, which is nearly always what the next one is.
 */
export function newOption(step: StepDoc, locale: string): OptionDoc {
  const existing = step.options ?? []
  const value = freeKey(
    'option',
    existing.map((one) => one.value),
    '-',
  )
  const option: OptionDoc = { value, label: { [locale]: '' } }
  const formula = existing.find((one) => typeof one.area === 'string')?.area

  if (formula !== undefined) option.area = formula
  if (step.type === 'swatches') option.swatch = '#cccccc'
  if (step.type === 'cards') option.image = null

  return option
}

export function newField(taken: string[], locale: string): FieldDoc {
  return {
    key: freeKey('w', taken),
    label: { [locale]: '' },
    unit: { [locale]: 'mm' },
    min: 0,
    max: 1000,
    step: 10,
  }
}

function rewriteCondition(
  condition: Condition | undefined,
  change: (step: string, values: string[]) => [string, string[]] | null,
): Condition | undefined {
  if (!condition) return condition

  const next: Condition = {}

  for (const [step, values] of Object.entries(condition)) {
    const kept = change(step, values)

    if (kept) next[kept[0]] = kept[1]
  }

  return next
}

/** Every condition of the document — of steps, options, fields and picture rules — rewritten. */
function eachCondition(
  schema: ConfiguratorSchema,
  change: (step: string, values: string[]) => [string, string[]] | null,
): void {
  const holders: { when?: Condition; unless?: Condition }[] = []

  for (const step of schema.steps) {
    holders.push(step, ...(step.options ?? []), ...(step.fields ?? []))
  }

  for (const holder of holders) {
    for (const which of ['when', 'unless'] as const) {
      const rewritten = rewriteCondition(holder[which], change)

      if (rewritten === undefined) continue
      if (Object.keys(rewritten).length === 0) delete holder[which]
      else holder[which] = rewritten
    }
  }

  for (const rule of schema.previews ?? []) rule.when = rewriteCondition(rule.when, change) ?? {}
}

/**
 * A step got a new key: every condition and the starting choice follow it, so a rename never
 * breaks what was built on the old one. A size field renamed the same way also rewrites the
 * formulas, `defaults` and `limits` that name it.
 */
export function renameKey(schema: ConfiguratorSchema, from: string, to: string): void {
  if (from === to || from === '') return

  eachCondition(schema, (step, values) => [step === from ? to : step, values])

  if (schema.initial && from in schema.initial) {
    schema.initial = Object.fromEntries(
      Object.entries(schema.initial).map(([key, value]) => [key === from ? to : key, value]),
    )
  }

  const word = new RegExp(`(?<![a-z0-9_])${from}(?![a-z0-9_])`, 'g')

  for (const step of schema.steps) {
    for (const option of step.options ?? []) {
      if (typeof option.area === 'string') option.area = option.area.replace(word, to)

      for (const which of ['defaults', 'limits'] as const) {
        const sizes = option[which] as Record<string, unknown> | undefined

        if (sizes && from in sizes) {
          option[which] = Object.fromEntries(
            Object.entries(sizes).map(([key, value]) => [key === from ? to : key, value]),
          ) as never
        }
      }
    }
  }
}

/** An option of a step got a new value: the conditions that chose it choose the new one. */
export function renameValue(
  schema: ConfiguratorSchema,
  stepKey: string,
  from: string,
  to: string,
): void {
  if (from === to) return

  eachCondition(schema, (step, values) => [
    step,
    step === stepKey ? values.map((value) => (value === from ? to : value)) : values,
  ])

  if (schema.initial?.[stepKey] === from) schema.initial[stepKey] = to
}

/** A step is gone: conditions on it are dropped, and so is its starting choice. */
export function forgetKey(schema: ConfiguratorSchema, key: string): void {
  eachCondition(schema, (step, values) => (step === key ? null : [step, values]))

  if (schema.initial && key in schema.initial) delete schema.initial[key]
}

/** An option is gone: conditions stop naming it, and one left with nothing to name is dropped. */
export function forgetValue(schema: ConfiguratorSchema, stepKey: string, value: string): void {
  eachCondition(schema, (step, values) => {
    if (step !== stepKey) return [step, values]

    const kept = values.filter((one) => one !== value)

    return kept.length === 0 ? null : [step, kept]
  })

  if (schema.initial?.[stepKey] === value) delete schema.initial[stepKey]
}

/** The problems at a place and under it: `steps.2` holds `steps.2.options.0.area`. */
export function problemsUnder(problems: Problem[], path: string): Problem[] {
  return problems.filter((one) => one.path === path || one.path.startsWith(`${path}.`))
}

/** The problems at exactly this place. */
export function problemsAt(problems: Problem[], path: string): Problem[] {
  return problems.filter((one) => one.path === path)
}

/** A stable id for an item edited in place — what a sortable list keys a row by while its key is typed. */
const ids = new WeakMap<object, number>()
let next = 0

export function rowId(proxy: object): number {
  const item = toRaw(proxy)
  let id = ids.get(item)

  if (id === undefined) {
    id = ++next
    ids.set(item, id)
  }

  return id
}
