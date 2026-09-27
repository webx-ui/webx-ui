import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import BlockPicker from './BlockPicker.vue'
import type { BlockType } from './types'

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
    ...extra,
  }
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('WxBlockPicker', () => {
  /* A component is called from templates, never put on a page by hand: its schema is what a
     caller passes, not a form an editor could fill (§3.9). */
  it('does not offer components, even when the catalogue holds them', async () => {
    mount(BlockPicker, {
      attachTo: document.body,
      props: {
        catalog: [
          type('hero'),
          type('badge', { kind: 'component' }),
          type('text', { kind: 'block' }),
        ],
      },
      global: { stubs: { BlockThumb: true, RouterLink: true } },
    })

    await flushPromises()

    const offered = [...document.body.querySelectorAll('.wx-block-picker__title')].map(
      (title) => title.textContent,
    )

    expect(offered).toEqual(['hero', 'text'])
  })

  function offered(props: Record<string, unknown>): Promise<(string | null)[]> {
    mount(BlockPicker, {
      attachTo: document.body,
      props: { catalog: [], ...props },
      global: { stubs: { BlockThumb: true, RouterLink: true } },
    })

    return flushPromises().then(() =>
      [...document.body.querySelectorAll('.wx-block-picker__title')].map((t) => t.textContent),
    )
  }

  it('narrows the top level to what the field allows', async () => {
    expect(
      await offered({ catalog: [type('hero'), type('rich-text')], allow: ['rich-text'] }),
    ).toEqual(['rich-text'])
  })

  /* The block editor's sample form: its top level is the block, not a page, so a type that
     may stand only inside that block is offered there, and nothing the field leaves out is. */
  it('treats the owning block as the parent of a sample constructor', async () => {
    const owner = type('text-block', { allow: ['rich-text'] })

    expect(
      await offered({
        catalog: [type('hero'), type('rich-text', { allowed_in: ['text-block'] }), owner],
        parent: owner,
      }),
    ).toEqual(['rich-text'])
  })
})
