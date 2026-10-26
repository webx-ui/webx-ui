import { evaluateFormula, FormulaError, parseFormula } from './formula'
import type {
  ConfiguratorState,
  ConfiguratorValue,
  ConfiguratorValues,
  Quote,
  QuoteLine,
  ValueError,
} from './types'

/**
 * The configurator's engine: visibility, the choice, the sizes and the price (§6, §7).
 *
 * The JS copy of `Engine\Engine` — the server's is the truth (decision 3), this one draws the
 * number first: in the editor's preview now, in the widget of the site next. Both implement the
 * same text, and the cases in `php/packages/module-configurator/resources/fixtures/pricing/` hold
 * them together (`engine.parity.test.ts`).
 *
 * Pure functions over the document — no Vue, no dependencies, no clock: the day a discount is
 * checked against is an argument, so the same source builds into the widget. The document is read
 * defensively, as on the server: a draft is saved with problems, and its preview must not throw.
 * Records are made without a prototype, so a key of the document never lands on one.
 */

/** The step types a visitor chooses one option in. */
export const CHOICE_TYPES = ['cards', 'swatches', 'buttons'] as const

type Item = Record<string, unknown>

/** Whether the server would read this as an array — a JSON object or list. */
export function isRecord(value: unknown): value is Item {
  return typeof value === 'object' && value !== null
}

/** `map()` of the server: what is not an object or a list counts as empty. */
export function map(value: unknown): Item {
  return isRecord(value) ? value : {}
}

function dict<T>(): Record<string, T> {
  return Object.create(null) as Record<string, T>
}

function has(item: Item, key: string): boolean {
  return Object.hasOwn(item, key)
}

/** What PHP's `is_scalar` lets through, as its `(string)` writes it. */
export function scalar(value: unknown): string | null {
  if (typeof value === 'string') return value
  if (typeof value === 'number') return String(value)
  if (typeof value === 'boolean') return value ? '1' : ''

  return null
}

const NUMERIC = /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/

/** A number, from a number or a numeric string; null for anything else. */
export function float(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value !== 'string') return null

  const trimmed = value.trim()

  return NUMERIC.test(trimmed) ? Number(trimmed) : null
}

/** The steps that have a key, skipping what is not a step at all. */
export function steps(schema: unknown): Item[] {
  return Object.values(map(map(schema).steps)).filter(
    (step): step is Item => isRecord(step) && typeof step.key === 'string' && step.key !== '',
  )
}

export function options(step: Item): Item[] {
  return Object.values(map(step.options)).filter((option): option is Item => {
    if (!isRecord(option)) return false

    const value = scalar(option.value)

    return value !== null && value !== ''
  })
}

export function fields(step: Item): Item[] {
  if (step.type !== 'dimensions') return []

  return Object.values(map(step.fields)).filter(
    (field): field is Item => isRecord(field) && typeof field.key === 'string' && field.key !== '',
  )
}

function isChoice(type: unknown): boolean {
  return (CHOICE_TYPES as readonly unknown[]).includes(type)
}

function valueOf(option: Item): string {
  return scalar(option.value) ?? ''
}

/**
 * Whether a step, an option or a field is visible (§5.6): its `when` holds — every step it names
 * visible and chosen one of the values — and its `unless` would not.
 */
export function visible(
  item: Item,
  resolved: Record<string, ConfiguratorValue>,
  visibleOptions: Record<string, string[]>,
): boolean {
  const holds = (condition: unknown): boolean => {
    for (const [step, values] of Object.entries(map(condition))) {
      if (!(step in visibleOptions) || !isRecord(values)) return false

      const allowed = Object.values(values)
        .map(scalar)
        .filter((one): one is string => one !== null)

      if (!allowed.includes(resolved[step] as string)) return false
    }

    return true
  }

  if (has(item, 'when') && !holds(item.when)) return false

  return !(has(item, 'unless') && holds(item.unless))
}

/**
 * The limits of a field: its own, replaced by the `limits` of the options chosen above it — the
 * later step wins.
 */
function limitsOf(field: Item, chosen: Item[]): [number, number] {
  let min = float(field.min) ?? 0
  let max = float(field.max) ?? min

  for (const option of chosen) {
    const limit = map(option.limits)[field.key as string]

    if (isRecord(limit)) {
      const low = float(limit[0])
      const high = float(limit[1])

      if (low !== null && high !== null) {
        min = low
        max = high
      }
    }
  }

  return [min, Math.max(min, max)]
}

/**
 * Where a size not given starts (§6.3): the preset or `initial`, the `defaults` of the options
 * chosen above (the later step wins), the field's own `default`, its minimum.
 */
function start(field: Item, name: string, initial: Item, chosen: Item[], min: number): number {
  const given = float(initial[name])

  if (given !== null) return given

  for (const option of [...chosen].reverse()) {
    const preset = float(map(option.defaults)[name])

    if (preset !== null) return preset
  }

  return float(field.default) ?? min
}

