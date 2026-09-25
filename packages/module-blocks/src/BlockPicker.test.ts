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
})
