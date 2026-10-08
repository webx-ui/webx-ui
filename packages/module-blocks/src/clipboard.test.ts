import { beforeEach, describe, expect, it } from 'vitest'
import {
  CLIPBOARD_KEY,
  parseClip,
  planPaste,
  useBlocksClipboard,
  type PasteTarget,
} from './clipboard'
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
    published: {
      number: 1,
      source: 'panel',
      comment: null,
      author_id: null,
      author: null,
      created_at: null,
    },
    usage_count: 0,
    thumbnail: null,
    created_at: null,
    updated_at: null,
    content: { schema: [], template: '', styles: '', script: null, sample: {} },
    ...extra,
  }
}

const catalog = [
  type('hero', { max_per_entity: 1 }),
  type('text'),
  type('header', { allowed_in: ['region:header'] }),
  type('section', { allow: ['text'] }),
]

function target(extra: Partial<PasteTarget> = {}): PasteTarget {
  return { tree: [], list: [], parent: null, allow: null, max: null, root: 'root', ...extra }
}

const node = (key: string, slug: string, values: Record<string, unknown> = {}): BlockNode => ({
  key,
  type: slug,
  values,
})

describe('planPaste', () => {
  it('renews every key, nested ones included, and keeps a switched-off block switched off', () => {
    const section = {
      ...node('s', 'section', { content: [node('t', 'text', { body: 'x' })] }),
      hidden: true as const,
    }

    const { accepted, skipped } = planPaste([section], target(), catalog)

    expect(skipped).toEqual([])
    expect(accepted[0]!.key).not.toBe('s')
    expect(accepted[0]!.hidden).toBe(true)
    const inner = accepted[0]!.values.content as BlockNode[]
    expect(inner[0]!.key).not.toBe('t')
    expect(inner[0]!.values.body).toBe('x')
  })

  it('leaves out a type this site does not offer, and a block with one inside it', () => {
    const { accepted, skipped } = planPaste(
      [
        node('a', 'gone'),
        node('b', 'section', { content: [node('c', 'gone')] }),
        node('d', 'text'),
      ],
      target(),
      catalog,
    )

    expect(accepted.map((n) => n.type)).toEqual(['text'])
    expect(skipped.map((s) => [s.node.key, s.reason])).toEqual([
      ['a', 'unknown'],
      ['b', 'unknown'],
    ])
  })

  it('judges the place by the same rules as the picker', () => {
    expect(planPaste([node('h', 'header')], target(), catalog).skipped[0]!.reason).toBe('place')
    expect(
      planPaste([node('h', 'header')], target({ root: 'region:header' }), catalog).accepted,
    ).toHaveLength(1)

    const section = catalog.find((t) => t.slug === 'section')!
    const inside = planPaste(
      [node('h', 'hero'), node('t', 'text')],
      target({ parent: section }),
      catalog,
    )
    expect(inside.accepted.map((n) => n.type)).toEqual(['text'])
    expect(inside.skipped[0]!.reason).toBe('place')
  })

  it('stops at the limit of the list and at the limit of the type on the page', () => {
    const full = planPaste(
      [node('a', 'text'), node('b', 'text')],
      target({ list: [node('x', 'text')], max: 2 }),
      catalog,
    )
    expect(full.accepted).toHaveLength(1)
    expect(full.skipped[0]!.reason).toBe('full')

    const twice = planPaste([node('a', 'hero'), node('b', 'hero')], target(), catalog)
    expect(twice.accepted).toHaveLength(1)
    expect(twice.skipped[0]!.reason).toBe('limit')

    const already = planPaste([node('a', 'hero')], target({ tree: [node('x', 'hero')] }), catalog)
    expect(already.skipped[0]!.reason).toBe('limit')
  })
})

describe('the clip', () => {
  beforeEach(() => useBlocksClipboard().clear())

  it('is stored under one key and read back', () => {
    const { clip, copy } = useBlocksClipboard()

    copy([node('a', 'text')])

    expect(clip.value?.nodes[0]!.key).toBe('a')
    expect(parseClip(localStorage.getItem(CLIPBOARD_KEY))?.nodes).toHaveLength(1)
  })

  it('treats anything else in storage as nothing copied', () => {
    expect(parseClip(null)).toBeNull()
    expect(parseClip('not json')).toBeNull()
    expect(parseClip('{"format":"webx-blocks-clip","format_version":2,"nodes":[{}]}')).toBeNull()
    expect(parseClip('{"format":"webx-blocks-clip","format_version":1,"nodes":[]}')).toBeNull()
  })

  it('follows a copy made in another tab', () => {
    const { clip } = useBlocksClipboard()
    const raw = JSON.stringify({
      format: 'webx-blocks-clip',
      format_version: 1,
      copied_at: '',
      nodes: [node('z', 'text')],
    })

    localStorage.setItem(CLIPBOARD_KEY, raw)
    window.dispatchEvent(new StorageEvent('storage', { key: CLIPBOARD_KEY, newValue: raw }))

    expect(clip.value?.nodes[0]!.key).toBe('z')
  })
})
