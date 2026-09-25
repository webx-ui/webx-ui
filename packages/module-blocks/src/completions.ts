import {
  autocompletion,
  pickedCompletion,
  snippetCompletion,
  startCompletion,
  type Completion,
  type CompletionContext,
  type CompletionResult,
  type CompletionSource,
} from '@codemirror/autocomplete'
import { EditorState, type Extension } from '@codemirror/state'
import type { EditorView } from '@codemirror/view'
import type { ScreenNode, TypeRegistry } from '@webx-ui/schema'

/**
 * What the editor of a block type offers as you type, one source per file. Each reads the
 * other files of the same type — the template's fields come from the schema, its classes from
 * the styles, the styles' classes from the template — so the sources take getters, not
 * values: the editor is built once and the files change under it.
 *
 * Registered as language data rather than as an `override`, so that what the language
 * already offers (CSS properties, HTML tags) stays in the list beside ours.
 */
export function completions(source: CompletionSource): Extension {
  return [
    autocompletion({ icons: false }),
    EditorState.languageData.of(() => [{ autocomplete: source }]),
  ]
}

/* ---------------------------------------------------------------------------------------- */
/* Template                                                                                  */
/* ---------------------------------------------------------------------------------------- */

export interface TemplateSources {
  schema: () => ScreenNode[]
  styles: () => string
  /** The types a tag can call, components among them: what `type="` offers. */
  types?: () => TagTarget[]
  /**
   * The schema of a type, for the attributes and slots of a tag that calls it. May answer later:
   * the list of types comes without content, and the one schema is asked for when it is needed.
   */
  schemaOf?: (slug: string) => ScreenNode[] | null | Promise<ScreenNode[] | null>
}

/** A type as a tag names it. */
export interface TagTarget {
  slug: string
  title: string
  kind: 'block' | 'component'
  /** The module's view, for a declared place: the tag that calls it should carry it. */
  fallback?: string | null
}

/** What the renderer hands a template besides its fields (see `lint.ts`, `GIVEN`). */
const GIVEN: [string, string][] = [
  ['block', 'key, type, version, depth, value()'],
  ['entity', 'the page or article the block stands on'],
]

const BLOCK_MEMBERS = ['key', 'type', 'version', 'depth']

/** The keys of a picked file: `wx-media` holds them, the renderer adds `url`. */
const MEDIA_KEYS = ['url', 'alt', 'title', 'path']

/** One item of `wx-gallery` or `wx-files`: a file plus what a list of them is printed with. */
const ITEM_KEYS = [...MEDIA_KEYS, 'thumb', 'name', 'extension', 'mime', 'size', 'width', 'height']

const MEDIA_TYPES = ['wx-media']
const LIST_TYPES = ['wx-gallery', 'wx-files']

/**
 * `wx-collection`, as the renderer hands it over: the records and the filter's groups. What else
 * a record holds is its source's business (`question`, `answer` for the FAQ); every source gives
 * these three.
 */
const COLLECTION_KEYS = ['items', 'groups', 'filter']
const COLLECTION_PARTS: Record<string, string[]> = {
  items: ['id', 'anchor', 'categories'],
  groups: ['id', 'title', 'items'],
}

/**
 * What a record of the sources the modules of this repository bring holds beyond the three every
 * source gives — the card each one's `Cards` builds. A source a site registers itself offers the
 * three only.
 */
const SOURCE_ITEMS: Record<string, string[]> = {
  faq: ['question', 'answer'],
  services: ['title', 'url', 'lead', 'cover', 'fields'],
  reviews: [
    'name',
    'initials',
    'job_title',
    'text',
    'rating',
    'date',
    'profile',
    'photo',
    'fields',
  ],
  recipes: [
    'title',
    'url',
    'lead',
    'cover',
    'gallery',
    'minutes',
    'servings',
    'nutrients',
    'fields',
  ],
}

