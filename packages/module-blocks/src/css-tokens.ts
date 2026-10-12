/**
 * CSS knows only tokens (THEMES §8) — the theme engine's `Checks\CssCheck`, rule for rule, for
 * the editor's live lints. Not a parser: a walk over braces and semicolons that knows strings,
 * comments and parentheses.
 *
 * Unlike the engine it has no `media-width`: the editor's `media-query` says it of any `@media`.
 */

export type CssProblem = 'literal-color' | 'literal-font' | 'unknown-token' | 'token-fallback'

export interface CssFinding {
  problem: CssProblem
  line: number
  subject: string
}

/** CSS Color 4 — every named colour a browser paints. */
const NAMED = [
  'aliceblue',
  'antiquewhite',
  'aqua',
  'aquamarine',
  'azure',
  'beige',
  'bisque',
  'black',
  'blanchedalmond',
  'blue',
  'blueviolet',
  'brown',
  'burlywood',
  'cadetblue',
  'chartreuse',
  'chocolate',
  'coral',
  'cornflowerblue',
  'cornsilk',
  'crimson',
  'cyan',
  'darkblue',
  'darkcyan',
  'darkgoldenrod',
  'darkgray',
  'darkgreen',
  'darkgrey',
  'darkkhaki',
  'darkmagenta',
  'darkolivegreen',
  'darkorange',
  'darkorchid',
  'darkred',
  'darksalmon',
  'darkseagreen',
  'darkslateblue',
  'darkslategray',
  'darkslategrey',
  'darkturquoise',
  'darkviolet',
  'deeppink',
  'deepskyblue',
  'dimgray',
  'dimgrey',
  'dodgerblue',
  'firebrick',
  'floralwhite',
  'forestgreen',
  'fuchsia',
  'gainsboro',
  'ghostwhite',
  'gold',
  'goldenrod',
  'gray',
  'green',
  'greenyellow',
  'grey',
  'honeydew',
  'hotpink',
  'indianred',
  'indigo',
  'ivory',
  'khaki',
  'lavender',
  'lavenderblush',
  'lawngreen',
  'lemonchiffon',
  'lightblue',
  'lightcoral',
  'lightcyan',
  'lightgoldenrodyellow',
  'lightgray',
  'lightgreen',
  'lightgrey',
  'lightpink',
  'lightsalmon',
  'lightseagreen',
  'lightskyblue',
  'lightslategray',
  'lightslategrey',
  'lightsteelblue',
  'lightyellow',
  'lime',
  'limegreen',
  'linen',
  'magenta',
  'maroon',
  'mediumaquamarine',
  'mediumblue',
  'mediumorchid',
  'mediumpurple',
  'mediumseagreen',
  'mediumslateblue',
  'mediumspringgreen',
  'mediumturquoise',
  'mediumvioletred',
  'midnightblue',
  'mintcream',
  'mistyrose',
  'moccasin',
  'navajowhite',
  'navy',
  'oldlace',
  'olive',
  'olivedrab',
  'orange',
  'orangered',
  'orchid',
  'palegoldenrod',
  'palegreen',
  'paleturquoise',
  'palevioletred',
  'papayawhip',
  'peachpuff',
  'peru',
  'pink',
  'plum',
  'powderblue',
  'purple',
  'rebeccapurple',
  'red',
  'rosybrown',
  'royalblue',
  'saddlebrown',
  'salmon',
  'sandybrown',
  'seagreen',
  'seashell',
  'sienna',
  'silver',
  'skyblue',
  'slateblue',
  'slategray',
  'slategrey',
  'snow',
  'springgreen',
  'steelblue',
  'tan',
  'teal',
  'thistle',
  'tomato',
  'turquoise',
  'violet',
  'wheat',
  'white',
  'whitesmoke',
  'yellow',
  'yellowgreen',
].join('|')

const GENERIC_FONTS = [
  'serif',
  'sans-serif',
  'monospace',
  'cursive',
  'fantasy',
  'system-ui',
  'ui-serif',
  'ui-sans-serif',
  'ui-monospace',
  'ui-rounded',
  'math',
  'emoji',
  'fangsong',
]

const WIDE = ['inherit', 'initial', 'unset', 'revert', 'revert-layer']

const FONT_KEYWORDS = [
  'normal',
  'italic',
  'oblique',
  'bold',
  'bolder',
  'lighter',
  'small-caps',
  'ultra-condensed',
  'extra-condensed',
  'condensed',
  'semi-condensed',
  'semi-expanded',
  'expanded',
  'extra-expanded',
  'ultra-expanded',
  'xx-small',
  'x-small',
  'small',
  'medium',
  'large',
  'x-large',
  'xx-large',
  'xxx-large',
  'smaller',
  'larger',
  'caption',
  'icon',
  'menu',
  'message-box',
  'small-caption',
  'status-bar',
]

/** Properties whose values are names, not paint: an animation called `fade`, a grid area `aside`. */
const NOT_PAINT =
  /^(?:font|font-[a-z-]+|transition(?:-[a-z-]+)?|animation(?:-[a-z-]+)?|grid(?:-[a-z-]+)?|will-change|content|counter-[a-z-]+|quotes|container(?:-[a-z-]+)?|view-transition-name|list-style-type)$/

/**
 * @param tokens The chain's vocabulary, names without `--site-`.
 */