/**
 * Resolve values against the schema in one pass, in the order of the document (§6.1).
 *
 * A step's value is the one given if it is a visible option, the first visible option if it is
 * not; a step not given at all starts from `initial` (a block's preset over the schema's own,
 * §6.3). A size given outside its limits is an error — or, with `clamp`, pulled inside them: the
 * visitor's own stepper, never a request (§6.4).
 */
export function resolve(
  schema: unknown,
  values: Record<string, unknown>,
  initial: Record<string, unknown> | null = null,
  clamp = false,
): ConfiguratorState {
  const start_ = initial ?? map(map(schema).initial)
  const resolved = dict<ConfiguratorValue>()
  const hidden: string[] = []
  const visibleOptions = dict<string[]>()
  const limits = dict<[number, number]>()
  const errors: ValueError[] = []
  const chosen: Item[] = []

  for (const step of steps(schema)) {
    const key = step.key as string

    if (!visible(step, resolved, visibleOptions)) {
      hidden.push(key, ...fields(step).map((field) => field.key as string))
      continue
    }

    if (isChoice(step.type)) {
      const shown = new Map<string, Item>()

      for (const option of options(step)) {
        if (visible(option, resolved, visibleOptions)) shown.set(valueOf(option), option)
      }

      if (shown.size === 0) {
        hidden.push(key)
        continue
      }

      const wanted = scalar(has(values, key) ? values[key] : start_[key])
      const value = wanted !== null && shown.has(wanted) ? wanted : [...shown.keys()][0]!

      resolved[key] = value
      visibleOptions[key] = [...shown.keys()]
      chosen.push(shown.get(value)!)
      continue
    }

    let count = 0

    for (const field of fields(step)) {
      const name = field.key as string

      if (!visible(field, resolved, visibleOptions)) {
        hidden.push(name)
        continue
      }

      count++
      const [min, max] = limitsOf(field, chosen)
      limits[name] = [min, max]

      const sent = has(values, name)
      const given = sent ? float(values[name]) : null

      if (sent && !clamp) {
        if (given === null) errors.push({ path: `values.${name}`, code: 'values.number' })
        else if (given < min || given > max)
          errors.push({ path: `values.${name}`, code: 'values.range' })
      }

      const value = given ?? start(field, name, start_, chosen, min)
      resolved[name] = given !== null && !clamp ? value : Math.max(min, Math.min(max, value))
    }

    if (count === 0) hidden.push(key)
  }

  return { values: resolved, hidden, options: visibleOptions, limits, errors }
}

/**
 * The visitor chose an option (§6.2): the value, then the option's `defaults` into the sizes,
 * then everything resolved again and the sizes pulled into their new limits. Defaults are put in
 * only here — in `resolve` they would wipe out the size somebody typed on every recount.
 */
export function select(
  schema: unknown,
  state: ConfiguratorState,
  step: string,
  value: string,
  initial: Record<string, unknown> | null = null,
): ConfiguratorState {
  const values: Record<string, unknown> = { ...state.values, [step]: value }

  if (state.options[step]?.includes(value)) {
    for (const one of steps(schema)) {
      if (one.key !== step) continue

      for (const option of options(one)) {
        if (valueOf(option) !== value) continue

        for (const [field, number] of Object.entries(map(option.defaults))) {
          const size = float(number)

          if (size !== null) values[field] = size
        }
      }
    }
  }

  return resolve(schema, values, initial, true)
}

/** The visitor changed a size with the stepper or the keyboard: pulled into its limits. */
export function set(
  schema: unknown,
  state: ConfiguratorState,
  field: string,
  number: number,
  initial: Record<string, unknown> | null = null,
): ConfiguratorState {
  return resolve(schema, { ...state.values, [field]: number }, initial, true)
}

/**
 * The one rounding both copies use (§7.3): half away from zero, by a step, without the
 * language's own — `Math.round` and PHP's `round()` disagree on halves below zero. A step under 1
 * whose inverse is whole (0.01, 0.5) multiplies and divides by that inverse instead, so that a
 * cent comes out as `3128.64` and not `3128.6400000000003`.
 */
export function roundTo(x: number, step: number): number {
  if (x === 0 || step <= 0) return 0

  const inverse = 1 / step
  const whole = Math.floor(inverse + 0.5)

  const rounded =
    step < 1 && Math.abs(inverse - whole) < 1e-9
      ? Math.floor(Math.abs(x) * whole + 0.5) / whole
      : Math.floor(Math.abs(x) / step + 0.5) * step

  if (rounded === 0) return 0

  return x < 0 ? -rounded : rounded
}

/** The chosen value of a visible step, or null. */
export function choice(state: ConfiguratorState, step: string): string | null {
  const value = state.values[step]

  return step in state.options && typeof value === 'string' ? value : null
}

