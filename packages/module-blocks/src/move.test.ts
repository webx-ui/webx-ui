import { describe, expect, it } from 'vitest'
import { destinations } from './move'
import type { BlockNode, BlockType } from './types'

function type(slug: string, extra: Partial<BlockType> = {}): BlockType {
  return {
    id: slug.length,
    slug,
    title: slug,
    description: null,
    icon: null,
    group: 'content',
    sort: 0,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: null,
    published: null,
    usage_count: 0,
    thumbnail: null,
    created_at: null,
    updated_at: null,
    content: { schema: [], template: '', styles: '', script: null, sample: {} },
    ...extra,
  }
}

function container(slug: string, allow: string[], max?: number): BlockType {
  return type(slug, {
    allow,
    content: {
      schema: [{ id: 'children', type: 'wx-blocks', label: 'Children', props: { max } }],
      template: '',
      styles: '',
      script: null,
      sample: {},
    },
  })
}

const catalog = [
  type('hero'),
  type('rich-text', { allowed_in: ['text-block'] }),
  container('text-block', ['rich-text']),
  container('columns', ['hero'], 1),
]

const top = { owner: null, allow: null, max: null }

function tree(): BlockNode[] {
  return [
    { key: 'h', type: 'hero', values: {} },
    { key: 'r', type: 'rich-text', values: {} },
    { key: 't', type: 'text-block', values: { children: [] } },
    {
      key: 'c',
      type: 'columns',
      values: { children: [{ key: 'h2', type: 'hero', values: {} }] },
    },
  ]
}

describe('destinations', () => {
  /* The case that asked for it: a text made at the top level, then meant for the text block. */
  it('offers the container that allows the type, and not the one that does not', () => {
    const places = destinations(tree(), 'r', catalog, top)

    expect(places.map((place) => place.parentKey)).toEqual(['t'])
    expect(places[0]!.trail).toEqual(['text-block'])
  })

  it('offers the top level to a nested block that may stand there, and says a full list is full', () => {
    const places = destinations(tree(), 'h2', catalog, top)

    expect(places.map((place) => place.parentKey)).toEqual([null])

    const out = destinations(tree(), 'h', catalog, top)
    expect(out.map((place) => [place.parentKey, place.full])).toEqual([['c', true]])
  })

  it('never offers the block itself, nor where it already is', () => {
    const places = destinations(tree(), 'c', catalog, top)

    expect(places).toEqual([])
  })

  it('keeps a page field narrowed by its own allow', () => {
    const narrowed = { owner: null, allow: ['rich-text'], max: null }

    expect(destinations(tree(), 'h2', catalog, narrowed)).toEqual([])
  })
})
