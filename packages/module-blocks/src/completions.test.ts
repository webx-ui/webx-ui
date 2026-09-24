import { describe, expect, it } from 'vitest'
import { CompletionContext, type CompletionResult } from '@codemirror/autocomplete'
import { EditorState } from '@codemirror/state'
import { EditorView } from '@codemirror/view'
import type { ScreenNode, TypeRegistry } from '@webx-ui/schema'
import {
  jsonContext,
  schemaCompletions,
  styleClasses,
  stylesCompletions,
  templateClasses,
  templateCompletions,
} from './completions'

const schema: ScreenNode[] = [
  { id: 'eyebrow', type: 'wx-input', label: 'Eyebrow' },
  {
    id: 'box',
    type: 'wx-card',
    children: [{ id: 'heading', type: 'wx-input' }],
  },
  { id: 'image', type: 'wx-media' },
  { id: 'items', type: 'wx-repeater', children: [{ id: 'title', type: 'wx-input' }] },
  { id: 'content', type: 'wx-blocks' },
  { id: 'questions', type: 'wx-collection', props: { source: 'faq' } },
]

/** `|` marks the caret. */
function at(text: string): { state: EditorState; pos: number } {
  const pos = text.indexOf('|')
  return { state: EditorState.create({ doc: text.replace('|', '') }), pos }
}

function ask(
  source: (context: CompletionContext) => CompletionResult | null | Promise<unknown>,
  text: string,
  explicit = false,
): CompletionResult | null {
  const { state, pos } = at(text)
  return source(new CompletionContext(state, pos, explicit)) as CompletionResult | null
}

function labels(result: CompletionResult | null): string[] {
  return (result?.options ?? []).map((option) => option.displayLabel ?? option.label)
}

/** Applies an option the way picking it does, and returns the text with the caret as `|`. */
function pick(source: Parameters<typeof ask>[0], text: string, label: string): string {
  const { state, pos } = at(text)
  const result = source(new CompletionContext(state, pos, false)) as CompletionResult
  const option = result.options.find((o) => (o.displayLabel ?? o.label) === label)!
  const view = new EditorView({ state })

  if (typeof option.apply === 'function') option.apply(view, option, result.from, pos)
  else {
    const insert = option.apply ?? option.label
    view.dispatch({
      changes: { from: result.from, to: pos, insert },
      selection: { anchor: result.from + insert.length },
    })
  }

  const doc = view.state.doc.toString()
  const head = view.state.selection.main.head
  view.destroy()

  return doc.slice(0, head) + '|' + doc.slice(head)
}

describe('the template', () => {
  const source = templateCompletions({
    schema: () => schema,
    styles: () => '.b-hero { &__inner { } } .b-hero__title {}',
  })

  it('offers the fields straight after {{, through the layout, without the nested blocks', () => {
    expect(labels(ask(source, '<p>{{|}}</p>'))).toEqual([
      '$eyebrow',
      '$heading',
      '$image',
      '$items',
      '$questions',
    ])
  })

  it('writes the echo whole, whatever closeBrackets has typed', () => {
    expect(pick(source, '<p>{{|}}</p>', '$heading')).toBe('<p>{{ $heading }}|</p>')
    expect(pick(source, '<p>{{ he| }}</p>', '$heading')).toBe('<p>{{ $heading }}|</p>')
    expect(pick(source, '<p>{{|', '$heading')).toBe('<p>{{ $heading }}|')
    expect(pick(source, '<p>{!!|!!}</p>', '$eyebrow')).toBe('<p>{!! $eyebrow !!}|</p>')
  })

  it('offers fields, loop variables and what is given after a $', () => {
    const found = labels(ask(source, '@foreach ($items as $item) @if ($|'))
    expect(found.slice(0, 4)).toEqual(['$eyebrow', '$heading', '$image', '$items'])
    expect(found).toContain('$item')
    expect(found).toContain('$block')
  })

  it('knows the keys of a picked file and of a repeater item', () => {
    expect(labels(ask(source, "{{ $image['|"))).toEqual(['url', 'alt', 'title', 'path'])
    expect(labels(ask(source, '@foreach ($items as $item) {{ $item[|'))).toEqual(['title'])
    expect(pick(source, '{{ $image[|] }}', 'url')).toBe("{{ $image['url']| }}")
    expect(pick(source, "{{ $image['|'] }}", 'alt')).toBe("{{ $image['alt']| }}")
    expect(ask(source, '{{ $eyebrow[|')).toBeNull()
  })

  it('knows what a collection hands over, and what its items and groups hold', () => {
    expect(labels(ask(source, "{{ $questions['|"))).toEqual(['items', 'groups', 'filter'])
    expect(labels(ask(source, "@foreach ($questions['items'] as $item) {{ $item['|"))).toEqual([
      'id',
      'anchor',
      'categories',
      'question',
      'answer',
    ])
    expect(labels(ask(source, '@foreach ($questions["groups"] as $group) {{ $group[|'))).toEqual([
      'id',
      'title',
      'items',
    ])
  })

  it('knows the card of the recipes source', () => {
    const recipes = templateCompletions({
      schema: () => [{ id: 'recipes', type: 'wx-collection', props: { source: 'recipes' } }],
      styles: () => '',
    })

    expect(labels(ask(recipes, "@foreach ($recipes['items'] as $recipe) {{ $recipe['|"))).toEqual([
      'id',
      'anchor',
      'categories',
      'title',
      'url',
      'lead',
      'cover',
      'gallery',
      'minutes',
      'servings',
      'nutrients',
      'fields',
    ])
  })

  it('knows the card of the reviews source', () => {
    const reviews = templateCompletions({
      schema: () => [{ id: 'reviews', type: 'wx-collection', props: { source: 'reviews' } }],
      styles: () => '',
    })

    expect(labels(ask(reviews, "@foreach ($reviews['items'] as $review) {{ $review['|"))).toEqual([
      'id',
      'anchor',
      'categories',
      'name',
      'initials',
      'job_title',
      'text',
      'rating',
      'date',
      'profile',
      'photo',
      'fields',
    ])
  })

  it('offers the classes the styles declare, nested ones resolved', () => {
    expect(labels(ask(source, '<div class="b-hero |"'))).toEqual(['b-hero__inner', 'b-hero__title'])
  })

  it('offers directives, with @blocks for each nested list', () => {
    const found = labels(ask(source, '<div>\n  @fo|'))
    expect(found).toContain('@foreach')
    expect(found).toContain('@blocks')
    expect(ask(source, 'mail@exa|')).toBeNull()
  })
})

