/**
 * The area formula of an option (§7.1): numbers, the keys of the size fields, `+ - * /`, a unary
 * minus and brackets — and nothing else.
 *
 * A tokenizer and a recursive descent, never `eval` or `Function`: the same grammar as
 * `Engine\Formula` on the server, held to it by the parity cases. A tree is a tuple: `['n', 1.5]`
 * a number, `['k', 'w']` a key, `['neg', tree]` a minus, `['+', left, right]` an operation.
 *
 *     const tree = parseFormula('(w+d)*h/1e6')
 *     formulaKeys(tree)                                  // ['w', 'd', 'h']
 *     evaluateFormula(tree, { w: 900, d: 900, h: 2000 }) // 3.6
 */

export type FormulaTree =
  | ['n', number]
  | ['k', string]
  | ['neg', FormulaTree]
  | ['+' | '-' | '*' | '/', FormulaTree, FormulaTree]

/** Longer than any area anybody writes; a pasted novel is refused, not parsed. */
export const FORMULA_MAX_LENGTH = 500

/** Brackets inside brackets; deeper than this is not a formula a person wrote. */
const MAX_DEPTH = 32

/** Where a formula stops making sense, and why — the `:position` and `:reason` of `area.syntax`. */
export class FormulaError extends Error {
  constructor(
    readonly position: number,
    readonly reason: string,
  ) {
    super(`${reason} at ${position}`)
    this.name = 'FormulaError'
  }
}

type Token = [kind: 'op' | 'n' | 'k', text: string, at: number]

const NUMBER = /(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/y
const KEY = /[a-z_][a-z0-9_]*/y

function tokenize(source: string): Token[] {
  const tokens: Token[] = []
  let at = 0

  while (at < source.length) {
    const char = source[at]!

    if (char === ' ' || char === '\t' || char === '\n' || char === '\r') {
      at++
      continue
    }

    if ('+-*/()'.includes(char)) {
      tokens.push(['op', char, at])
      at++
      continue
    }

    NUMBER.lastIndex = at
    const number = NUMBER.exec(source)

    if (number) {
      tokens.push(['n', number[0], at])
      at += number[0].length
      continue
    }

    KEY.lastIndex = at
    const key = KEY.exec(source)

    if (key) {
      tokens.push(['k', key[0], at])
      at += key[0].length
      continue
    }

    throw new FormulaError(at, `unexpected «${[...source.slice(at)][0]}»`)
  }

  return tokens
}

export function parseFormula(source: string): FormulaTree {
  if (source.length > FORMULA_MAX_LENGTH) {
    throw new FormulaError(FORMULA_MAX_LENGTH, `longer than ${FORMULA_MAX_LENGTH} characters`)
  }

  const tokens = tokenize(source)
  let at = 0
  let depth = 0

  if (tokens.length === 0) throw new FormulaError(0, 'empty')

  const peek = (operator: string): boolean => {
    const token = tokens[at]

    return token !== undefined && token[0] === 'op' && token[1] === operator
  }

  const expression = (): FormulaTree => {
    let tree = term()

    while (peek('+') || peek('-')) {
      const operator = tokens[at++]![1] as '+' | '-'
      tree = [operator, tree, term()]
    }

    return tree
  }

  const term = (): FormulaTree => {
    let tree = factor()

    while (peek('*') || peek('/')) {
      const operator = tokens[at++]![1] as '*' | '/'
      tree = [operator, tree, factor()]
    }

    return tree
  }

  const factor = (): FormulaTree => {
    const token = tokens[at]

    if (token === undefined) throw new FormulaError(source.length, 'ends too early')
    if (++depth > MAX_DEPTH) throw new FormulaError(token[2], 'nested too deep')

    try {
      if (token[0] === 'n') {
        at++

        return ['n', Number(token[1])]
      }

      if (token[0] === 'k') {
        at++

        return ['k', token[1]]
      }

      if (token[1] === '-') {
        at++

        return ['neg', factor()]
      }

      if (token[1] === '(') {
        at++
        const tree = expression()

        if (!peek(')')) {
          throw new FormulaError(tokens[at]?.[2] ?? source.length, 'a bracket is not closed')
        }

        at++

        return tree
      }

      throw new FormulaError(token[2], `unexpected «${token[1]}»`)
    } finally {
      depth--
    }
  }

  const tree = expression()

  if (at < tokens.length) {
    const token = tokens[at]!

    throw new FormulaError(token[2], `unexpected «${token[1]}»`)
  }

  return tree
}

/** The keys a formula reads, each once, in the order they first appear. */
export function formulaKeys(tree: FormulaTree): string[] {
  switch (tree[0]) {
    case 'n':
      return []
    case 'k':
      return [tree[1]]
    case 'neg':
      return formulaKeys(tree[1])
    default:
      return [...new Set([...formulaKeys(tree[1]), ...formulaKeys(tree[2])])]
  }
}

/**
 * The value of a formula. A key with no value — a hidden field — is 0; dividing by 0 gives 0,
 * not infinity: the visitor typed something odd, the page does not break (§7.1).
 */
export function evaluateFormula(tree: FormulaTree, values: Record<string, number>): number {
  switch (tree[0]) {
    case 'n':
      return tree[1]
    case 'k':
      // Own keys only: `constructor` is a word a formula may contain, not a function to call.
      return Object.hasOwn(values, tree[1]) ? (values[tree[1]] ?? 0) : 0
    case 'neg':
      return -evaluateFormula(tree[1], values)
  }

  const left = evaluateFormula(tree[1], values)
  const right = evaluateFormula(tree[2], values)

  switch (tree[0]) {
    case '+':
      return left + right
    case '-':
      return left - right
    case '*':
      return left * right
    default:
      return right === 0 ? 0 : left / right
  }
}
