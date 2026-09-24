/**
 * The little of Blade a block template in the playground is written in.
 *
 * `@if`/`@elseif`/`@else`, `@foreach` (with `$key => $value` and `$loop->first`), `@blocks('field')`,
 * `{{ }}`, `{!! !!}` and `{{-- --}}`, with PHP-ish expressions inside: variables and their keys,
 * literals and arrays, `! && || ?? ?: ? :`, comparisons, `.` and arithmetic, and a short list of
 * functions a template of a block actually calls (`nl2br(e(…))`, `mb_substr` for initials,
 * `in_array`, `count`, `str_repeat`…).
 *
 * Still a fixture and not a second Blade: it is here so that a template with a layout switch
 * (`@if ($layout === 'slider')`) or a condition on one item of a list previews as what the site
 * would draw. Anything it cannot read stays in the output as written — an expression it does not
 * know is printed raw, which is how a broken template keeps looking broken in the preview.
 */

type Scope = Record<string, unknown>

type Node =
  | { kind: 'text'; text: string }
  | { kind: 'echo'; expression: string; raw: boolean; source: string }
  | { kind: 'if'; branches: { test: string | null; body: Node[] }[] }
  | { kind: 'foreach'; list: string; key: string | null; alias: string; body: Node[] }
  | { kind: 'blocks'; argument: string }

/**
 * A template drawn with its values. `children` are the drawn blocks of each `wx-blocks` field,
 * which `@blocks('field')` prints.
 */
export function blade(template: string, values: Scope, children: Record<string, string>): string {
  return render(parse(template.replace(/\{\{--[\s\S]*?--\}\}/g, '')), values, children)
}

/* ---------------------------------------------------------------------------- parsing ----- */

