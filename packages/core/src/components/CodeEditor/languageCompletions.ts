import { EditorSelection, EditorState, type Extension } from '@codemirror/state'
import { EditorView } from '@codemirror/view'
import { syntaxTree } from '@codemirror/language'
import {
  autocompletion,
  CompletionContext,
  type CompletionResult,
  type CompletionSource,
} from '@codemirror/autocomplete'
import { html, htmlCompletionSource, htmlLanguage } from '@codemirror/lang-html'
import { javascriptLanguage, scopeCompletionSource } from '@codemirror/lang-javascript'

type SyntaxNode = ReturnType<ReturnType<typeof syntaxTree>['resolve']>

/*
 * The trouble this file works around: an open `<script`, `<style` or `<textarea` at the very end
 * of the document — the usual case, typing into an empty editor — is parsed with an empty script
 * behind it. CodeMirror then takes the place for JavaScript: the list offers globals instead of
 * `src`, `async`, `defer`, and `>` closes nothing, since HTML's own auto-closing only runs where
 * HTML is the language. In an open tag it is always HTML's turn, whatever is mounted after it.
 */

/** The open tag the place is in, read from the HTML tree itself rather than the nested one. */
function openTagAt(state: EditorState, pos: number): SyntaxNode | null {
  for (let node: SyntaxNode | null = syntaxTree(state).resolve(pos, -1); node; node = node.parent) {
    if (node.name === 'OpenTag' || node.name === 'SelfClosingTag') return node
    if (node.name === 'Element' || node.name === 'Document') return null
  }

  return null
}

/*
 * `override` is a list fixed up front, while the sources of a place depend on the language there:
 * each slot asks for the n-th of them. JavaScript has the most, three — its keywords and snippets,
 * the names of the code itself and the globals added below — so four slots leave one to spare.
 */
const SLOTS = 4

function slot(index: number): CompletionSource {
  return (context: CompletionContext) => {
    const inTag = openTagAt(context.state, context.pos) !== null
    const sources: readonly CompletionSource[] =
      inTag && !htmlLanguage.isActiveAt(context.state, context.pos, -1)
        ? [htmlCompletionSource]
        : context.state.languageDataAt<CompletionSource>('autocomplete', context.pos)
    const result = sources[index]?.(context) ?? null

    return inTag ? Promise.resolve(result).then(ownFirst) : result
  }
}

let shared: Set<string> | null = null

/**
 * The attributes every element takes, asked of HTML's own source at a tag it knows nothing about:
 * what it offers there is the global list and nothing else.
 */
function globalAttributes(): Set<string> {
  if (shared === null) {
    const state = EditorState.create({ doc: '<x-any ', extensions: html() })
    const result = htmlCompletionSource(
      new CompletionContext(state, 7, true),
    ) as CompletionResult | null
    shared = new Set(result?.options.map((option) => option.label) ?? [])
  }

  return shared
}

/*
 * HTML offers a tag's own attributes and then some ninety global ones, sorted together by name:
 * `<script ` would open on `accesskey` and the `aria-*`, with `src` far below. Its own come first.
 */
function ownFirst(result: CompletionResult | null): CompletionResult | null {
  if (result === null) return null

  const global = globalAttributes()

  return {
    ...result,
    options: result.options.map((option) =>
      option.type === 'property' && !global.has(option.label) ? { ...option, boost: 1 } : option,
    ),
  }
}

/**
 * `>` typed at the end of an open script, style or textarea writes its closing tag, as HTML's
 * auto-closing does everywhere else.
 */
const closeAtTheEnd = EditorView.inputHandler.of((view, from, to, text) => {
  if (text !== '>' || from !== to || view.state.readOnly) return false
  if (htmlLanguage.isActiveAt(view.state, from, -1)) return false

  const tag = openTagAt(view.state, from)
  const name = tag?.getChild('TagName')
  if (!tag || !name || tag.name !== 'OpenTag' || tag.getChild('EndTag')) return false

  const close = `</${view.state.sliceDoc(name.from, name.to)}>`
  view.dispatch({
    changes: { from, insert: '>' + close },
    selection: EditorSelection.cursor(from + 1),
    userEvent: 'input.type',
  })

  return true
})

/**
 * What the language itself suggests: HTML tags, attributes and their values with the tag closed on
 * `>`, and the JavaScript and CSS nested in it. Each language ships its own source; this switches
 * the list on, adds the globals of the page after a dot — the same `window`, `document` and
 * `console` the code meets on the site — and mends the end of the document.
 */
export function languageCompletions(): Extension {
  return [
    autocompletion({
      override: Array.from({ length: SLOTS }, (_, index) => slot(index)),
      icons: false,
    }),
    javascriptLanguage.data.of({ autocomplete: scopeCompletionSource(globalThis) }),
    closeAtTheEnd,
  ]
}
