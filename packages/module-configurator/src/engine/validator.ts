import { CHOICE_TYPES, fields, float, isRecord, options, scalar, steps } from './engine'
import { FormulaError, formulaKeys, parseFormula } from './formula'

/**
 * Whether a schema document is one the engine can price and a site can show (§9) — the JS copy
 * of `Schema\Validator`, giving the same paths and codes for the editor to underline before a
 * save. The server checks anyway and is the truth; one thing only it can know — whether the media
 * library has a picture (`image.unknown`) — is not checked here.
 *
 *     validate(schema, 'uk')
 *     // [{ path: 'steps.0.options.1.area', code: 'area.syntax', params: { position: 3, … } }, …]
 */

export interface Problem {
  /** Dotted: `steps.2.options.0.price.perM2`; empty for the document itself. */
  path: string
  /** A code of §9 — `errors.<code>` in the module's words. */
  code: string
  /** What the words name: `:key`, `:step`, `:value`, `:position`… */
  params: Record<string, string | number>
}

/** The schema format this module reads. */
export const SCHEMA_VERSION = 1

/** A step or field key: also a name in a formula. PHP's `$` lets a final newline through; so does this. */
export const KEY_PATTERN = /^[a-z][a-z0-9_]{0,63}\n?$/

/** An option value: a slug. */
export const VALUE_PATTERN = /^[a-z0-9][a-z0-9_-]{0,63}\n?$/

export const STEP_TYPES = ['cards', 'swatches', 'buttons', 'dimensions'] as const

const HEX = '#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})'
const COLOUR = new RegExp(`^${HEX}$`)
const GRADIENT = /^(?:linear|radial)-gradient\(([\s\S]+)\)$/
const TOKEN = `(?:${HEX}|-?\\d+(?:\\.\\d+)?(?:%|deg)?|to|left|right|top|bottom|circle|ellipse|at|center|,)`
const TOKENS = new RegExp(`^(?:\\s*${TOKEN}\\s*)+$`)

/**
 * A swatch goes into a `style` attribute, so it is a closed list of tokens (§5.5): a `url()` or a
 * `;` in there would be a door for anybody's CSS.
 */
export function isSwatch(value: unknown): boolean {
  if (typeof value !== 'string') return false

  const trimmed = value.trim()

  if (COLOUR.test(trimmed)) return true

  const gradient = GRADIENT.exec(trimmed)

  return gradient !== null && TOKENS.test(gradient[1]!)
}

type Item = Record<string, unknown>

interface Known {
  index: number
  choice: boolean
  values: string[]
}

/** A JSON object as PHP decodes it: `{}` and `[]` are both an empty array there. */
function isMap(value: unknown): value is Item {
  return isRecord(value) && (!Array.isArray(value) || value.length === 0)
}

/** A JSON list as PHP decodes it — `{}` is an empty one. */
function isList(value: unknown): value is unknown[] {
  return Array.isArray(value) || (isRecord(value) && Object.keys(value).length === 0)
}

function entries(value: unknown): [string, unknown][] {
  return isRecord(value) ? Object.entries(value) : []
}

function isNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value)
}

function isDate(value: unknown): boolean {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false

  const [year, month, day] = value.split('-').map(Number) as [number, number, number]
  const date = new Date(Date.UTC(year, month - 1, day))

  return (
    date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day
  )
}

/** An index PHP would have made an integer of — never a language code. */
function isIndex(key: string): boolean {
  return /^(?:0|-?[1-9]\d*)$/.test(key)
}

function has(item: Item, key: string): boolean {
  return Object.hasOwn(item, key)
}

/**
 * Every problem of a document, in the order the server lists them.
 *
 * @param locale the site's default language: every text needs words in it
 */
