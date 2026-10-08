import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import BlocksField from './BlocksField.vue'
import { useBlocksClipboard } from './clipboard'
import type { BlockNode, BlockType } from './types'
import type { ScreenNode } from '@webx-ui/schema'

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
 * Choose something from a row's `···`.
 *
 * The menu's panel is teleported, so it is found in the document and clicked for real rather
 * than through the wrapper. Outside a panel the words are keys, so the lines are found by
 * place: what the row offers is add after, switch off, duplicate, copy, the paste lines while
 * something is copied, move, remove — and remove is destructive, so `WxRowMenu` keeps it last
 * whatever order it was written in.
 */
async function choose(wrapper: ReturnType<typeof field>, row: number, item: number): Promise<void> {
  await wrapper
    .findAll('.wx-blocks-tree__row')
    [row]!.get('.wx-actions__menu button')
    .trigger('click')

  const lines = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

  lines[item]!.click()

  await flushPromises()
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

function emitted(wrapper: ReturnType<typeof field>): BlockNode[] | null {
  const updates = wrapper.emitted('update:modelValue') as [BlockNode[]][] | undefined

  return updates ? updates[updates.length - 1]![0] : null
}

describe('WxBlocks', () => {
  beforeEach(() => useBlocksClipboard().clear())

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

  /**
   * The tree and the form take turns in one column, so everything the tree was there for
   * while a block is open has to be somewhere else: the head of the form. Found by place,
   * because outside a panel the labels are keys — previous, next, then the way out.
   */
  it('puts the tree away and steps between blocks in its head', async () => {
    const wrapper = field()
    const head = () => wrapper.find('.wx-blocks__panel-title').text()
    const steps = () => wrapper.findAll('.wx-blocks__panel-extra button')

    await wrapper.findAll('.wx-blocks-tree__row')[0]!.trigger('click')

    expect(wrapper.find('.wx-blocks__tree').exists()).toBe(false)
    expect(head()).toContain('Hero')
    /* Nothing above the first one. */
    expect(steps()[0]!.attributes('aria-disabled')).toBe('true')

    await steps()[1]!.trigger('click')
    expect(head()).toContain('Section')

    /* Down into what the section holds, not past it: the order is the one the page draws. */
    await steps()[1]!.trigger('click')
    expect(head()).toContain('Hero')
    /* And the trail says where that one lives, which is what the tree used to show. */
    expect(head()).toContain('Section')
    expect(steps()[1]!.attributes('aria-disabled')).toBe('true')
  })

  it('draws a note instead of a second constructor for the nested field', async () => {
    const wrapper = field()

    await wrapper.findAll('.wx-blocks-tree__row')[1]!.trigger('click')

    expect(wrapper.find('.wx-blocks-nested').exists()).toBe(true)
    expect(wrapper.findAll('.wx-blocks').length).toBe(1)
  })

  it('asks before removing a block that holds others', async () => {
    const wrapper = field()

    await choose(wrapper, 1, 5)

    // Nothing has gone yet: what leaves with a container is not on screen, so it is asked
    // about. The count in the question is a translated line, so it reads as a key here.
    expect(emitted(wrapper)).toBeNull()
    expect(document.querySelector('.wx-confirm__message')).not.toBeNull()

    confirmIt()
    await flushPromises()

    expect(emitted(wrapper)!.map((node) => node.key)).toEqual(['a'])
  })

  it('asks before removing a block that holds nothing either', async () => {
    const wrapper = field()

    await choose(wrapper, 0, 5)

    // A block of its own used to go without a question. It is still one thing leaving a page
    // by one click, and the row it left from says its type rather than its words — so what
    // vanished was never named.
    expect(emitted(wrapper)).toBeNull()
    expect(document.querySelector('.wx-confirm__message')).not.toBeNull()

    confirmIt()
    await flushPromises()

    expect(emitted(wrapper)!.map((node) => node.key)).toEqual(['b'])
  })

  it('switches a block off without touching what is in it', async () => {
    const wrapper = field()

    // Switching off stands second in the menu, after adding and before duplicate and remove.
    await choose(wrapper, 1, 1)

    const off = emitted(wrapper)!

    expect(off[1]!.hidden).toBe(true)
    expect((off[1]!.values.content as BlockNode[])[0]!.values.title).toBe('Inner')
    expect(off[0]!.hidden).toBeUndefined()
  })

  it('shows a switched-off block as switched off, and switching it on leaves no trace', async () => {
    const off = tree()
    off[1]!.hidden = true

    const wrapper = field(off)

    expect(wrapper.findAll('.wx-blocks-tree__row')[1]!.classes()).toContain('is-hidden')

    await choose(wrapper, 1, 1)

    // Back on the key is gone rather than false: the content is again what it was before
    // anybody hid it, which is what the revision guarding a save compares.
    expect('hidden' in emitted(wrapper)![1]!).toBe(false)
  })

  it('duplicates a block with fresh keys, right after the original', async () => {
    const wrapper = field()

    await choose(wrapper, 1, 2)

    const next = emitted(wrapper)!
    expect(next.length).toBe(3)
    expect(next[2]!.type).toBe('section')
    expect(next[2]!.key).not.toBe('b')
    expect((next[2]!.values.content as BlockNode[])[0]!.values.title).toBe('Inner')
  })

  /*
   * The button under the list only appends; the row's own `···` puts the new block right
   * under it, at the top level and inside a container alike.
   */
  it('adds a block right after the one whose menu was used', async () => {
    const wrapper = field()

    await choose(wrapper, 0, 0)
    document.querySelector<HTMLElement>('.wx-block-picker__card')!.click()
    await flushPromises()

    const top = emitted(wrapper)!
    expect(top.map((node) => node.key).slice(0, 1)).toEqual(['a'])
    expect(top[1]!.type).toBe('hero')
    expect(top[1]!.key).not.toBe('a')
    expect(top[2]!.key).toBe('b')
  })

  it('adds after a nested block inside the same container', async () => {
    const inner = tree()
    ;(inner[1]!.values.content as BlockNode[]).push({ key: 'd', type: 'hero', values: {} })

    const wrapper = field(inner)

    await choose(wrapper, 2, 0)
    document.querySelector<HTMLElement>('.wx-block-picker__card')!.click()
    await flushPromises()

    const kids = emitted(wrapper)![1]!.values.content as BlockNode[]
    expect(kids.length).toBe(3)
    expect(kids[0]!.key).toBe('c')
    expect(kids[2]!.key).toBe('d')
  })

  /* The same block, keys and all, in its new place — not a copy with the original removed. */
  it('moves a block into a container and back out to the page', async () => {
    const wrapper = field()

    await choose(wrapper, 0, 4)
    document.querySelector<HTMLElement>('.wx-block-move__place')!.click()
    await flushPromises()

    const inside = emitted(wrapper)!
    expect(inside.map((node) => node.key)).toEqual(['b'])
    expect((inside[0]!.values.content as BlockNode[]).map((node) => node.key)).toEqual(['c', 'a'])

    await wrapper.setProps({ modelValue: inside })
    await choose(wrapper, 1, 4)
    document.querySelector<HTMLElement>('.wx-block-move__place')!.click()
    await flushPromises()

    expect(emitted(wrapper)!.map((node) => node.key)).toEqual(['b', 'c'])
  })

  /*
   * The clip outlives the field: what was copied on one page is pasted on another, which is a
   * second field mounted over the same storage.
   */
  it('copies a block on one page and pastes it after a row on another, with fresh keys', async () => {
    const source = field()

    await choose(source, 1, 3)
    source.unmount()

    const target = field([{ key: 'x', type: 'hero', values: { title: 'Here' } }])

    // Copy is the fourth line; with something copied, "paste after" is the one under it.
    await choose(target, 0, 4)

    const next = emitted(target)!
    expect(next.map((node) => node.type)).toEqual(['hero', 'section'])
    expect(next[0]!.key).toBe('x')
    expect(next[1]!.key).not.toBe('b')
    const inner = next[1]!.values.content as BlockNode[]
    expect(inner[0]!.values.title).toBe('Inner')
    expect(inner[0]!.key).not.toBe('c')
  })

  it('copies the whole page from the head of the list and pastes it at the end', async () => {
    const wrapper = field()

    await wrapper.get('.wx-blocks__panel-head .wx-actions__menu button').trigger('click')
    document.querySelectorAll<HTMLElement>('.wx-dropdown-item')[0]!.click()
    await flushPromises()

    await wrapper.get('.wx-blocks__panel-head .wx-actions__menu button').trigger('click')
    document.querySelectorAll<HTMLElement>('.wx-dropdown-item')[1]!.click()
    await flushPromises()

    const next = emitted(wrapper)!
    expect(next.map((node) => node.type)).toEqual(['hero', 'section', 'hero', 'section'])
    expect(new Set(next.map((node) => node.key)).size).toBe(4)
  })

  /* Only a hero may go inside a section: a copied section stays out, and says why. */
  it('pastes into a container only what the container takes', async () => {
    const wrapper = field()

    await choose(wrapper, 0, 3)
    await choose(wrapper, 1, 4) // paste after
    expect(emitted(wrapper)!.map((node) => node.type)).toEqual(['hero', 'section', 'hero'])

    await wrapper.setProps({ modelValue: tree() })
    await choose(wrapper, 1, 3) // copy the section
    await choose(wrapper, 1, 5) // paste inside the section
    // Refused: the tree is the one it was, nothing emitted beyond the earlier paste.
    expect(emitted(wrapper)!.map((node) => node.type)).toEqual(['hero', 'section', 'hero'])
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

describe('WxBlocks shortcodes', () => {
  it('offers the shortcodes in the text fields of the open block, and nowhere else', async () => {
    const shortcodes = [{ name: 'phone', value: '+1 555 0100' }]
    const contact = type('contact', {
      content: {
        schema: [
          { id: 'title', type: 'wx-input', label: 'Title' },
          { id: 'mail', type: 'wx-input', label: 'Mail', props: { type: 'email' } },
          { id: 'items', type: 'wx-repeater', children: [{ id: 'caption', type: 'wx-textarea' }] },
        ],
        template: '',
        styles: '',
        script: null,
        sample: {},
      },
    })
    const wrapper = mount(BlocksField, {
      props: {
        modelValue: [{ key: 'a', type: 'contact', values: {} }],
        catalog: [contact],
        shortcodes,
        'onUpdate:modelValue': () => {},
      },
      global: { stubs: { RouterLink: true, WxScreenRenderer: true } },
    })

    await wrapper.findAll('.wx-blocks-tree__row')[0]!.trigger('click')

    const root = wrapper.findComponent({ name: 'WxScreenRenderer' }).props('root') as ScreenNode[]

    expect(root[0]!.props?.tokens).toEqual(shortcodes)
    expect(root[1]!.props?.tokens).toBeUndefined()
    expect(root[2]!.children![0]!.props?.tokens).toEqual(shortcodes)
  })
})
