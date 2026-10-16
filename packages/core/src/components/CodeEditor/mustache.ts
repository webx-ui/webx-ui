import type { Extension, Range } from '@codemirror/state'
import {
  Decoration,
  ViewPlugin,
  type DecorationSet,
  type EditorView,
  type ViewUpdate,
} from '@codemirror/view'
import type {
  CodeEditorCompleter,
  CodeEditorCompletion,
  CodeEditorCompletionContext,
  MustacheVariable,
  MustacheWords,
} from './types'

/**
 * Mustache over text or HTML: `{{name}}`, sections `{{#list}}…{{/list}}`, `{{^name}}`, comments.
 *
 * Neither CodeMirror nor a maintained package carries Mustache for CodeMirror 6 (the legacy modes
 * have Jinja and Velocity, not Mustache), and a template of a notification is a dozen lines — so
 * the tags are found by one scan of the document and painted as marks over whatever language is
 * underneath. The same scan answers the completion source and the checks a form runs on a
 * template, so the three never disagree about where a tag begins.
 */

/** What a tag is, by its first character. */
export type MustacheTagKind =
  | 'variable'
  | 'section'
  | 'inverted'
  | 'close'
  | 'comment'
  /** `{{{name}}}` and `{{&name}}`: printed raw by Mustache itself. */
  | 'raw'
  /** `{{>partial}}`, `{{=<% %>=}}`, `{{%pragma}}`, `{{$block}}`, `{{<parent}}`. */
  | 'other'
  /** `{{` with no `}}` after it. */
  | 'unclosed'

export interface MustacheTag {
  kind: MustacheTagKind
  /** The name inside, trimmed; empty for a comment and for a tag still being typed. */
  name: string
  /** Offset of the first `{`. */
  from: number
  /** Offset just past the last `}` — the end of the document for an unclosed tag. */
  to: number
}

const SIGILS: Record<string, MustacheTagKind> = {
  '#': 'section',
  '^': 'inverted',
  '/': 'close',
  '!': 'comment',
  '&': 'raw',
  '>': 'other',
  '<': 'other',
  '=': 'other',
  '%': 'other',
  $: 'other',
}

/** Every tag of a template, in order. */
export function scanMustache(text: string): MustacheTag[] {
  const tags: MustacheTag[] = []
  let at = 0

  while (at < text.length) {
    const from = text.indexOf('{{', at)

    if (from === -1) break

    const triple = text.startsWith('{{{', from)
    const close = triple ? '}}}' : '}}'
    const end = text.indexOf(close, from + (triple ? 3 : 2))

    if (end === -1) {
      tags.push({ kind: 'unclosed', name: '', from, to: text.length })
      break
    }

    const inner = text.slice(from + (triple ? 3 : 2), end)
    const sigil = inner.trimStart().charAt(0)
    const kind: MustacheTagKind = triple ? 'raw' : (SIGILS[sigil] ?? 'variable')
    const name =
      kind === 'comment'
        ? ''
        : (kind === 'variable' || triple ? inner : inner.trimStart().slice(1)).trim()

    tags.push({ kind, name, from, to: end + close.length })
    at = end + close.length
  }

  return tags
}

/** The sections open at an offset, the innermost last. */
export function openSections(tags: MustacheTag[], before: number): string[] {
  const open: string[] = []

  for (const tag of tags) {
    if (tag.to > before) break

    if (tag.kind === 'section' || tag.kind === 'inverted') open.push(tag.name)

    if (tag.kind === 'close') {
      const at = open.lastIndexOf(tag.name)

      if (at !== -1) open.splice(at)
    }
  }

  return open
}

export interface MustacheProblems {
  /** Sections opened and never closed. */
  unclosed: string[]
  /** `{{/name}}` with nothing of that name open. */
  stray: string[]
  /** Names no variable and no field of an open list has — only when variables were given. */
  unknown: string[]
  /** Tags Mustache would read and a template here may not use: partials, delimiters, pragmas. */
  refused: string[]
  /** `{{` that never ends. */
  broken: boolean
}

/**
 * What is wrong with a template as it stands, by the same rules the completion knows: the open
 * list's fields inside its section, the variables everywhere. With no variables the names are not
 * judged — a template for any event may say anything.
 */