export function validate(schema: unknown, locale: string): Problem[] {
  const problems: Problem[] = []
  const fail = (path: string, code: string, params: Problem['params'] = {}): void => {
    problems.push({ path, code, params })
  }

  if (!isMap(schema)) return [{ path: '', code: 'schema.invalid', params: {} }]

  if (schema.version !== SCHEMA_VERSION) fail('version', 'schema.version')

  if (typeof schema.currency !== 'string' || !/^[A-Z]{3}$/.test(schema.currency)) {
    fail('currency', 'currency.invalid')
  }

  if (has(schema, 'pricing')) {
    const round = isRecord(schema.pricing) ? (schema.pricing.round ?? 1) : null
    const number = float(round)

    if (number === null || number <= 0 || typeof round === 'string')
      fail('pricing.round', 'pricing.round')
  }

  checkDiscount(schema)

  let list: unknown[] = []

  if (!Array.isArray(schema.steps) || schema.steps.length === 0) {
    fail('steps', 'steps.empty')
  } else {
    list = schema.steps
  }

  const fieldKeys = keysOfFields(list)

  checkSteps(list, fieldKeys)
  checkInitial(schema.initial, list)
  checkPreviews(schema.previews, list)

  return problems

  // ---------------------------------------------------------------------------------------

  function text(value: unknown, path: string): void {
    if (typeof value === 'string') return

    if (isRecord(value) && !Array.isArray(value) && Object.keys(value).length > 0) {
      for (const [code, words] of Object.entries(value)) {
        if (isIndex(code) || typeof words !== 'string') {
          fail(path, 'text.invalid')

          return
        }
      }

      return
    }

    fail(path, 'text.invalid')
  }

  function checkDiscount(document: Item): void {
    if (!has(document, 'discount') || document.discount === null) return

    const given = isRecord(document.discount) ? document.discount : {}
    const percent = (given as Item).percent
    const number = float(percent)

    if (typeof percent === 'string' || number === null || number <= 0 || number >= 100) {
      fail('discount.percent', 'discount.percent')
    }

    const until = (given as Item).until ?? null

    if (until !== null && !isDate(until)) fail('discount.until', 'discount.until')

    if (has(given as Item, 'label')) text((given as Item).label, 'discount.label')
  }

  function label(item: Item, path: string, keys: string[]): void {
    for (const key of keys) {
      const words = item[key]

      if (typeof words === 'string' && words.trim() !== '') return

      if (isRecord(words)) {
        const own = (words as Item)[locale]

        if (typeof own === 'string' && own.trim() !== '') return
      }
    }

    fail(`${path}.${keys[0]}`, 'label.missing', { locale })
  }

  function texts(item: Item, path: string, keys: string[]): void {
    for (const key of keys) {
      if (has(item, key) && item[key] !== null) text(item[key], `${path}.${key}`)
    }
  }

  function key(value: unknown, path: string, taken: string[]): void {
    if (typeof value !== 'string' || !KEY_PATTERN.test(value)) {
      fail(path, 'key.invalid', { key: scalar(value) ?? '' })

      return
    }

    if (taken.includes(value)) {
      fail(path, 'key.duplicate', { key: value })

      return
    }

    taken.push(value)
  }

  function condition(
    given: unknown,
    path: string,
    known: Map<string, Known>,
    index: number | null,
    prefix: 'condition' | 'previews',
  ): void {
    const code = (what: string) =>
      prefix === 'previews' ? 'previews.condition' : `condition.${what}`

    if (!isMap(given)) {
      fail(path, code('invalid'))

      return
    }

    for (const [step, values] of entries(given)) {
      const here = `${path}.${step}`

      if (!isList(values)) {
        fail(here, code('invalid'))
        continue
      }

      const target = known.get(step)

      if (!target) {
        fail(here, code('unknown_step'), { step })
        continue
      }

      if (index !== null && target.index >= index) {
        fail(here, code('forward'), { step })
        continue
      }

      if (!target.choice) {
        fail(here, code('not_choice'), { step })
        continue
      }

      entries(values).forEach(([at, value]) => {
        if (typeof value !== 'string' || !target.values.includes(value)) {
          fail(`${here}.${at}`, code('unknown_value'), { step, value: scalar(value) ?? '' })
        }
      })
    }
  }

  function conditions(item: Item, path: string, known: Map<string, Known>, index: number): void {
    for (const which of ['when', 'unless']) {
      if (has(item, which) && item[which] !== null) {
        condition(item[which], `${path}.${which}`, known, index, 'condition')
      }
    }
  }

  function price(given: unknown, path: string, present: boolean): void {
    if (!present || given === null) return

    if (!isMap(given)) {
      fail(path, 'price.invalid')

      return
    }

    for (const [name, amount] of entries(given)) {
      if (!['fixed', 'perM2', 'percent'].includes(name) || (amount !== null && !isNumber(amount))) {
        fail(`${path}.${name}`, 'price.invalid')
      }
    }
  }

  function formula(given: unknown, path: string, known: string[]): void {
    if (typeof given !== 'string') {
      fail(path, 'area.syntax', { position: 0, reason: 'not a string' })

      return
    }

    let keys: string[]

    try {
      keys = formulaKeys(parseFormula(given))
    } catch (error) {
      if (!(error instanceof FormulaError)) throw error

      fail(path, 'area.syntax', { position: error.position, reason: error.reason })

      return
    }

    for (const name of keys) {
      if (!known.includes(name)) fail(path, 'area.unknown_key', { key: name })
    }
  }

  function sizes(
    given: unknown,
    path: string,
    known: string[],
    unknown: string,
    pair: boolean,
    present: boolean,
  ): void {
    if (!present || given === null) return

    if (!isMap(given)) {
      fail(path, 'range.invalid')

      return
    }

    for (const [field, value] of entries(given)) {
      if (!known.includes(field)) {
        fail(`${path}.${field}`, unknown, { key: field })
        continue
      }

      const valid = pair
        ? Array.isArray(value) &&
          value.length === 2 &&
          isNumber(value[0]) &&
          isNumber(value[1]) &&
          value[0] <= value[1]
        : isNumber(value)

      if (!valid) fail(`${path}.${field}`, 'range.invalid')
    }
  }

  function option(
    item: Item,
    path: string,
    type: string,
    values: string[],
    known: string[],
    steps_: Map<string, Known>,
    index: number,
  ): void {
    const value = item.value

    if (typeof value !== 'string' || !VALUE_PATTERN.test(value)) {
      fail(`${path}.value`, 'value.invalid', { value: scalar(value) ?? '' })
    } else if (values.includes(value)) {
      fail(`${path}.value`, 'value.duplicate', { value })
    } else {
      values.push(value)
    }

    label(item, path, ['label'])
    texts(item, path, ['label', 'subtitle'])

    // A card without a picture; one given but not a key is told once, by the loop below.
    if (type === 'cards' && (item.image ?? null) === null) {
      fail(`${path}.image`, 'image.missing')
    }

    for (const picture of ['image', 'swatchImage']) {
      if (!has(item, picture) || item[picture] === null) continue

      const given = item[picture]

      if (typeof given !== 'string' || given.trim() === '')
        fail(`${path}.${picture}`, 'image.missing')
    }

    if (has(item, 'swatch') && item.swatch !== null && !isSwatch(item.swatch)) {
      fail(`${path}.swatch`, 'swatch.invalid')
    }

    price(item.price ?? null, `${path}.price`, has(item, 'price'))

    if (has(item, 'area')) formula(item.area, `${path}.area`, known)

    sizes(
      item.defaults ?? null,
      `${path}.defaults`,
      known,
      'defaults.unknown_field',
      false,
      has(item, 'defaults'),
    )
    sizes(
      item.limits ?? null,
      `${path}.limits`,
      known,
      'limits.unknown_field',
      true,
      has(item, 'limits'),
    )
    conditions(item, path, steps_, index)
  }

  function checkFields(
    step: Item,
    path: string,
    taken: string[],
    known: Map<string, Known>,
    index: number,
  ): void {
    const list_ = step.fields

    if (!Array.isArray(list_) || list_.length === 0) {
      fail(`${path}.fields`, 'fields.empty')

      return
    }

    list_.forEach((field, at) => {
      const here = `${path}.fields.${at}`

      if (!isRecord(field)) {
        fail(`${here}.key`, 'key.invalid')

        return
      }

      key(field.key ?? null, `${here}.key`, taken)
      label(field, here, ['label'])
      texts(field, here, ['label', 'unit'])

      const min = field.min ?? null
      const max = field.max ?? null

      if (!isNumber(min) || !isNumber(max) || min > max) fail(here, 'range.invalid')

      if (has(field, 'step') && (!isNumber(field.step) || field.step <= 0)) {
        fail(`${here}.step`, 'range.invalid')
      }

      if (has(field, 'default') && field.default !== null && !isNumber(field.default)) {
        fail(`${here}.default`, 'range.invalid')
      }

      conditions(field, here, known, index)
    })
  }

  function checkSteps(list_: unknown[], known_: string[]): void {
    const taken: string[] = []
    const known = new Map<string, Known>()
    const main = new Map<number, number[]>()
    const perM2: string[] = []

    list_.forEach((step, index) => {
      if (isRecord(step) && typeof step.key === 'string' && !known.has(step.key)) {
        known.set(step.key, {
          index,
          choice: (CHOICE_TYPES as readonly unknown[]).includes(step.type),
          values: options(step).map((one) => scalar(one.value) ?? ''),
        })
      }
    })

    list_.forEach((step, index) => {
      const path = `steps.${index}`

      if (!isRecord(step)) {
        fail(path, 'type.unknown')

        return
      }

      key(step.key ?? null, `${path}.key`, taken)

      const type = step.type

      if (typeof type !== 'string' || !(STEP_TYPES as readonly string[]).includes(type)) {
        fail(`${path}.type`, 'type.unknown')

        return
      }

      label(step, path, ['label', 'title'])
      texts(step, path, ['label', 'title', 'hint'])
      conditions(step, path, known, index)

      if (type === 'dimensions') {
        checkFields(step, path, taken, known, index)

        return
      }

      const items = step.options

      if (!Array.isArray(items) || items.length === 0) {
        fail(`${path}.options`, 'options.empty')

        return
      }

      const values: string[] = []

      items.forEach((item, at) => {
        const here = `${path}.options.${at}`

        if (!isRecord(item)) {
          fail(`${here}.value`, 'value.invalid')

          return
        }

        option(item, here, type, values, known_, known, index)

        if (has(item, 'area')) main.set(index, [...(main.get(index) ?? []), at])

        const rate = isRecord(item.price) ? float(item.price.perM2) : null

        if (rate !== null && rate !== 0) perM2.push(`${here}.price.perM2`)
      })
    })

    // Exactly one step is the main one, and all of its options have a formula (§7.1); with
    // none, nothing may be priced per square metre.
    if (main.size === 0) {
      perM2.forEach((path) => fail(path, 'price.per_m2'))

      return
    }

    const indexes = [...main.keys()]

    indexes.slice(1).forEach((index) => fail(`steps.${index}`, 'area.main_count'))

    const first = indexes[0]!
    const items = (list_[first] as Item).options

    entries(items).forEach(([at, item]) => {
      if (isRecord(item) && !has(item, 'area'))
        fail(`steps.${first}.options.${at}.area`, 'area.missing')
    })
  }

  function checkInitial(given: unknown, list_: unknown[]): void {
    if (given === undefined || given === null) return

    if (!isMap(given)) {
      fail('initial', 'initial.unknown')

      return
    }

    const choices = new Map<string, string[]>()
    const sizes_: string[] = []

    for (const step of steps({ steps: list_ })) {
      if ((CHOICE_TYPES as readonly unknown[]).includes(step.type)) {
        choices.set(
          step.key as string,
          options(step).map((one) => scalar(one.value) ?? ''),
        )
      }

      for (const field of fields(step)) sizes_.push(field.key as string)
    }

    for (const [name, value] of entries(given)) {
      const values = choices.get(name)
      const valid = values
        ? typeof value === 'string' && values.includes(value)
        : sizes_.includes(name) && isNumber(value)

      if (!valid) fail(`initial.${name}`, 'initial.unknown', { key: name })
    }
  }

  function checkPreviews(given: unknown, list_: unknown[]): void {
    if (given === undefined || given === null) return

    if (!isList(given)) {
      fail('previews', 'previews.condition')

      return
    }

    const known = new Map<string, Known>()

    steps({ steps: list_ }).forEach((step, index) => {
      if (known.has(step.key as string)) return

      known.set(step.key as string, {
        index,
        choice: (CHOICE_TYPES as readonly unknown[]).includes(step.type),
        values: options(step).map((one) => scalar(one.value) ?? ''),
      })
    })

    entries(given).forEach(([at, preview]) => {
      const path = `previews.${at}`

      if (!isRecord(preview)) {
        fail(path, 'previews.condition')

        return
      }

      condition(preview.when ?? null, `${path}.when`, known, null, 'previews')

      if (typeof preview.image !== 'string' || preview.image.trim() === '') {
        fail(`${path}.image`, 'image.missing')
      }
    })
  }
}

/** The keys of every size field, wherever its step stands: a formula above it still names them. */
function keysOfFields(list: unknown[]): string[] {
  const keys: string[] = []

  for (const step of list) {
    if (!isRecord(step) || step.type !== 'dimensions' || !isRecord(step.fields)) continue

    for (const field of Object.values(step.fields)) {
      if (isRecord(field) && typeof field.key === 'string') keys.push(field.key)
    }
  }

  return keys
}
