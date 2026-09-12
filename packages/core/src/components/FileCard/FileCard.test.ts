import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxFileCard from './FileCard.vue'
import { extensionOf, fileIconName, isPicture } from './files'
import { registerIcons } from '../Icon/icons'

function card(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxFileCard, {
    attachTo: document.body,
    props: { name: 'spare-parts.xlsx', ...props },
    slots,
  })
}

const glyph = (wrapper: VueWrapper) => wrapper.find('.wx-file-card__glyph')
const name = (wrapper: VueWrapper) => wrapper.find('.wx-file-card__name')

/** The action whose tooltip and accessible name is `label`. */
const action = (wrapper: VueWrapper, label: string) =>
  wrapper.findAll('button').find((button) => button.attributes('title') === label)

describe('files', () => {
  it('reads an extension off a name, a path or a URL', () => {
    expect(extensionOf('spare-parts.xlsx')).toBe('xlsx')
    expect(extensionOf('/media/2026/photo.JPG')).toBe('jpg')
    expect(extensionOf('https://cdn.example/photo.jpg?v=2#top')).toBe('jpg')
  })

  it('has nothing to say about a name without one', () => {
    expect(extensionOf('README')).toBe('')
    // A dotfile is not an extension, however much it looks like one.
    expect(extensionOf('.gitignore')).toBe('')
  })

  it('believes the type over the name', () => {
    expect(isPicture('photo.jpg')).toBe(true)
    expect(isPicture('mystery.bin', 'image/png')).toBe(true)
    expect(isPicture('pretend.jpg', 'application/pdf')).toBe(false)
  })

  it('leaves the formats a browser will not draw to their glyph', () => {
    expect(isPicture('scan.tiff')).toBe(false)
    expect(isPicture('scan.tiff', 'image/tiff')).toBe(false)
    expect(isPicture('logo.svg', 'image/svg+xml')).toBe(true)
  })

  it('picks the icon the extension is named after, and the plain page for the rest', () => {
    expect(fileIconName('spare-parts.xlsx')).toBe('file-xlsx')
    expect(fileIconName('notes.md')).toBe('file-md')
    expect(fileIconName('field.sketch')).toBe('file-sketch')
    expect(fileIconName('drawing.dwg')).toBe('file-generic')
  })

  it('finds an icon an application registered, without being told about it', () => {
    registerIcons({ 'file-dwg': '<path d="M4 4h16v16H4z"/>' })

    expect(fileIconName('drawing.dwg')).toBe('file-dwg')
  })
})