export function cssFindings(css: string, tokens: Iterable<string>): CssFinding[] {
  const vocabulary = new Set(tokens)
  const source = css.replace(/\/\*[\s\S]*?\*\//g, (comment) => comment.replace(/[^\n]/g, ' '))
  const findings: CssFinding[] = []

  for (const [text, offset, inside] of declarations(source)) {
    if (/^@(?:font-face|property)\b/i.test(inside)) continue

    const colon = text.indexOf(':')
    const property = text.slice(0, colon).trim().toLowerCase()
    const start = offset + colon + 1

    for (const [problem, at, subject] of declaration(property, text.slice(colon + 1), vocabulary)) {
      findings.push({ problem, line: lineAt(source, start + at), subject })
    }
  }

  return findings
}

function declaration(
  property: string,
  value: string,
  vocabulary: Set<string>,
): [CssProblem, number, string][] {
  const found: [CssProblem, number, string][] = []

  for (const reference of value.matchAll(/var\(\s*--([A-Za-z0-9_-]+)\s*([,)])/g)) {
    const name = reference[1]!

    // A block's own variable — `var(--team-columns, 3)` — keeps its default.
    if (!name.startsWith('site-')) continue

    if (!vocabulary.has(name.slice(5))) found.push(['unknown-token', reference.index, `--${name}`])
    if (reference[2] === ',') found.push(['token-fallback', reference.index, `var(--${name}, …)`])
  }

  const paint = value.replace(
    /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|url\([^)]*\)|--[A-Za-z0-9_-]+/gi,
    (match) => ' '.repeat(match.length),
  )

  for (const color of paint.matchAll(
    /(?<![\w&-])#[0-9a-f]{3,8}(?![\w-])|(?<![\w-])(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(/gi,
  )) {
    found.push(['literal-color', color.index, color[0].endsWith('(') ? `${color[0]}…)` : color[0]])
  }

  if (property.startsWith('--')) {
    const whole = new RegExp(`^(\\s*)(${NAMED})\\s*(?:!important\\s*)?$`, 'i').exec(paint)
    if (whole) found.push(['literal-color', whole[1]!.length, whole[2]!])
  } else if (!NOT_PAINT.test(property)) {
    for (const named of paint.matchAll(new RegExp(`(?<![\\w-])(${NAMED})(?![\\w-])`, 'gi'))) {
      found.push(['literal-color', named.index, named[1]!])
    }
  }

  if (property === 'font-family' || property === 'font') {
    for (const [family, at] of families(property, value)) found.push(['literal-font', at, family])
  }

  return found.sort((a, b) => a[1] - b[1])
}

function families(property: string, value: string): [string, number][] {
  const bare = value.replace(/var\([^()]*(?:\([^()]*\)[^()]*)*\)|!important/gi, (match) =>
    ' '.repeat(match.length),
  )
  let allowed = [...GENERIC_FONTS, ...WIDE]
  const found: [string, number][] = []

  if (property === 'font-family') {
    for (const item of bare.matchAll(/[^,]+/g)) {
      const family = item[0].trim()

      if (family !== '' && !allowed.includes(family.toLowerCase())) {
        found.push([family, item.index + item[0].indexOf(family[0]!)])
      }
    }

    return found
  }

  // The shorthand: keywords and families share the line; `Open Sans` is two unknown words in a row.
  allowed = [...allowed, ...FONT_KEYWORDS]
  let end: number | null = null

  for (const word of bare.matchAll(/"[^"]*"|'[^']*'|(?<![\w.%#-])[A-Za-z_-][A-Za-z0-9_-]*/g)) {
    const quoted = word[0][0] === '"' || word[0][0] === "'"

    if (!quoted && allowed.includes(word[0].toLowerCase())) {
      end = null
      continue
    }

    if (!quoted && end !== null && bare.slice(end, word.index).trim() === '') {
      found[found.length - 1]![0] += ` ${word[0]}`
    } else {
      found.push([word[0], word.index])
    }

    end = quoted ? null : word.index + word[0].length
  }

  return found
}

/** Declarations — what a `;` or a `}` closes inside a block — with their offset and the at-rule they stand in. */
function declarations(source: string): [string, number, string][] {
  const found: [string, number, string][] = []
  const stack: string[] = []
  let start = 0
  let quote: string | null = null
  let parens = 0

  for (let i = 0; i < source.length; i++) {
    const char = source[i]!

    if (quote !== null) {
      if (char === '\\') i++
      else if (char === quote) quote = null
      continue
    }

    if (char === '"' || char === "'") quote = char
    else if (char === '(') parens++
    else if (char === ')') parens = Math.max(0, parens - 1)
    else if (parens === 0 && (char === '{' || char === ';' || char === '}')) {
      const raw = source.slice(start, i)
      const text = raw.trim()
      const offset = start + (raw.length - raw.trimStart().length)

      if (char === '{') {
        stack.push(text)
      } else if (stack.length > 0 && text !== '' && !text.startsWith('@') && text.includes(':')) {
        found.push([text, offset, [...stack].reverse().find((p) => p.startsWith('@')) ?? ''])
      }

      if (char === '}') stack.pop()

      start = i + 1
    }
  }

  return found
}

function lineAt(source: string, offset: number): number {
  return source.slice(0, Math.max(0, offset)).split('\n').length
}
