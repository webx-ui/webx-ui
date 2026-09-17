import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import BlocksField from './BlocksField.vue'
import type { BlockNode, BlockType } from './types'

/**
 * What the constructor does to the value.
 *
 * Layout is not tested here and cannot be — jsdom computes none of it, and the preview is
 * a page of the site that jsdom will never serve. What is worth pinning down is the tree:
 * selecting a block shows its form, editing a field writes into that block's values and
 * nothing else's, and removing a block takes its children with it.
 */
function type(slug: string, extra: Partial<BlockType> = {}): BlockType {
  return {
    id: slug.length,
    slug,
    title: slug.charAt(0).toUpperCase() + slug.slice(1),
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
    content: {
      schema: [{ id: 'title', type: 'wx-input', label: 'Title' }],
      template: '',
      styles: '',
      script: null,
      sample: { title: 'Sample' },
    },
    ...extra,
  }
}

const catalog: BlockType[] = [
  type('hero'),
  type('section', {
    allow: ['hero'],
    content: {
      schema: [
        { id: 'tone', type: 'wx-input', label: 'Tone' },
        { id: 'content', type: 'wx-blocks', label: 'Content', props: { allow: ['hero'] } },
      ],
      template: '',
      styles: '',
      script: null,
      sample: {},
    },
  }),
]

function tree(): BlockNode[] {
  return [
    { key: 'a', type: 'hero', values: { title: 'One' } },
    {
      key: 'b',
      type: 'section',
      values: { tone: 'muted', content: [{ key: 'c', type: 'hero', values: { title: 'Inner' } }] },
    },
  ]
}

function field(value: BlockNode[] = tree()) {
  return mount(BlocksField, {
    props: { modelValue: value, catalog, 'onUpdate:modelValue': () => {} },
    global: { stubs: { RouterLink: true } },
  })
}

/**
 * Press the confirming button of the dialog `confirm()` mounted.
 *
 * It lives outside the wrapper — that is the whole point of a dialog from code — so it is
 * found in the document and pressed for real rather than through the test utilities.
 */
function confirmIt(): void {
  const buttons = [...document.querySelectorAll<HTMLButtonElement>('.wx-dialog__foot button')]

  buttons[buttons.length - 1]!.click()
}

/* A dialog takes its own node away on a timer; one left behind is found by the next test. */
afterEach(async () => {
  const deadline = Date.now() + 2000

  while (document.querySelectorAll('.wx-modal-host').length > 0 && Date.now() < deadline) {
    await new Promise((settle) => setTimeout(settle, 10))
  }
})

function emitted(wrapper: ReturnType<typeof field>): BlockNode[] | null {
  const updates = wrapper.emitted('update:modelValue') as [BlockNode[]][] | undefined

  return updates ? updates[updates.length - 1]![0] : null
}

describe('WxBlocks', () => {
  it('draws the tree with nested blocks under their container', () => {
    const wrapper = field()
    const names = wrapper.findAll('.wx-blocks-tree__name').map((el) => el.text())

    expect(names).toEqual(['Hero', 'Section', 'Hero'])
    expect(wrapper.find('.wx-blocks__fields').exists()).toBe(false)
  })

  it('shows the form of the selected block and writes into its values only', async () => {
    const wrapper = field()

    await wrapper.findAll('.wx-blocks-tree__row')[2]!.trigger('click')

    expect(wrapper.find('.wx-blocks__fields').exists()).toBe(true)
    expect(wrapper.find('.wx-blocks').classes()).toContain('is-editing')

    const input = wrapper.find('.wx-blocks__fields input')
    await input.setValue('Changed')

    const next = emitted(wrapper)!
    expect(next[0]!.values.title).toBe('One')
    expect((next[1]!.values.content as BlockNode[])[0]!.values.title).toBe('Changed')
  })

  it('draws a note instead of a second constructor for the nested field', async () => {
    const wrapper = field()

    await wrapper.findAll('.wx-blocks-tree__row')[1]!.trigger('click')

    expect(wrapper.find('.wx-blocks-nested').exists()).toBe(true)
    expect(wrapper.findAll('.wx-blocks').length).toBe(1)
  })

  it('asks before removing a block that holds others', async () => {
    const wrapper = field()

    // Outside a panel the words are keys, so the buttons are found by place, not by name.
    await wrapper
      .findAll('.wx-blocks-tree__row')[1]!
      .findAll('.wx-blocks-tree__actions button')[1]!
      .trigger('click')

    await flushPromises()

    // Nothing has gone yet: what leaves with a container is not on screen, so it is asked
    // about. The count in the question is a translated line, so it reads as a key here.
    expect(emitted(wrapper)).toBeNull()
    expect(document.querySelector('.wx-confirm__message')).not.toBeNull()

    confirmIt()
    await flushPromises()

    expect(emitted(wrapper)!.map((node) => node.key)).toEqual(['a'])
  })

  it('removes a block that holds nothing without asking', async () => {
    const wrapper = field()

    await wrapper
      .findAll('.wx-blocks-tree__row')[0]!
      .findAll('.wx-blocks-tree__actions button')[1]!
      .trigger('click')

    await flushPromises()

    expect(document.querySelector('.wx-confirm__message')).toBeNull()
    expect(emitted(wrapper)!.map((node) => node.key)).toEqual(['b'])
  })

  it('duplicates a block with fresh keys, right after the original', async () => {
    const wrapper = field()

    await wrapper
      .findAll('.wx-blocks-tree__row')[1]!
      .findAll('.wx-blocks-tree__actions button')[0]!
      .trigger('click')

    const next = emitted(wrapper)!
    expect(next.length).toBe(3)
    expect(next[2]!.type).toBe('section')
    expect(next[2]!.key).not.toBe('b')
    expect((next[2]!.values.content as BlockNode[])[0]!.values.title).toBe('Inner')
  })

  it('leaves editing on Escape', async () => {
    const wrapper = field()

    await wrapper.findAll('.wx-blocks-tree__row')[0]!.trigger('click')
    expect(wrapper.find('.wx-blocks__fields').exists()).toBe(true)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.wx-blocks__fields').exists()).toBe(false)
  })
})