describe('the styles', () => {
  const source = stylesCompletions({
    slug: () => 'hero',
    template: () =>
      '<section class="b-hero"><h2 class="b-hero__title {{ $x }}">…</h2><p class="b-hero__text"></p></section>',
  })

  it('puts the root first and the classes nothing styles yet next', () => {
    const found = ask(source, '.b-hero__title {}\n.|')!
    const sorted = [...found.options].sort((a, b) => (b.boost ?? 0) - (a.boost ?? 0))
    expect(sorted.map((o) => o.label)).toEqual(['.b-hero', '.b-hero__text', '.b-hero__title'])
  })

  it('stays out of values', () => {
    expect(ask(source, '.b-hero { padding: .|')).toBeNull()
    expect(ask(source, '.b-hero { opacity: 0.|')).toBeNull()
  })

  it('reads classes on both sides', () => {
    expect(styleClasses('/* .gone */ .a { &__b { &--c {} } } @container (x) { .d .e {} }')).toEqual(
      ['a', 'a__b', 'a__b--c', 'd', 'e'],
    )
    expect(templateClasses("<a class=\"x {{ $y ? 'z' : '' }} w\"></a>")).toEqual(['x', 'w'])
  })
})

describe('the fields', () => {
  const types: TypeRegistry = {
    'wx-card': { component: {}, kind: 'layout' },
    'wx-input': { component: {}, kind: 'field' },
  }
  const source = schemaCompletions({ types: () => types })

  it('knows where the caret stands', () => {
    const doc = '[{ "id": "a", "props": { "x": 1 }, "children": [{ "' + '" }] }]'
    expect(jsonContext(doc, doc.indexOf('"x"') + 1)!.node).toBe(false)
    const inner = jsonContext(doc, doc.lastIndexOf('"') - 1)!
    expect(inner).toMatchObject({ node: true, phase: 'key' })
  })

  it('offers the keys a node does not have yet', () => {
    const found = labels(ask(source, '[{ "id": "a", "|" }]'))
    expect(found).not.toContain('id')
    expect(found.slice(0, 3)).toEqual(['type', 'label', 'help'])
  })

  it('writes a key with its value and puts the caret inside it', () => {
    expect(pick(source, '[{ "|" }]', 'label')).toBe('[{ "label": "|" }]')
    expect(pick(source, '[{ la| }]', 'label')).toBe('[{ "label": "|" }]')
    expect(pick(source, '[{ "|" }]', 'props')).toBe('[{ "props": {|} }]')
    expect(pick(source, '[{ "loc|" }]', 'localized')).toBe('[{ "localized": true| }]')
  })

  it('offers the registry for a type, and nothing inside props', () => {
    expect(labels(ask(source, '[{ "type": "|" }]'))).toEqual(['wx-card', 'wx-input'])
    expect(pick(source, '[{ "type": "wx-i|" }]', 'wx-input')).toBe('[{ "type": "wx-input"| }]')
    expect(pick(source, '[{ "type": wx| }]', 'wx-input')).toBe('[{ "type": "wx-input"| }]')
    expect(ask(source, '[{ "props": { "|" } }]')).toBeNull()
  })

  it('waits for a letter outside a string', () => {
    expect(ask(source, '[{ | }]')).toBeNull()
    expect(labels(ask(source, '[{ | }]', true))).toContain('id')
  })
})
