import { describe, expect, it } from 'vitest'
import { callerWords, callTag, kindOf, usageWords } from './schema'

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