export function mustacheProblems(text: string, variables?: MustacheVariable[]): MustacheProblems {
  const tags = scanMustache(text)
  const problems: MustacheProblems = {
    unclosed: [],
    stray: [],
    unknown: [],
    refused: [],
    broken: false,
  }
  const open: string[] = []
  const lists = new Map((variables ?? []).map((variable) => [variable.name, variable]))

  const known = (name: string): boolean => {
    for (const section of [...open].reverse()) {
      if (lists.get(section)?.fields?.some((field) => field.name === name)) return true
    }

    return (variables ?? []).some(
      (variable) =>
        variable.name === name ||
        (variable.name.endsWith('.*') && name.startsWith(variable.name.slice(0, -1))),
    )
  }

  const unknown = new Set<string>()

  for (const tag of tags) {
    if (tag.kind === 'unclosed') problems.broken = true
    if (tag.kind === 'other') problems.refused.push(text.slice(tag.from, tag.to))
    if (tag.kind === 'comment' || tag.kind === 'unclosed' || tag.kind === 'other') continue

    if (tag.kind === 'close') {
      const at = open.lastIndexOf(tag.name)

      if (at === -1) problems.stray.push(tag.name)
      else open.splice(at)

      continue
    }

    if (variables !== undefined && tag.name !== '' && !known(tag.name)) unknown.add(tag.name)
    if (tag.kind === 'section' || tag.kind === 'inverted') open.push(tag.name)
  }

  problems.unclosed = open
  problems.unknown = [...unknown]

  return problems
}

/* ------------------------------------------------------------------------- highlighting ----- */

const marks: Record<MustacheTagKind, Decoration> = {
  variable: Decoration.mark({ class: 'wx-cm-mustache' }),
  raw: Decoration.mark({ class: 'wx-cm-mustache' }),
  section: Decoration.mark({ class: 'wx-cm-mustache wx-cm-mustache--section' }),
  inverted: Decoration.mark({ class: 'wx-cm-mustache wx-cm-mustache--section' }),
  close: Decoration.mark({ class: 'wx-cm-mustache wx-cm-mustache--section' }),
  comment: Decoration.mark({ class: 'wx-cm-mustache wx-cm-mustache--comment' }),
  other: Decoration.mark({ class: 'wx-cm-mustache wx-cm-mustache--invalid' }),
  unclosed: Decoration.mark({ class: 'wx-cm-mustache wx-cm-mustache--invalid' }),
}

/*
 * The whole document every time: a section opened on line 2 colours a close on line 40, and a
 * notification is short enough that a scan costs less than keeping track of what changed.
 */
function decorate(view: EditorView): DecorationSet {
  const ranges: Range<Decoration>[] = []

  for (const tag of scanMustache(view.state.doc.toString())) {
    if (tag.to > tag.from) ranges.push(marks[tag.kind].range(tag.from, tag.to))
  }

  return Decoration.set(ranges, true)
}

/** The tags painted as marks over the language underneath. */
export function mustacheHighlighting(): Extension {
  return ViewPlugin.fromClass(
    class {
      decorations: DecorationSet

      constructor(view: EditorView) {
        this.decorations = decorate(view)
      }

      update(update: ViewUpdate) {
        if (update.docChanged) this.decorations = decorate(update.view)
      }
    },
    { decorations: (plugin) => plugin.decorations },
  )
}

/* --------------------------------------------------------------------------- completion ----- */

const WORDS: MustacheWords = {
  variable: 'variable',
  list: 'list',
  field: 'field of :list',
  section: 'shown when :name has a value',
  inverted: 'shown when :name is empty',
  close: 'closes :name',
  comment: 'a comment, never printed',
  example: 'Example: :example',
}

function say(line: string, replace: Record<string, string>): string {
  return line.replace(/:(\w+)/g, (whole, key: string) => replace[key] ?? whole)
}

function exampleOf(variable: MustacheVariable): string | undefined {
  const example = variable.example

  if (example === undefined || example === null || example === '') return undefined
  if (typeof example === 'string' || typeof example === 'number') return String(example)

  return undefined
}

function isList(variable: MustacheVariable): boolean {
  return (variable.fields?.length ?? 0) > 0
}

/** A section around the caret: on lines of its own for a list, inline for "has a value". */
function sectionOf(name: string, sigil: '#' | '^', block: boolean): string {
  return block ? `${sigil}${name}}}\n\${}\n{{/${name}}}` : `${sigil}${name}}}\${}{{/${name}}}`
}

/**
 * Mustache's own suggestions after `{{`: the variables and the fields of the list being looped
 * over, a list as a whole section, `{{#…}}` / `{{^…}}` for "has a value" / "is empty", and after
 * `{{/` the section open nearest. With no variables only the syntax is offered — a template for
 * any event knows no names.
 *
 * Every suggestion ends the tag: the `}}` that closing brackets typed is taken over rather than
 * doubled, and a section is written whole with the caret inside.
 *
 * ```ts
 * <wx-code-editor language="mustache" :completions="mustacheCompletions(variables)" />
 * ```
 */