export function templateCompletions({
  schema,
  styles,
  types = () => [],
  schemaOf = () => null,
}: TemplateSources): CompletionSource {
  return (context) => {
    const before = context.state.sliceDoc(Math.max(0, context.pos - 200), context.pos)

    // The value of `type` first: inside its quotes nothing else applies.
    const called = /<x-webx-block\b[^<>]*?\btype\s*=\s*(["'])([\w-]*)$/.exec(before)
    if (called) return tagTypes(context, called[1]!, called[2]!, types())

    const member = /\$block->(\w*)$/.exec(before)
    if (member) return blockMembers(context, member[1]!)

    const key = /\$(\w+)\[\s*(['"]?)(\w*)$/.exec(before)
    if (key) return arrayKeys(context, key[1]!, key[2]!, key[3]!, schema())

    const variable = /\$(\w*)$/.exec(before)
    if (variable) {
      return {
        from: context.pos - variable[0].length,
        options: variables(schema(), context.state.doc.toString()),
        validFor: /^\$\w*$/,
      }
    }

    // Straight after `{{` there is nothing else a template writes, so the fields open there
    // without waiting for the `$` (the one exception, a call like `{{ now() }}`, just types on).
    const echo = /(\{\{|\{!!)(\s*)(\w*)$/.exec(before)
    if (echo) return echoed(context, echo[1]!, echo[2]!, echo[3]!, schema())

    const tag = openTag(before)
    if (tag !== null) return tagAttributes(context, tag, types(), schemaOf)

    const element = /<(x-[\w:-]*)$/.exec(before)
    if (element) {
      const text = context.state.sliceDoc(0, context.pos - element[0].length)

      return tagElements(context, element[1]!, enclosingType(text), schemaOf)
    }

    const attribute = /\bclass\s*=\s*"([^"]*)$/.exec(before)
    if (attribute) return classNames(context, attribute[1]!, styles())

    const directive = /(?:^|[\s>(])@(\w*)$/.exec(before)
    if (directive) {
      return {
        from: context.pos - directive[1]!.length - 1,
        options: directives(schema()),
        validFor: /^@\w*$/,
      }
    }

    return null
  }
}

/* ---------------------------------------------------------------------------------------- */
/* <x-webx-block>                                                                            */
/* ---------------------------------------------------------------------------------------- */

/**
 * `type="` of the tag: every type there is, the components first — calling one is what the tag
 * is for, calling a block (a CTA with its values written in) is the rarer case (§2, decision 2).
 */
function tagTypes(
  context: CompletionContext,
  quote: string,
  word: string,
  targets: TagTarget[],
): CompletionResult | null {
  if (targets.length === 0) return null

  return {
    from: context.pos - word.length,
    options: targets.map((target, index) => ({
      label: target.slug,
      detail: target.kind,
      info: target.title,
      type: 'type',
      boost: (target.kind === 'component' ? 50 : 0) - index / 100,
      apply: (view, completion, from, to) => {
        const rest = /^[\w-]*/.exec(view.state.sliceDoc(to, to + 64))![0]
        const end = to + rest.length
        const closed = view.state.sliceDoc(end, end + 1) === quote
        const insert = `${target.slug}${closed ? '' : quote}`

        view.dispatch({
          changes: { from, to: end, insert },
          // Past the closing quote: the attributes come next.
          selection: { anchor: from + insert.length + (closed ? 1 : 0) },
          annotations: pickedCompletion.of(completion),
          userEvent: 'input.complete',
        })
      },
    })),
    validFor: /^[\w-]*$/,
  }
}

interface OpenTag {
  /** The attributes written so far. */
  attributes: string
  /** The type, once it is written among them. */
  type: string | null
  /** `:` typed before the name. */
  bound: boolean
  word: string
}

/** The caret stands between the attributes of an `<x-webx-block` that is still open. */
export function openTag(before: string): OpenTag | null {
  const start = before.lastIndexOf('<x-webx-block')
  if (start === -1) return null

  const inside = before.slice(start + '<x-webx-block'.length)
  if (inside.includes('>') || !/^\s/.test(inside)) return null

  // An odd number of quotes: the caret is inside a value, and names do not go there.
  const odd = (quote: string): boolean => (inside.split(quote).length - 1) % 2 === 1
  if (odd('"') || odd("'")) return null

  const name = /\s(:?)([\w-]*)$/.exec(inside)
  if (!name) return null

  const type = /\btype\s*=\s*["']([\w-]+)["']/.exec(inside)

  return { attributes: inside, type: type?.[1] ?? null, bound: name[1] === ':', word: name[2]! }
}

/** Attribute names: the type's inputs by its schema, `:` for a structure, then the reserved two. */
function tagAttributes(
  context: CompletionContext,
  tag: OpenTag,
  targets: TagTarget[],
  schemaOf: NonNullable<TemplateSources['schemaOf']>,
): CompletionResult | null | Promise<CompletionResult | null> {
  const present = new Set([...tag.attributes.matchAll(/:?([\w-]+)\s*=/g)].map((match) => match[1]!))
  const from = context.pos - tag.word.length - (tag.bound ? 1 : 0)

  const reserved = (): Completion[] => {
    const options: Completion[] = []

    if (!present.has('type')) options.push(attribute('type', false, 'reserved', null, 60))

    if (!present.has('fallback') && tag.type !== null) {
      const declared = targets.find((target) => target.slug === tag.type)?.fallback ?? null

      options.push(attribute('fallback', false, 'reserved', declared, -10, declared))
    }

    return options
  }

  const answer = (nodes: ScreenNode[] | null): CompletionResult | null => {
    const inputs = fields(nodes ?? [])
      .filter((node) => node.type !== 'wx-slot' && node.type !== 'wx-blocks')
      .filter((node) => !present.has(node.id))
      .map((node, index) =>
        attribute(node.id, node.type === 'wx-data' || tag.bound, node.type, node.label, 40 - index),
      )
    const options = [...inputs, ...reserved()]

    // A space after the tag's name is not yet a request: the list opens on a letter or a colon.
    if (options.length === 0 || (!context.explicit && tag.word === '' && !tag.bound)) return null

    return { from, options, validFor: /^:?[\w-]*$/ }
  }

  if (tag.type === null) return answer(null)

  const nodes = schemaOf(tag.type)

  return nodes instanceof Promise ? nodes.then(answer, () => answer(null)) : answer(nodes)
}

/** `name=""` or `:name="$"`, with the caret before the closing quote. */
function attribute(
  name: string,
  bound: boolean,
  detail: string,
  info: string | null | undefined,
  boost: number,
  value: string | null = null,
): Completion {
  const label = `${bound ? ':' : ''}${name}`

  return {
    label,
    detail,
    info: info ?? undefined,
    type: 'property',
    boost,
    apply: (view, completion, from, to) => {
      const insert = `${label}="${value ?? (bound ? '$' : '')}"`

      view.dispatch({
        changes: { from, to, insert },
        selection: { anchor: from + insert.length - 1 },
        annotations: pickedCompletion.of(completion),
        userEvent: 'input.complete',
      })
    },
  }
}

/**
 * After `<x-`: the tag itself, and inside a tag's body the named slots its type declares, each
 * written open and closed with the caret between.
 */
function tagElements(
  context: CompletionContext,
  word: string,
  type: string | null,
  schemaOf: NonNullable<TemplateSources['schemaOf']>,
): CompletionResult | Promise<CompletionResult> {
  const from = context.pos - word.length

  const answer = (nodes: ScreenNode[] | null): CompletionResult => {
    const options: Completion[] = [
      snippetCompletion('x-webx-block type="${type}" />', {
        label: 'x-webx-block',
        detail: 'call a block',
        type: 'keyword',
      }),
    ]

    for (const node of fields(nodes ?? []).filter((field) => field.type === 'wx-slot')) {
      options.push({
        label: `x-slot:${node.id}`,
        detail: 'slot',
        info: node.label,
        type: 'keyword',
        boost: 20,
        apply: (view, completion, start, end) => {
          const open = `x-slot:${node.id}>`

          view.dispatch({
            changes: { from: start, to: end, insert: `${open}</x-slot:${node.id}>` },
            selection: { anchor: start + open.length },
            annotations: pickedCompletion.of(completion),
            userEvent: 'input.complete',
          })
        },
      })
    }

    return { from, options, validFor: /^x-[\w:-]*$/ }
  }

  if (type === null) return answer(null)

  const nodes = schemaOf(type)

  return nodes instanceof Promise ? nodes.then(answer, () => answer(null)) : answer(nodes)
}

/** The type of the `<x-webx-block>` whose body the end of the text stands in, if any. */
export function enclosingType(text: string): string | null {
  const stack: (string | null)[] = []

  for (const match of text.matchAll(
    /<x-webx-block\b((?:[^>"']|"[^"]*"|'[^']*')*?)(\/?)>|<\/x-webx-block\s*>/g,
  )) {
    if (match[0].startsWith('</')) stack.pop()
    else if (match[2] !== '/') {
      stack.push(/\btype\s*=\s*["']([\w-]+)["']/.exec(match[1] ?? '')?.[1] ?? null)
    }
  }

  return stack.at(-1) ?? null
}

/** Every field a template may print: the schema's, in its order, then what is always there. */
function variables(schema: ScreenNode[], template: string): Completion[] {
  const options: Completion[] = printable(schema).map((node, index) => ({
    label: `$${node.id}`,
    detail: node.type,
    info: node.label,
    type: 'variable',
    boost: 50 - index,
  }))

  for (const name of loopVariables(template).keys()) {
    options.push({ label: `$${name}`, detail: 'loop', type: 'variable' })
  }

  for (const [name, info] of GIVEN) {
    options.push({ label: `$${name}`, detail: 'given', info, type: 'variable', boost: -10 })
  }

  // Any type can be called with the tag, and the tag's body arrives as this, empty when there
  // was none: the one given a component is written around.
  options.push({ label: '$slot', detail: 'given', info: 'what the calling tag held', boost: -15 })

  options.push({ label: '$loop', detail: 'given', info: 'inside @foreach', boost: -20 })

  return options
}

function echoed(
  context: CompletionContext,
  opener: string,
  space: string,
  word: string,
  schema: ScreenNode[],
): CompletionResult | null {
  const options = printable(schema)
  if (options.length === 0) return null

  const closer = opener === '{{' ? '}}' : '!!}'

  return {
    from: context.pos - word.length,
    options: options.map((node, index) => ({
      label: node.id,
      displayLabel: `$${node.id}`,
      detail: node.type,
      info: node.label,
      type: 'variable',
      boost: 50 - index,
      apply: (view, completion, from, to) => {
        const rest = view.state.sliceDoc(to, to + 8)
        const closed = new RegExp(`^\\s*${escape(closer)}`).exec(rest)
        // `closeBrackets` has usually typed the `}}` already; when it has not, write it.
        const tail = closed ? (/^\s/.test(rest) ? '' : ' ') : ` ${closer}`
        const insert = `${space === '' ? ' ' : ''}$${node.id}${tail}`

        view.dispatch({
          changes: { from, to, insert },
          // Past the closing braces: the echo is finished, the next thing is the markup.
          selection: { anchor: from + insert.length + (closed ? closed[0].length : 0) },
          annotations: pickedCompletion.of(completion),
          userEvent: 'input.complete',
        })
      },
    })),
    validFor: /^\w*$/,
  }
}

function blockMembers(context: CompletionContext, word: string): CompletionResult {
  return {
    from: context.pos - word.length,
    options: [
      ...BLOCK_MEMBERS.map((label) => ({ label, type: 'property' })),
      snippetCompletion("value('${name}', ${default})", {
        label: 'value',
        detail: "('name', default)",
        type: 'method',
      }),
    ],
    validFor: /^\w*$/,
  }
}

/** `$image['…']`, `$item['…']`: what a value of that field holds, when the schema says. */
function arrayKeys(
  context: CompletionContext,
  variable: string,
  quote: string,
  word: string,
  schema: ScreenNode[],
): CompletionResult | null {
  const keys = keysOf(variable, schema, context.state.doc.toString())
  if (keys.length === 0) return null

  return {
    from: context.pos - word.length,
    options: keys.map(([label, detail]) => ({
      label,
      detail,
      type: 'property',
      apply: (view, completion, from, to) => {
        const start = quote ? from - 1 : from
        const rest = view.state.sliceDoc(to, to + 2)
        const q = quote || "'"
        const end = to + (rest.startsWith(q) ? 1 : 0)
        const closed = view.state.sliceDoc(end, end + 1) === ']'
        const insert = `${q}${label}${q}${closed ? '' : ']'}`

        view.dispatch({
          changes: { from: start, to: end, insert },
          selection: { anchor: start + insert.length + (closed ? 1 : 0) },
          annotations: pickedCompletion.of(completion),
          userEvent: 'input.complete',
        })
      },
    })),
    validFor: /^\w*$/,
  }
}

function keysOf(variable: string, schema: ScreenNode[], template: string): [string, string][] {
  const field = fields(schema).find((node) => node.id === variable)
  if (field && MEDIA_TYPES.includes(field.type)) return MEDIA_KEYS.map((key) => [key, field.type])
  if (field?.type === 'wx-collection') return COLLECTION_KEYS.map((key) => [key, field.type])

  const list = loopVariables(template).get(variable)
  const [name, part] = list?.split('.') ?? []
  const source = name ? fields(schema).find((node) => node.id === name) : undefined
  if (!source) return []

  if (source.type === 'wx-collection') {
    const origin = typeof source.props?.source === 'string' ? source.props.source : source.type
    const own = part === 'items' ? (SOURCE_ITEMS[origin] ?? []) : []
    return [...(COLLECTION_PARTS[part ?? ''] ?? []), ...own].map((key) => [key, origin])
  }

  if (LIST_TYPES.includes(source.type)) return ITEM_KEYS.map((key) => [key, source.type])

  if (source.type === 'wx-repeater') {
    return fields(source.children ?? []).map((node) => [node.id, node.type])
  }

  return []
}

/**
 * `@foreach ($items as $item)` → item ⇒ items, and the key variable ⇒ nothing;
 * `@foreach ($questions['items'] as $q)` → q ⇒ questions.items.
 */
function loopVariables(template: string): Map<string, string> {
  const found = new Map<string, string>()

  for (const match of template.matchAll(
    /\$(\w+)(?:\[\s*['"](\w+)['"]\s*\])?\s+as\s+\$(\w+)(?:\s*=>\s*\$(\w+))?/g,
  )) {
    found.set(match[4] ?? match[3]!, match[2] ? `${match[1]}.${match[2]}` : match[1]!)
  }

  return found
}

function classNames(context: CompletionContext, value: string, styles: string): CompletionResult {
  const word = /[\w-]*$/.exec(value)![0]
  const present = new Set(value.split(/\s+/))

  return {
    from: context.pos - word.length,
    options: styleClasses(styles)
      .filter((name) => !present.has(name) || name === word)
      .map((label) => ({ label, detail: 'styles', type: 'class' })),
    validFor: /^[\w-]*$/,
  }
}

function directives(schema: ScreenNode[]): Completion[] {
  const blocks = fields(schema).filter((node) => node.type === 'wx-blocks')

  return [
    snippetCompletion('@if (${condition})\n\t${}\n@endif', { label: '@if', type: 'keyword' }),
    snippetCompletion('@isset(${value})\n\t${}\n@endisset', { label: '@isset', type: 'keyword' }),
    snippetCompletion('@foreach (${items} as ${item})\n\t${}\n@endforeach', {
      label: '@foreach',
      type: 'keyword',
    }),
    snippetCompletion('@forelse (${items} as ${item})\n\t${}\n@empty\n\t${}\n@endforelse', {
      label: '@forelse',
      type: 'keyword',
    }),
    snippetCompletion('@unless (${condition})\n\t${}\n@endunless', {
      label: '@unless',
      type: 'keyword',
    }),
    snippetCompletion('@class([${}])', { label: '@class', type: 'keyword' }),
    snippetCompletion('@style([${}])', { label: '@style', type: 'keyword' }),
    ...blocks.map((node) =>
      snippetCompletion(`@blocks('${node.id}')`, {
        label: '@blocks',
        detail: `('${node.id}')`,
        type: 'keyword',
        boost: 10,
      }),
    ),
    ...['@elseif', '@else', '@endif', '@endforeach', '@endisset', '@empty', '@endforelse'].map(
      (label) => ({ label, type: 'keyword', boost: -10 }),
    ),
  ]
}

/* ---------------------------------------------------------------------------------------- */
/* Styles                                                                                    */
/* ---------------------------------------------------------------------------------------- */

export interface StylesSources {
  slug: () => string
  template: () => string
}

export function stylesCompletions({ slug, template }: StylesSources): CompletionSource {
  return (context) => {
    const before = context.state.sliceDoc(Math.max(0, context.pos - 200), context.pos)
    const match = /(?:^|[\s,>+~(}{])\.([\w-]*)$/.exec(before)
    if (!match) return null

    // A dot in a value (`padding: .5rem`) is not a selector: the text since the last rule
    // boundary reads as `property: …`.
    const statement = /[^{};]*$/.exec(before)![0]
    if (/^\s*[\w-]+\s*:\s/.test(statement)) return null

    const root = `b-${slug()}`
    const styled = new Set(styleClasses(context.state.doc.toString()))
    const used = templateClasses(template())
    const names = [root, ...used.filter((name) => name !== root)]

    const options: Completion[] = names.map((name, index) => ({
      label: `.${name}`,
      // A class the template uses and nothing styles yet is the likeliest thing to write.
      detail: styled.has(name) ? 'template' : 'template · no rule yet',
      type: 'class',
      boost: (styled.has(name) ? 0 : 20) + (name === root ? 30 : 0) - index / 100,
    }))

    for (const name of styled) {
      if (!names.includes(name))
        options.push({ label: `.${name}`, detail: 'styles', type: 'class' })
    }

    return { from: context.pos - match[1]!.length - 1, options, validFor: /^\.[\w-]*$/ }
  }
}

/** Classes a stylesheet declares, nested `&__part` resolved against its parent. */
export function styleClasses(styles: string): string[] {
  const source = styles.replace(/\/\*[\s\S]*?\*\//g, '')
  const found = new Set<string>()
  const stack: string[][] = []
  let prelude = ''

  for (const char of source) {
    if (char === '{') {
      const parents = stack.at(-1) ?? ['']
      const selectors = prelude
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '' && !part.startsWith('@'))
        .flatMap((part) =>
          part.includes('&')
            ? parents.map((parent) => part.replaceAll('&', parent))
            : parents.map((parent) => (parent ? `${parent} ${part}` : part)),
        )

      for (const selector of selectors) {
        for (const match of selector.matchAll(/\.(-?[a-zA-Z_][\w-]*)/g)) found.add(match[1]!)
      }

      stack.push(prelude.trim().startsWith('@') ? parents : selectors.length ? selectors : parents)
      prelude = ''
    } else if (char === '}') {
      stack.pop()
      prelude = ''
    } else if (char === ';') {
      prelude = ''
    } else {
      prelude += char
    }
  }

  return [...found]
}

/** Classes the template writes literally; anything Blade computes is left out. */
export function templateClasses(template: string): string[] {
  const found = new Set<string>()

  for (const match of template.matchAll(/\bclass\s*=\s*"([^"]*)"/g)) {
    const value = match[1]!.replace(/\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}|@\w+(\([^)]*\))?/g, ' ')

    for (const name of value.split(/\s+/)) {
      if (/^-?[a-zA-Z_][\w-]*$/.test(name)) found.add(name)
    }
  }

  return [...found]
}

/* ---------------------------------------------------------------------------------------- */
/* Fields                                                                                    */
/* ---------------------------------------------------------------------------------------- */

export interface SchemaSources {
  types: () => TypeRegistry
}

/** The keys of a node (`ScreenNode`), each with what goes after the colon. */
const NODE_KEYS: { key: string; value: string; info: string }[] = [
  { key: 'id', value: '""', info: 'The variable the template gets, in snake_case.' },
  { key: 'type', value: '""', info: 'A type from the registry.' },
  { key: 'label', value: '""', info: 'What the editor sees above the field.' },
  { key: 'help', value: '""', info: 'A hint under the field.' },
  { key: 'localized', value: 'true', info: 'Edited per content language.' },
  { key: 'props', value: '{}', info: 'Passed to the component as they are.' },
  { key: 'children', value: '[]', info: 'Nodes inside a layout node or a repeater.' },
  { key: 'visible', value: '', info: '`false`, or a condition on another field.' },
  { key: 'slot', value: '""', info: "The parent's named slot to land in." },
  { key: 'can', value: '""', info: 'The permission the node needs.' },
]

export function schemaCompletions({ types }: SchemaSources): CompletionSource {
  return (context) => {
    const at = jsonContext(context.state.doc.toString(), context.pos)
    if (!at || !at.node) return null

    const word = at.string
      ? context.state.sliceDoc(at.string, context.pos)
      : /[\w-]*$/.exec(context.state.sliceDoc(Math.max(0, context.pos - 60), context.pos))![0]

    if (!at.string && word === '' && !context.explicit) return null

    const from = context.pos - word.length
    const quoted = at.string !== null

    if (at.phase === 'key') {
      const options = NODE_KEYS.filter(({ key }) => !at.keys.has(key) || key === word).map(
        ({ key, value, info }, index): Completion => ({
          label: key,
          info,
          type: 'property',
          boost: -index,
          apply: (view, completion, start, end) =>
            applyJson(view, completion, start, end, quoted, `"${key}": ${value}`, value),
        }),
      )

      return { from, options, validFor: /^[\w-]*$/ }
    }

    if (at.phase === 'value' && at.key === 'type') {
      const options = Object.entries(types()).map(([type, entry]): Completion => ({
        label: type,
        detail: entry.kind,
        type: 'type',
        // Fields first: a block's schema is mostly fields, the layout only groups them.
        boost: entry.kind === 'field' ? 1 : 0,
        apply: (view, completion, start, end) =>
          applyJson(view, completion, start, end, quoted, `"${type}"`, ''),
      }))

      return { from, options, validFor: /^[\w-]*$/ }
    }

    return null
  }
}

/**
 * Replaces the word being typed — with its quotes, whichever of them are there — and puts
 * the caret inside the value that follows: between `""`, `{}`, `[]`, or after the colon.
 * A `type` opens the list of types straight away, since that is the next thing to pick.
 */
function applyJson(
  view: EditorView,
  completion: Completion,
  from: number,
  to: number,
  quoted: boolean,
  insert: string,
  value: string,
): void {
  const start = quoted ? from - 1 : from
  const rest = /^[\w-]*"?/.exec(view.state.sliceDoc(to, to + 60))![0]
  const end = to + (quoted ? rest.length : /^[\w-]*/.exec(rest)![0].length)
  const inside = value === '""' || value === '{}' || value === '[]' ? 1 : 0

  view.dispatch({
    changes: { from: start, to: end, insert },
    selection: { anchor: start + insert.length - inside },
    annotations: pickedCompletion.of(completion),
    userEvent: 'input.complete',
  })

  if (insert.startsWith('"type"')) startCompletion(view)
}

interface Frame {
  kind: 'object' | 'array'
  parent: Frame | null
  /** For an array: the key it is the value of. */
  under: string | null
  keys: Set<string>
  phase: 'key' | 'colon' | 'value' | 'after'
  key: string | null
}

export interface JsonContext {
  /** The caret stands in an object that is a screen node. */
  node: boolean
  phase: Frame['phase']
  /** The key whose value is being written. */
  key: string | null
  /** Every key the object has, before the caret and after it. */
  keys: Set<string>
  /** Where the string under the caret starts, after its opening quote. */
  string: number | null
}

/**
 * Where the caret stands in a schema, by one pass over the text — it has to work on a
 * document that is broken right there, which is always the case while typing, and a parser
 * gives up on exactly that.
 */
export function jsonContext(doc: string, pos: number): JsonContext | null {
  let top = null as Frame | null
  let snapshot: JsonContext | null = null
  let string: { start: number; key: boolean } | null = null
  let escaped = false

  const isNode = (frame: Frame | null): boolean =>
    frame?.kind === 'object' &&
    frame.parent?.kind === 'array' &&
    (frame.parent.parent === null || frame.parent.under === 'children')

  for (let i = 0; i <= doc.length; i++) {
    if (i === pos) {
      snapshot =
        top?.kind === 'object'
          ? {
              node: isNode(top),
              phase: string?.key ? 'key' : top.phase,
              key: top.key,
              keys: top.keys,
              string: string ? string.start + 1 : null,
            }
          : null
    }

    if (i === doc.length) break
    const char = doc[i]!

    if (string) {
      if (escaped) escaped = false
      else if (char === '\\') escaped = true
      else if (char === '"' || char === '\n') {
        const text = doc.slice(string.start + 1, i)

        if (top?.kind === 'object') {
          if (string.key) {
            top.keys.add(text)
            top.key = text
            top.phase = 'colon'
          } else if (top.phase === 'value') {
            top.phase = 'after'
          }
        }

        string = null
      }
      continue
    }

    if (/\s/.test(char)) continue

    if (char === '"') {
      string = { start: i, key: top?.kind === 'object' && top.phase === 'key' }
    } else if (char === '{' || char === '[') {
      const frame: Frame = {
        kind: char === '{' ? 'object' : 'array',
        parent: top,
        under: top?.kind === 'object' ? top.key : null,
        keys: new Set(),
        phase: 'key',
        key: null,
      }
      if (top?.kind === 'object') top.phase = 'after'
      top = frame
    } else if (char === '}' || char === ']') {
      top = top?.parent ?? null
    } else if (char === ':') {
      if (top?.kind === 'object') top.phase = 'value'
    } else if (char === ',') {
      if (top?.kind === 'object') {
        top.phase = 'key'
        top.key = null
      }
    }
    // A bare word after a colon keeps the phase: `"type": wx` is a type being typed without
    // its quotes, and `true` or `12` has nothing to complete either way.
  }

  return snapshot
}

/* ---------------------------------------------------------------------------------------- */

/** Fields of a schema through its layout, the way the template sees them: flat. */
function fields(schema: ScreenNode[]): ScreenNode[] {
  const found: ScreenNode[] = []

  for (const node of schema) {
    if (!node || typeof node !== 'object') continue
    if (LAYOUT.has(node.type)) found.push(...fields(node.children ?? []))
    else if (typeof node.id === 'string' && node.id !== '') found.push(node)
  }

  return found
}

/** A `wx-blocks` value is printed with `@blocks('id')`, never echoed. */
function printable(schema: ScreenNode[]): ScreenNode[] {
  return fields(schema).filter((node) => node.type !== 'wx-blocks')
}

const LAYOUT = new Set(['wx-card', 'wx-tabs', 'wx-tab', 'wx-row', 'wx-col', 'wx-divider'])

function escape(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\!]/g, '\\$&')
}