/** The numbers of the visible fields — what a formula reads. */
export function sizes(state: ConfiguratorState): Record<string, number> {
  const out = dict<number>()

  for (const field of Object.keys(state.limits)) out[field] = Number(state.values[field])

  return out
}

/** Each visible choice step with its chosen option, in the order of the document. */
export function chosenOptions(schema: unknown, state: ConfiguratorState): [string, Item][] {
  const chosen: [string, Item][] = []

  for (const step of steps(schema)) {
    const value = choice(state, step.key as string)

    if (value === null) continue

    const option = options(step).find((one) => valueOf(one) === value)

    if (option) chosen.push([step.key as string, option])
  }

  return chosen
}

/** The main step: the first choice step any option of which has an `area`. */
export function mainStep(schema: unknown): string | null {
  for (const step of steps(schema)) {
    if (!isChoice(step.type)) continue
    if (options(step).some((option) => has(option, 'area'))) return step.key as string
  }

  return null
}

/**
 * The area: the formula of the chosen option of the main step on the visible sizes, never below
 * 0 (§7.1). No main step, or a hidden one — 0.
 */
export function area(schema: unknown, state: ConfiguratorState): number {
  const main = mainStep(schema)

  if (main === null || choice(state, main) === null) return 0

  for (const [step, option] of chosenOptions(schema, state)) {
    if (step !== main || typeof option.area !== 'string') continue

    let value: number

    try {
      value = evaluateFormula(parseFormula(option.area), sizes(state))
    } catch (error) {
      if (error instanceof FormulaError) return 0
      throw error
    }

    return Number.isFinite(value) && value > 0 ? value : 0
  }

  return 0
}

/** The discount in force on a day (`YYYY-MM-DD`), as a percent; null when there is none or it is over. */
export function discount(schema: unknown, today: string): number | null {
  const given = map(map(schema).discount)
  const percent = float(given.percent)
  const until = given.until

  if (percent === null || percent <= 0 || percent >= 100) return null

  // ISO dates compare as strings; the last day is still a day of the discount.
  if (typeof until === 'string' && until !== '' && today > until) return null

  return percent
}

/**
 * The price of a resolved state (§7.2): sums, then simple percents, then the discount, then the
 * rounding of the total.
 *
 * Every line is rounded to a cent the moment it appears and the sums are of rounded lines, so
 * that what the breakdown shows adds up to what it says. A percent is of the subtotal — the
 * `fixed` and `perM2` lines — never of another percent.
 */
export function price(schema: unknown, state: ConfiguratorState, today: string): Quote {
  const size = area(schema, state)
  const chosen = chosenOptions(schema, state)
  const lines: QuoteLine[] = []
  let subtotal = 0

  for (const [step, option] of chosen) {
    const cost = map(option.price)
    const fixed = float(cost.fixed) ?? 0
    const rate = float(cost.perM2) ?? 0

    if (fixed !== 0) {
      const amount = roundTo(fixed, 0.01)
      lines.push({ step, option: valueOf(option), kind: 'fixed', amount })
      subtotal += amount
    }

    if (rate !== 0) {
      const amount = roundTo(rate * size, 0.01)
      lines.push({ step, option: valueOf(option), kind: 'perM2', rate, area: size, amount })
      subtotal += amount
    }
  }

  subtotal = roundTo(subtotal, 0.01)
  let accumulated = subtotal

  for (const [step, option] of chosen) {
    const percent = float(map(option.price).percent) ?? 0

    if (percent !== 0) {
      const amount = roundTo((subtotal * percent) / 100, 0.01)
      lines.push({ step, option: valueOf(option), kind: 'percent', rate: percent, amount })
      accumulated += amount
    }
  }

  accumulated = roundTo(accumulated, 0.01)
  let after = accumulated
  const off = discount(schema, today)

  if (off !== null && accumulated > 0) {
    const amount = -roundTo((accumulated * off) / 100, 0.01)
    lines.push({ step: null, option: null, kind: 'discount', rate: off, amount })
    after = roundTo(accumulated + amount, 0.01)
  }

  const given = float(map(map(schema).pricing).round) ?? 1
  const round = given > 0 ? given : 1
  const currency = map(schema).currency

  return {
    currency: typeof currency === 'string' ? currency : '',
    normalized: { ...state.values },
    hidden: [...state.hidden],
    area: size,
    subtotal,
    total_before_discount: Math.max(0, roundTo(accumulated, round)),
    total: Math.max(0, roundTo(after, round)),
    round,
    lines,
  }
}

/** Today in the visitor's calendar, `YYYY-MM-DD` — what a discount is checked against. */
export function today(date = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** Values out of a state as a plain object, for a request or a document's `initial`. */
export function plain(values: ConfiguratorValues): ConfiguratorValues {
  return Object.fromEntries(Object.entries(values))
}
