// The pure half of critical.mjs: no browser, no file, so a test can run it on its own.

// A selector list into its selectors, cut only at the commas of its top level: a comma inside
// `:has(a, b)`, `:is()`, `:not()`, `[attr="a,b"]` or after a backslash belongs to its selector.
// It runs in the page too (critical.mjs hands its source over), so it uses nothing from outside.
export function splitSelectors(text) {
  const parts = []
  let depth = 0
  let quote = ''
  let start = 0
  for (let i = 0; i < text.length; i++) {
    const char = text[i]
    if (char === '\\') {
      i++
    } else if (quote) {
      if (char === quote) quote = ''
    } else if (char === '"' || char === "'") {
      quote = char
    } else if (char === '(' || char === '[') {
      depth++
    } else if (char === ')' || char === ']') {
      depth = Math.max(0, depth - 1)
    } else if (char === ',' && depth === 0) {
      parts.push(text.slice(start, i))
      start = i + 1
    }
  }
  parts.push(text.slice(start))
  return parts.map((part) => part.trim()).filter(Boolean)
}

// One rule, whichever stylesheet it came from: the same text under the same wrappers is the same
// rule. The bundle of a page's blocks repeats the rules of a block every page with it has.
export const ruleKey = (rule) => `${rule.wrappers.join('\u0001')}\u0000${rule.text}`

// The rules of every screen, in an order that keeps the cascade of each of them.
//
// `screens` — for every page on every screen, its kept rules in the order the page applies them
// (its stylesheets in the order it links them, each in its own order). Laying the stylesheets
// one after another would not do: the bundle of another page's blocks would come after this
// page's `.b-text--page` with its own `.b-text`, and the modifier would lose. So the rules are
// merged instead: a rule comes after every rule it follows on some screen. Two screens that
// order a pair of rules the other way round are a conflict; the rule seen first goes first then,
// and `conflicts` counts them.
export function mergeRules(screens) {
  const nodes = new Map()
  const sequences = screens.map((rules) => {
    // A rule a screen repeats counts where it is last: the earlier copy changes nothing.
    const last = new Map()
    rules.forEach((rule, at) => last.set(ruleKey(rule), at))
    return rules
      .filter((rule, at) => last.get(ruleKey(rule)) === at)
      .map((rule) => {
        const key = ruleKey(rule)
        if (!nodes.has(key)) nodes.set(key, { rule, order: nodes.size, next: new Set(), before: 0 })
        return nodes.get(key)
      })
  })
  for (const sequence of sequences) {
    for (let i = 1; i < sequence.length; i++) {
      const [from, to] = [sequence[i - 1], sequence[i]]
      if (!from.next.has(to)) {
        from.next.add(to)
        to.before++
      }
    }
  }

  const left = [...nodes.values()]
  const rules = []
  let conflicts = 0
  while (left.length) {
    // The rule seen first of those whose every predecessor is out; none — a conflict.
    let at = left.findIndex((node) => node.before <= 0)
    if (at === -1) {
      at = 0
      conflicts++
    }
    const [node] = left.splice(at, 1)
    rules.push(node.rule)
    for (const next of node.next) next.before--
  }
  return { rules, conflicts }
}

// The rules as one stylesheet: consecutive rules of one @media share it, the wrappers are closed
// only when they change.
export function serializeRules(rules) {
  let css = ''
  let open = []
  for (const rule of rules) {
    let common = 0
    while (
      common < open.length &&
      common < rule.wrappers.length &&
      open[common] === rule.wrappers[common]
    )
      common++
    css += '}'.repeat(open.length - common)
    for (const wrapper of rule.wrappers.slice(common)) css += `${wrapper}{`
    open = rule.wrappers
    css += rule.text
  }
  return css + '}'.repeat(open.length)
}