const TOKEN = /\{\{|\{!!|@(elseif|else|endif|endforeach|foreach|if|blocks)\b/g

function parse(template: string): Node[] {
  const root: Node[] = []
  /* What is open: the innermost is where text goes. */
  const stack: (Extract<Node, { kind: 'if' }> | Extract<Node, { kind: 'foreach' }>)[] = []

  const into = (): Node[] => {
    const top = stack[stack.length - 1]

    if (top === undefined) return root
    if (top.kind === 'foreach') return top.body

    return top.branches[top.branches.length - 1]!.body
  }

  const text = (value: string): void => {
    if (value !== '') into().push({ kind: 'text', text: value })
  }

  let at = 0

  for (;;) {
    TOKEN.lastIndex = at
    const found = TOKEN.exec(template)

    if (found === null) break

    text(template.slice(at, found.index))

    if (found[0] === '{{' || found[0] === '{!!') {
      const raw = found[0] === '{!!'
      const close = raw ? '!!}' : '}}'
      const end = template.indexOf(close, found.index + found[0].length)

      if (end === -1) {
        text(found[0])
        at = found.index + found[0].length
        continue
      }

      into().push({
        kind: 'echo',
        expression: template.slice(found.index + found[0].length, end).trim(),
        raw,
        source: template.slice(found.index, end + close.length),
      })
      at = end + close.length
      continue
    }

    const directive = found[1]!
    let after = found.index + found[0].length
    let argument: string | null = null

    if (['if', 'elseif', 'foreach', 'blocks'].includes(directive)) {
      const opened = /^\s*\(/.exec(template.slice(after))
      const close = opened === null ? -1 : balanced(template, after + opened[0].length - 1)

      if (close === -1) {
        // Not a directive after all — `@if` in a sentence, say. Left as the text it is.
        text(found[0])
        at = after
        continue
      }

      argument = template.slice(after + opened![0].length, close).trim()
      after = close + 1
    }

    const top = stack[stack.length - 1]

    switch (directive) {
      case 'if': {
        const node: Extract<Node, { kind: 'if' }> = {
          kind: 'if',
          branches: [{ test: argument, body: [] }],
        }

        into().push(node)
        stack.push(node)
        break
      }
      case 'elseif':
      case 'else':
        if (top?.kind !== 'if') {
          text(template.slice(found.index, after))
          break
        }

        top.branches.push({ test: directive === 'else' ? null : argument, body: [] })
        break
      case 'endif':
        if (top?.kind === 'if') stack.pop()
        else text(found[0])
        break
      case 'foreach': {
        const head = /^([\s\S]+?)\s+as\s+\$(\w+)(?:\s*=>\s*\$(\w+))?$/.exec(argument ?? '')

        if (head === null) {
          text(template.slice(found.index, after))
          break
        }

        const node: Extract<Node, { kind: 'foreach' }> = {
          kind: 'foreach',
          list: head[1]!,
          key: head[3] === undefined ? null : head[2]!,
          alias: head[3] ?? head[2]!,
          body: [],
        }

        into().push(node)
        stack.push(node)
        break
      }
      case 'endforeach':
        if (top?.kind === 'foreach') stack.pop()
        else text(found[0])
        break
      case 'blocks':
        into().push({ kind: 'blocks', argument: argument ?? '' })
        break
    }

    at = after
  }

  text(template.slice(at))

  return root
}

/** Where the parenthesis opened at `open` closes, minding quotes; -1 when it never does. */
function balanced(source: string, open: number): number {
  let depth = 0
  let quote: string | null = null

  for (let index = open; index < source.length; index++) {
    const char = source[index]!

    if (quote !== null) {
      if (char === '\\') index++
      else if (char === quote) quote = null
      continue
    }

    if (char === "'" || char === '"') quote = char
    else if (char === '(') depth++
    else if (char === ')' && --depth === 0) return index
  }

  return -1
}

/* -------------------------------------------------------------------------- rendering ----- */

function render(nodes: Node[], scope: Scope, children: Record<string, string>): string {
  let out = ''

  for (const node of nodes) {
    switch (node.kind) {
      case 'text':
        out += node.text
        break
      case 'echo':
        try {
          const value = show(evaluate(node.expression, scope))

          out += node.raw ? value : escape(value)
        } catch {
          out += node.source
        }
        break
      case 'if': {
        const branch = node.branches.find(
          (candidate) => candidate.test === null || truthy(attempt(candidate.test, scope)),
        )

        if (branch !== undefined) out += render(branch.body, scope, children)
        break
      }
      case 'foreach': {
        const list = attempt(node.list, scope)
        const entries: [unknown, unknown][] = Array.isArray(list)
          ? list.map((item, index) => [index, item])
          : list !== null && typeof list === 'object'
            ? Object.entries(list)
            : []

        entries.forEach(([key, item], index) => {
          const loop = {
            index,
            iteration: index + 1,
            count: entries.length,
            first: index === 0,
            last: index === entries.length - 1,
          }

          out += render(
            node.body,
            {
              ...scope,
              [node.alias]: item,
              ...(node.key === null ? {} : { [node.key]: key }),
              loop,
            },
            children,
          )
        })
        break
      }
      case 'blocks':
        out += children[show(attempt(node.argument, scope))] ?? ''
        break
    }
  }

  return out
}

/** A condition or a list nobody could read counts as nothing, the way an unset one would. */
function attempt(expression: string, scope: Scope): unknown {
  try {
    return evaluate(expression, scope)
  } catch {
    return undefined
  }
}

/* ------------------------------------------------------------------------ expressions ----- */

interface Token {
  type: 'var' | 'str' | 'num' | 'name' | 'op'
  value: string
}

const OPERATORS = [
  '===',
  '!==',
  '==',
  '!=',
  '<=',
  '>=',
  '&&',
  '||',
  '??',
  '?:',
  '=>',
  '->',
  '<',
  '>',
  '!',
  '?',
  ':',
  '(',
  ')',
  '[',
  ']',
  ',',
  '.',
  '+',
  '-',
  '*',
  '/',
  '%',
]

function lex(source: string): Token[] {
  const tokens: Token[] = []
  let at = 0

  while (at < source.length) {
    const rest = source.slice(at)
    const space = /^\s+/.exec(rest)

    if (space !== null) {
      at += space[0].length
      continue
    }

    const variable = /^\$(\w+)/.exec(rest)

    if (variable !== null) {
      tokens.push({ type: 'var', value: variable[1]! })
      at += variable[0].length
      continue
    }

    const number = /^\d+(\.\d+)?/.exec(rest)

    if (number !== null) {
      tokens.push({ type: 'num', value: number[0] })
      at += number[0].length
      continue
    }

    const name = /^[A-Za-z_]\w*/.exec(rest)

    if (name !== null) {
      tokens.push({ type: 'name', value: name[0] })
      at += name[0].length
      continue
    }

    if (rest[0] === "'" || rest[0] === '"') {
      const quote = rest[0]
      let value = ''
      let index = 1

      for (; index < rest.length && rest[index] !== quote; index++) {
        if (rest[index] === '\\' && index + 1 < rest.length) index++
        value += rest[index]
      }

      if (index >= rest.length) throw new Error('An unclosed string.')

      tokens.push({ type: 'str', value })
      at += index + 1
      continue
    }

    const operator = OPERATORS.find((candidate) => rest.startsWith(candidate))

    if (operator === undefined) throw new Error(`Cannot read "${rest}".`)

    tokens.push({ type: 'op', value: operator })
    at += operator.length
  }

  return tokens
}

export function evaluate(expression: string, scope: Scope): unknown {
  const tokens = lex(expression)
  let at = 0

  const peek = (value: string): boolean => tokens[at]?.type === 'op' && tokens[at]!.value === value

  const eat = (value: string): boolean => {
    if (!peek(value)) return false
    at++
    return true
  }

  const expect = (value: string): void => {
    if (!eat(value)) throw new Error(`Expected ${value}.`)
  }

  /* Lowest first: the ternaries, `??`, `||`, `&&`, equality, order, `+ - .`, `* / %`, unary. */
  const ternary = (): unknown => {
    let value = coalesce()

    for (;;) {
      if (eat('?:')) {
        const other = coalesce()
        value = truthy(value) ? value : other
      } else if (eat('?')) {
        const yes = ternary()
        expect(':')
        const no = ternary()
        value = truthy(value) ? yes : no
      } else {
        return value
      }
    }
  }

  const coalesce = (): unknown => {
    const value = or()

    if (!eat('??')) return value

    const other = coalesce()

    return value === null || value === undefined ? other : value
  }

  const or = (): unknown => {
    let value = and()

    while (eat('||')) {
      const other = and()
      value = truthy(value) || truthy(other)
    }

    return value
  }

  const and = (): unknown => {
    let value = equality()

    while (eat('&&')) {
      const other = equality()
      value = truthy(value) && truthy(other)
    }

    return value
  }

  const equality = (): unknown => {
    let value = order()

    for (;;) {
      if (eat('===')) value = same(value, order())
      else if (eat('!==')) value = !same(value, order())
      else if (eat('==')) value = loose(value, order())
      else if (eat('!=')) value = !loose(value, order())
      else return value
    }
  }

  const order = (): unknown => {
    let value = sum()

    for (;;) {
      if (eat('<=')) value = Number(value) <= Number(sum())
      else if (eat('>=')) value = Number(value) >= Number(sum())
      else if (eat('<')) value = Number(value) < Number(sum())
      else if (eat('>')) value = Number(value) > Number(sum())
      else return value
    }
  }

  const sum = (): unknown => {
    let value = product()

    for (;;) {
      if (eat('.')) value = show(value) + show(product())
      else if (eat('+')) value = Number(value) + Number(product())
      else if (eat('-')) value = Number(value) - Number(product())
      else return value
    }
  }

  const product = (): unknown => {
    let value = unary()

    for (;;) {
      if (eat('*')) value = Number(value) * Number(unary())
      else if (eat('/')) value = Number(value) / Number(unary())
      else if (eat('%')) value = Number(value) % Number(unary())
      else return value
    }
  }

  const unary = (): unknown => {
    if (eat('!')) return !truthy(unary())
    if (eat('-')) return -Number(unary())

    return postfix()
  }

  const postfix = (): unknown => {
    let value = primary()

    for (;;) {
      if (eat('[')) {
        const key = ternary()
        expect(']')
        value = localize(member(value, key))
      } else if (eat('->')) {
        // `$loop->first`: a property, which here is a key like any other.
        const name = tokens[at++]

        if (name?.type !== 'name') throw new Error('Expected a property.')

        value = localize(member(value, name.value))
      } else {
        return value
      }
    }
  }

  const list = (close: string): unknown[] => {
    const items: unknown[] = []

    while (!eat(close)) {
      items.push(ternary())
      if (!eat(',')) {
        expect(close)
        break
      }
    }

    return items
  }

  const primary = (): unknown => {
    const token = tokens[at++]

    if (token === undefined) throw new Error('Unexpected end.')

    switch (token.type) {
      case 'var':
        return localize(scope[token.value])
      case 'str':
        return token.value
      case 'num':
        return Number(token.value)
      case 'name': {
        const word = token.value.toLowerCase()

        if (word === 'true') return true
        if (word === 'false') return false
        if (word === 'null') return null

        const call = FUNCTIONS[token.value]

        if (call === undefined || !eat('(')) throw new Error(`Unknown ${token.value}.`)

        return call(...list(')'))
      }
      case 'op':
        if (token.value === '(') {
          const value = ternary()
          expect(')')
          return value
        }

        if (token.value === '[') return list(']')
    }

    throw new Error(`Unexpected ${token.value}.`)
  }

  const value = ternary()

  if (at < tokens.length) throw new Error('Trailing input.')

  return value
}

/** The functions a block template in this playground may call — the ones its templates do. */
const FUNCTIONS: Record<string, (...args: unknown[]) => unknown> = {
  e: (value) => escape(show(value)),
  nl2br: (value) => show(value).replace(/(\r\n|\n|\r)/g, '<br />$1'),
  mb_substr: (value, start, length) => {
    const letters = Array.from(show(value))
    const from = Number(start) < 0 ? Math.max(0, letters.length + Number(start)) : Number(start)
    const to =
      length === null || length === undefined
        ? letters.length
        : Number(length) < 0
          ? letters.length + Number(length)
          : from + Number(length)

    return letters.slice(from, to).join('')
  },
  mb_strtoupper: (value) => show(value).toUpperCase(),
  strtoupper: (value) => show(value).toUpperCase(),
  mb_strtolower: (value) => show(value).toLowerCase(),
  strtolower: (value) => show(value).toLowerCase(),
  trim: (value) => show(value).trim(),
  count: (value) =>
    Array.isArray(value)
      ? value.length
      : value !== null && typeof value === 'object'
        ? Object.keys(value).length
        : 0,
  in_array: (needle, haystack) =>
    Array.isArray(haystack) && haystack.some((item) => loose(item, needle)),
  implode: (glue, items) => (Array.isArray(items) ? items.map(show).join(show(glue)) : ''),
  explode: (glue, value) => show(value).split(show(glue)),
  str_repeat: (value, times) => show(value).repeat(Math.max(0, Math.floor(Number(times)) || 0)),
  max: (...values) => Math.max(...values.map(Number)),
  min: (...values) => Math.min(...values.map(Number)),
  round: (value) => Math.round(Number(value)),
  intval: (value) => Math.trunc(Number(value)) || 0,
  json_encode: (value) => JSON.stringify(value ?? null),
  isset: (...values) => values.every((value) => value !== null && value !== undefined),
  empty: (value) => !truthy(value),
}

/* ------------------------------------------------------------------------------ values ----- */

/**
 * A localized value read as the site reads it — one language of it, `ru` here, since that is what
 * the fixtures are written in and the preview is a page of the site rather than of the panel. On
 * a site the field type has done this before the template runs; here it happens on reading.
 */
function localize(value: unknown): unknown {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) return value

  const entries = Object.entries(value as Record<string, unknown>)
  const languages =
    entries.length > 0 &&
    entries.every(
      ([key, said]) =>
        /^[a-z]{2}(-[A-Za-z]{2,4})?$/.test(key) &&
        (typeof said === 'string' || said === null || said === undefined),
    )

  if (!languages) return value

  const map = value as Record<string, unknown>

  return map.ru ?? map.en ?? entries[0]![1] ?? ''
}

function member(value: unknown, key: unknown): unknown {
  if (Array.isArray(value)) return value[Number(key)]
  if (value !== null && typeof value === 'object') {
    return (value as Record<string, unknown>)[String(key)]
  }

  return undefined
}

/** PHP's truthiness, arrays included: an empty one is false, and so is `'0'`. */
function truthy(value: unknown): boolean {
  if (value === null || value === undefined || value === false) return false
  if (value === 0 || value === '' || value === '0') return false
  if (Array.isArray(value)) return value.length > 0
  if (typeof value === 'object') return Object.keys(value).length > 0

  return true
}

function same(a: unknown, b: unknown): boolean {
  if ((a === null || a === undefined) && (b === null || b === undefined)) return true

  return a === b
}

function loose(a: unknown, b: unknown): boolean {
  if (same(a, b)) return true
  if (typeof a === 'object' || typeof b === 'object') return false

  return String(a ?? '') === String(b ?? '')
}

/** What a value prints as: nothing for null and false, `1` for true, a language of a map. */
export function show(value: unknown): string {
  if (value === null || value === undefined || value === false) return ''
  if (value === true) return '1'

  if (typeof value === 'object' && !Array.isArray(value)) {
    const map = value as Record<string, unknown>

    return show(map.ru ?? map.en ?? Object.values(map)[0])
  }

  return String(value)
}

export function escape(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}