export function mustacheCompletions(
  variables: MustacheVariable[] = [],
  words: Partial<MustacheWords> = {},
): CodeEditorCompleter {
  const said: MustacheWords = { ...WORDS, ...words }
  const byName = new Map(variables.map((variable) => [variable.name, variable]))

  return (context: CodeEditorCompletionContext) => {
    const { text, pos } = context
    const start = text.lastIndexOf('{{', pos - 2)

    if (start === -1) return null

    const typed = text.slice(start + 2, pos)
    const head = /^([#^/!]?)(\s*)([\w.]*)$/.exec(typed)

    // A tag closed before the caret, or one with something in it that no name has.
    if (head === null) return null

    const sigil = head[1] ?? ''

    if (sigil === '!') return null

    const from = start + 2 + sigil.length + (head[2]?.length ?? 0)
    const after = text.slice(pos)
    const rest = /^[\w.]*/.exec(after)?.[0] ?? ''
    const to = pos + rest.length + (after.slice(rest.length).startsWith('}}') ? 2 : 0)
    const open = openSections(scanMustache(text.slice(0, start)), start)
    const options: CodeEditorCompletion[] = []

    if (sigil === '/') {
      ;[...open].reverse().forEach((name, depth) => {
        options.push({
          label: name,
          apply: `${name}}}`,
          detail: say(said.close, { name: `{{#${name}}}` }),
          type: 'keyword',
          boost: 99 - depth,
        })
      })

      return options.length === 0 ? null : { from, to, options }
    }

    if (sigil === '#' || sigil === '^') {
      for (const variable of variables) {
        if (variable.name.endsWith('.*')) continue

        const list = isList(variable)

        options.push({
          label: variable.name,
          apply: sectionOf(variable.name, sigil, list).slice(1),
          detail: list
            ? said.list
            : say(sigil === '#' ? said.section : said.inverted, { name: variable.name }),
          info: variable.label,
          type: list ? 'class' : 'variable',
          boost: list ? 1 : 0,
        })
      }

      return options.length === 0 ? null : { from, to, options }
    }

    // Inside a list: the fields of its element first, the way Mustache looks a name up.
    ;[...open].reverse().forEach((name, depth) => {
      for (const field of byName.get(name)?.fields ?? []) {
        options.push({
          label: field.name,
          apply: `${field.name}}}`,
          detail: say(said.field, { list: name }),
          info: field.label,
          type: 'property',
          boost: 50 - depth,
        })
      }
    })

    for (const variable of variables) {
      if (variable.name.endsWith('.*')) {
        options.push({
          label: `${variable.name.slice(0, -1)}…`,
          apply: variable.name.slice(0, -1),
          detail: said.variable,
          info: variable.label,
          type: 'variable',
        })

        continue
      }

      if (isList(variable)) {
        options.push({
          label: variable.name,
          apply: sectionOf(variable.name, '#', true),
          detail: said.list,
          info: [variable.label, (variable.fields ?? []).map((one) => one.name).join(', ')]
            .filter(Boolean)
            .join(' — '),
          type: 'class',
          boost: 5,
        })

        continue
      }

      const example = exampleOf(variable)

      options.push(
        {
          label: variable.name,
          apply: `${variable.name}}}`,
          detail: example,
          info: [variable.label, example === undefined ? undefined : say(said.example, { example })]
            .filter(Boolean)
            .join('\n'),
          type: 'variable',
          boost: 10,
        },
        {
          label: `#${variable.name}`,
          apply: sectionOf(variable.name, '#', false),
          detail: say(said.section, { name: variable.name }),
          type: 'keyword',
          boost: -10,
        },
        {
          label: `^${variable.name}`,
          apply: sectionOf(variable.name, '^', false),
          detail: say(said.inverted, { name: variable.name }),
          type: 'keyword',
          boost: -11,
        },
      )
    }

    const nearest = open.at(-1)

    if (nearest !== undefined) {
      options.push({
        label: `/${nearest}`,
        apply: `/${nearest}}}`,
        detail: say(said.close, { name: `{{#${nearest}}}` }),
        type: 'keyword',
        boost: 20,
      })
    }

    if (variables.length === 0) {
      options.push(
        {
          label: '#…',
          apply: '#${name}}}\n${}\n{{/${name}}}',
          detail: say(said.section, { name: '…' }),
          type: 'keyword',
        },
        {
          label: '^…',
          apply: '^${name}}}\n${}\n{{/${name}}}',
          detail: say(said.inverted, { name: '…' }),
          type: 'keyword',
        },
      )
    }

    options.push({
      label: '!…',
      apply: '! ${} }}',
      detail: said.comment,
      type: 'text',
      boost: -20,
    })

    return { from, to, options }
  }
}
