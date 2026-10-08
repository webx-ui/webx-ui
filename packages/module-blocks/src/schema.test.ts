import { describe, expect, it } from 'vitest'
import type { ScreenNode } from '@webx-ui/schema'
import {
  callerWords,
  callTag,
  holdsMarkup,
  kindOf,
  markupFields,
  usageWords,
  withPlaceholders,
} from './schema'

const t = (key: string, params: Record<string, string | number> = {}) =>
  `${key} ${Object.values(params).join(' ')}`.trim()

describe('how many entities a type stands on, in words', () => {
  it('says nothing about a count when there is none', () => {
    expect(usageWords(0, t)).toBe('page.not-used')
  })

  /* The line that used to read "on 1 pages" — and did so exactly when a type had just been
     put on its first page, which is when somebody is most likely looking at it. */
  it('has a line of its own for one', () => {
    expect(usageWords(1, t)).toBe('page.on-page')
  })

  it('counts from two', () => {
    expect(usageWords(2, t)).toBe('page.on-pages 2')
  })
})

describe('what a component says about itself', () => {
  it('reads a type without a kind as a block: a server older than components sends none', () => {
    expect(kindOf({})).toBe('block')
    expect(kindOf({ kind: 'component' })).toBe('component')
  })

  it('counts the blocks that call it, with a line of its own for one', () => {
    expect(callerWords(0, t)).toBe('components.not-called')
    expect(callerWords(1, t)).toBe('components.in-block')
    expect(callerWords(3, t)).toBe('components.in-blocks 3')
  })
})

describe('the tag that calls a type', () => {
  it('binds a structure, writes a word as it is, and closes itself without slots', () => {
    expect(
      callTag(
        'badge',
        [
          { id: 'tone', type: 'wx-segmented' },
          { id: 'card', type: 'wx-data', props: { shape: 'recipes.card' } },
          { id: 'image', type: 'wx-media' },
        ],
        { tone: 'accent', image: { path: 'a.jpg' } },
      ),
    ).toBe('<x-webx-block type="badge" tone="accent" :card="$card" :image="$image" />')
  })

  it('puts the declared slots in the body, through the layout', () => {
    expect(
      callTag('section', [
        { id: 'title', type: 'wx-input' },
        { id: 'box', type: 'wx-card', children: [{ id: 'aside', type: 'wx-slot' }] },
      ]),
    ).toBe(
      [
        '<x-webx-block type="section" :title="$title">',
        '    <x-slot:aside>…</x-slot:aside>',
        '</x-webx-block>',
      ].join('\n'),
    )
  })

  it('carries the fallback of a declared place, and names a dashed input as a variable would be', () => {
    expect(
      callTag(
        'recipe-card',
        [{ id: 'cta-label', type: 'wx-input' }],
        {},
        'webx-recipes::partials.card',
      ),
    ).toBe(
      '<x-webx-block type="recipe-card" :cta-label="$ctaLabel" fallback="webx-recipes::partials.card" />',
    )
  })
})

describe('a plain field that holds markup', () => {
  const schema: ScreenNode[] = [
    {
      id: 'card',
      type: 'wx-card',
      children: [
        { id: 'heading', type: 'wx-input', name: 'heading' },
        { id: 'lead', type: 'wx-textarea', name: 'lead', help: 'Two lines at most' },
        { id: 'title', type: 'wx-input', name: 'title' },
        { id: 'body', type: 'wx-rich-text', name: 'body' },
      ],
    },
  ]

  it('tells a tag from a sentence with a less-than sign in it', () => {
    expect(holdsMarkup('Deeply heard<span>.</span> Gently guided')).toBe(true)
    expect(holdsMarkup({ en: 'Plain', de: 'Mit <em>Akzent</em>' })).toBe(true)
    expect(holdsMarkup('3 < 5 and 7 > 2')).toBe(false)
    expect(holdsMarkup(null)).toBe(false)
  })

  it('finds the plain fields whose value holds tags, and only those', () => {
    const values = {
      heading: 'Deeply heard<span>.</span>',
      lead: 'A <strong>bold</strong> lead',
      title: 'Plain',
      body: '<p>A document</p>',
    }

    expect(markupFields(schema, values)).toEqual(['heading', 'lead'])
  })

  it('says so under the field, unless the type already says something there', () => {
    const form = withPlaceholders(schema, {}, ['heading', 'lead'], 'Holds tags')
    const fields = form[0]!.children!

    expect(fields[0]!.help).toBe('Holds tags')
    expect(fields[1]!.help).toBe('Two lines at most')
    expect(fields[2]!.help).toBeUndefined()
  })

  it('gives an inline rich field the words of its sample, without the markup', () => {
    const form = withPlaceholders(
      [{ id: 'heading', type: 'wx-rich-text', name: 'heading', props: { inline: true } }],
      { heading: 'Deeply heard<span>.</span> Gently guided' },
    )

    expect(form[0]!.props?.placeholder).toBe('Deeply heard. Gently guided')
  })
})