describe('WxFileCard', () => {
  it('draws a glyph and the extension for a file that is not a picture', () => {
    const wrapper = card()

    expect(glyph(wrapper).exists()).toBe(true)
    expect(wrapper.get('.wx-file-card__extension').text()).toBe('xlsx')
    expect(wrapper.find('.wx-file-card__picture').exists()).toBe(false)
  })

  it('draws the picture for one, and prefers the thumbnail to the file', () => {
    const wrapper = card({
      name: 'photo.jpg',
      url: '/media/photo.jpg',
      thumbnail: '/media/photo-small.jpg',
    })

    expect(wrapper.get('img').attributes('src')).toBe('/media/photo-small.jpg')
    expect(wrapper.get('img').attributes('alt')).toBe('photo.jpg')
  })

  it('has nothing to draw a picture from, so draws the glyph', () => {
    const wrapper = card({ name: 'photo.jpg' })

    expect(glyph(wrapper).exists()).toBe(true)
  })

  it('shows the name, and nothing but the offered actions', () => {
    const wrapper = card()

    expect(name(wrapper).text()).toBe('spare-parts.xlsx')
    expect(wrapper.findAll('button')).toHaveLength(0)
  })

  /* -------------------------------------------------------------- actions */

  it('offers the actions it was asked for', () => {
    const wrapper = card({ url: '/f.xlsx', renamable: true, removable: true, copyable: true })

    expect(action(wrapper, 'Rename')).toBeDefined()
    expect(action(wrapper, 'Delete')).toBeDefined()
    expect(action(wrapper, 'Copy link')).toBeDefined()
  })

  it('has nothing to edit in a spreadsheet', () => {
    expect(action(card({ editable: true }), 'Edit picture')).toBeUndefined()
    expect(action(card({ name: 'p.jpg', editable: true }), 'Edit picture')).toBeDefined()
  })

  it('has nothing to copy without a URL', () => {
    expect(action(card({ copyable: true }), 'Copy link')).toBeUndefined()
  })

  it('takes every action away when the file may not be touched', () => {
    const wrapper = card({
      url: '/f.xlsx',
      renamable: true,
      removable: true,
      copyable: true,
      disabled: true,
    })

    expect(wrapper.findAll('button')).toHaveLength(0)
  })

  it('reports a deletion rather than doing one', async () => {
    const wrapper = card({ removable: true })

    await action(wrapper, 'Delete')!.trigger('click')

    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('asks for an editor rather than being one', async () => {
    const wrapper = card({ name: 'photo.jpg', editable: true })

    await action(wrapper, 'Edit picture')!.trigger('click')

    expect(wrapper.emitted('edit')).toHaveLength(1)
  })

  /* ------------------------------------------------------------- renaming */

  it('edits the name in place and reports the new one', async () => {
    const wrapper = card({ renamable: true })

    await action(wrapper, 'Rename')!.trigger('click')
    await nextTick()

    const field = wrapper.get('.wx-file-card__field input')
    await field.setValue('parts-2026.xlsx')
    await field.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('rename')?.at(-1)).toEqual(['parts-2026.xlsx'])
    /* It reports, it does not rename: the name is still the one it was given. */
    expect(name(wrapper).text()).toBe('spare-parts.xlsx')
  })

  it('says nothing when the name did not change, or was emptied', async () => {
    const wrapper = card({ renamable: true })

    await action(wrapper, 'Rename')!.trigger('click')
    await nextTick()
    await wrapper.get('.wx-file-card__field input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('rename')).toBeUndefined()

    await action(wrapper, 'Rename')!.trigger('click')
    await nextTick()
    await wrapper.get('.wx-file-card__field input').setValue('   ')
    await wrapper.get('.wx-file-card__field input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('rename')).toBeUndefined()
  })

  it('puts the name back on escape', async () => {
    const wrapper = card({ renamable: true })

    await action(wrapper, 'Rename')!.trigger('click')
    await nextTick()
    const field = wrapper.get('.wx-file-card__field input')
    await field.setValue('something-else.xlsx')
    await field.trigger('keydown', { key: 'Escape' })

    expect(wrapper.emitted('rename')).toBeUndefined()
    expect(name(wrapper).text()).toBe('spare-parts.xlsx')
  })

  it('starts a rename on a double click, and not when it was not offered', async () => {
    const offered = card({ renamable: true })
    await name(offered).trigger('dblclick')
    await nextTick()
    expect(offered.find('.wx-file-card__field').exists()).toBe(true)

    const plain = card()
    await name(plain).trigger('dblclick')
    await nextTick()
    expect(plain.find('.wx-file-card__field').exists()).toBe(false)
  })

  /* ------------------------------------------------------------ clipboard */

  describe('copying', () => {
    const writeText = vi.fn()

    beforeEach(() => {
      writeText.mockReset().mockResolvedValue(undefined)
      Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    })

    afterEach(() => {
      Reflect.deleteProperty(navigator, 'clipboard')
    })

    it('puts the URL on the clipboard and says so', async () => {
      const wrapper = card({ url: 'https://cdn.example/f.xlsx', copyable: true })

      await action(wrapper, 'Copy link')!.trigger('click')
      await nextTick()

      expect(writeText).toHaveBeenCalledWith('https://cdn.example/f.xlsx')
      expect(wrapper.emitted('copy')?.at(-1)).toEqual(['https://cdn.example/f.xlsx'])
      /* And says so on the button, for a moment. */
      expect(action(wrapper, 'Copied')).toBeDefined()
    })

    it('reports the refusal rather than a copy that never happened', async () => {
      writeText.mockRejectedValue(new Error('denied'))
      const wrapper = card({ url: '/f.xlsx', copyable: true })

      await action(wrapper, 'Copy link')!.trigger('click')
      await nextTick()

      expect(wrapper.emitted('copy')).toBeUndefined()
      expect(wrapper.emitted('copy-error')).toHaveLength(1)
    })

    it('reports one when there is no clipboard at all', async () => {
      Reflect.deleteProperty(navigator, 'clipboard')
      const wrapper = card({ url: '/f.xlsx', copyable: true })

      await action(wrapper, 'Copy link')!.trigger('click')
      await nextTick()

      expect(wrapper.emitted('copy')).toBeUndefined()
      expect(wrapper.emitted('copy-error')).toHaveLength(1)
    })
  })

  /* ----------------------------------------------------------------- rest */

  it('draws itself as chosen without deciding that it is', () => {
    expect(card({ selected: true }).classes()).toContain('is-selected')
    expect(card().classes()).not.toContain('is-selected')
  })

  it('takes the glyph it is handed over the one the extension picks', () => {
    const wrapper = card({ icon: 'star' })

    expect(glyph(wrapper).exists()).toBe(true)
    expect(wrapper.find('.wx-file-card__extension').text()).toBe('xlsx')
  })

  it('renders a preview of its own when given one', () => {
    const wrapper = card({}, { preview: '<span class="mine">A still</span>' })

    expect(wrapper.get('.mine').text()).toBe('A still')
    expect(wrapper.find('.wx-file-card__glyph').exists()).toBe(false)
  })
})
