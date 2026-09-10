import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxRichText from './RichText.vue'

type Wrapper = ReturnType<typeof mount>

/** The editor is created on mount, so every test starts by letting that settle. */
async function mountEditor(props: Record<string, unknown> = {}) {
  const wrapper = mount(WxRichText, { props, attachTo: document.body })
  await nextTick()
  await nextTick()
  return wrapper
}

/**
 * Tiptap's Vue layer holds editor state in a customRef whose trigger is deferred by
 * two animation frames, so the toolbar catches up a couple of frames after a
 * transaction — not on the next tick.
 */
function frame() {
  return new Promise((resolve) => requestAnimationFrame(() => resolve(null)))
}

async function flush() {
  await frame()
  await frame()
  await nextTick()
}

type EditorLike = {
  state: unknown
  commands: Record<string, (...args: unknown[]) => unknown>
  getHTML: () => string
  isEditable: boolean
}

function editorOf(wrapper: Wrapper): EditorLike {
  // Exposed refs are unwrapped on the instance proxy; tolerate both shapes.
  const exposed = (wrapper.vm as unknown as { editor: EditorLike | { value: EditorLike } }).editor
  return 'state' in exposed ? exposed : exposed.value
}

function toolByLabel(wrapper: Wrapper, label: string) {
  return wrapper.get(`button[aria-label="${label}"]`)
}

describe('WxRichText', () => {
  it('renders a toolbar and an editing surface', async () => {
    const wrapper = await mountEditor()

    expect(wrapper.find('[role="toolbar"]').exists()).toBe(true)
    expect(wrapper.find('.ProseMirror').exists()).toBe(true)
  })

  it('starts from the model value', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Hello</p>' })

    expect(editorOf(wrapper).getHTML()).toContain('Hello')
  })

  it('writes HTML back into the model', async () => {
    const wrapper = await mountEditor({ modelValue: '' })

    editorOf(wrapper).commands.setContent('<p>Typed</p>')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toContain('Typed')
    expect(wrapper.emitted('change')?.at(-1)?.[0]).toContain('Typed')
  })

  it('reports an empty document as an empty string, not <p></p>', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Something</p>' })

    editorOf(wrapper).commands.clearContent(true)
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
  })

  it('takes in a model change from outside', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>One</p>' })

    await wrapper.setProps({ modelValue: '<p>Two</p>' })
    await nextTick()

    expect(editorOf(wrapper).getHTML()).toContain('Two')
  })

  it('toggles marks from the toolbar', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Text</p>' })
    editorOf(wrapper).commands.selectAll()

    await toolByLabel(wrapper, 'Bold').trigger('click')
    await flush()

    expect(editorOf(wrapper).getHTML()).toContain('<strong>')
    expect(toolByLabel(wrapper, 'Bold').attributes('aria-pressed')).toBe('true')
  })

  it('turns a paragraph into a heading', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Title</p>' })

    await toolByLabel(wrapper, 'Heading 2').trigger('click')

    expect(editorOf(wrapper).getHTML()).toContain('<h2>')
  })

  it('makes a list', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Item</p>' })

    await toolByLabel(wrapper, 'Bulleted list').trigger('click')

    expect(editorOf(wrapper).getHTML()).toContain('<ul>')
  })

  it('inserts a table with a header row and shows the table controls', async () => {
    const wrapper = await mountEditor()

    await toolByLabel(wrapper, 'Table').trigger('click')
    await flush()

    const html = editorOf(wrapper).getHTML()
    expect(html).toContain('<table')
    expect(html).toContain('<th')
    expect(wrapper.find('.wx-rich-text__toolbar--table').exists()).toBe(true)
  })

  it('adds a row through the table controls', async () => {
    const wrapper = await mountEditor()
    await toolByLabel(wrapper, 'Table').trigger('click')
    await flush()

    const before = (editorOf(wrapper).getHTML().match(/<tr>/g) ?? []).length
    await toolByLabel(wrapper, 'Row below').trigger('click')
    await flush()

    const after = (editorOf(wrapper).getHTML().match(/<tr>/g) ?? []).length
    expect(after).toBe(before + 1)
  })

  it('applies a link through the prompt bar', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Docs</p>' })
    editorOf(wrapper).commands.selectAll()

    await toolByLabel(wrapper, 'Link').trigger('click')
    await wrapper.get('.wx-rich-text__prompt-input').setValue('https://example.com')
    await toolByLabel(wrapper, 'Apply').trigger('click')

    expect(editorOf(wrapper).getHTML()).toContain('href="https://example.com"')
    expect(wrapper.find('.wx-rich-text__prompt').exists()).toBe(false)
  })

  it('gives outgoing links safe rel attributes', async () => {
    const wrapper = await mountEditor({ modelValue: '<p>Docs</p>' })
    editorOf(wrapper).commands.selectAll()

    await toolByLabel(wrapper, 'Link').trigger('click')
    await wrapper.get('.wx-rich-text__prompt-input').setValue('https://example.com')
    await toolByLabel(wrapper, 'Apply').trigger('click')

    expect(editorOf(wrapper).getHTML()).toContain('rel="noopener noreferrer nofollow"')
  })

  it('removes a link when the address is emptied', async () => {
    const wrapper = await mountEditor({
      modelValue: '<p><a href="https://example.com">Docs</a></p>',
    })
    editorOf(wrapper).commands.selectAll()

    await toolByLabel(wrapper, 'Link').trigger('click')
    await wrapper.get('.wx-rich-text__prompt-input').setValue('')
    await toolByLabel(wrapper, 'Apply').trigger('click')

    expect(editorOf(wrapper).getHTML()).not.toContain('<a')
  })

  it('embeds a YouTube video', async () => {
    const wrapper = await mountEditor()

    await toolByLabel(wrapper, 'YouTube video').trigger('click')
    await wrapper.get('.wx-rich-text__prompt-input').setValue('https://youtu.be/dQw4w9WgXcQ')
    await toolByLabel(wrapper, 'Apply').trigger('click')

    expect(editorOf(wrapper).getHTML()).toContain('youtube-nocookie.com')
  })

  it('hides the image button when there is nowhere to get an image from', async () => {
    const wrapper = await mountEditor()

    expect(wrapper.find('button[aria-label="Image"]').exists()).toBe(false)
  })

  it('inserts the image chosen in a media library', async () => {
    const pickImage = vi.fn().mockResolvedValue('/uploads/photo.jpg')
    const wrapper = await mountEditor({ pickImage })

    await toolByLabel(wrapper, 'Image').trigger('click')
    await nextTick()
    await nextTick()

    expect(pickImage).toHaveBeenCalled()
    expect(editorOf(wrapper).getHTML()).toContain('src="/uploads/photo.jpg"')
  })

  it('inserts nothing when the media library is cancelled', async () => {
    const pickImage = vi.fn().mockResolvedValue(null)
    const wrapper = await mountEditor({ pickImage })

    await toolByLabel(wrapper, 'Image').trigger('click')
    await nextTick()

    expect(editorOf(wrapper).getHTML()).not.toContain('<img')
  })

  it('uploads a picked file and inserts the returned URL', async () => {
    const upload = vi.fn().mockResolvedValue({ url: '/uploads/pasted.png', alt: 'Pasted' })
    const wrapper = await mountEditor({ upload })
    const file = new File(['x'], 'pasted.png', { type: 'image/png' })

    const input = wrapper.get('input[type="file"]').element as HTMLInputElement
    Object.defineProperty(input, 'files', { value: [file], configurable: true })
    await wrapper.get('input[type="file"]').trigger('change')
    await nextTick()
    await nextTick()

    expect(upload).toHaveBeenCalledWith(file)
    expect(editorOf(wrapper).getHTML()).toContain('src="/uploads/pasted.png"')
  })

  it('reports a failed upload and leaves the document alone', async () => {
    const upload = vi.fn().mockRejectedValue(new Error('Too large'))
    const wrapper = await mountEditor({ upload })
    const file = new File(['x'], 'big.png', { type: 'image/png' })

    const input = wrapper.get('input[type="file"]').element as HTMLInputElement
    Object.defineProperty(input, 'files', { value: [file], configurable: true })
    await wrapper.get('input[type="file"]').trigger('change')
    await nextTick()
    await nextTick()

    expect(wrapper.emitted('uploadError')?.[0]?.[1]).toBe(file)
    expect(editorOf(wrapper).getHTML()).not.toContain('<img')
  })

  it('is not editable when disabled, and its tools are off', async () => {
    const wrapper = await mountEditor({ disabled: true })

    expect(editorOf(wrapper).isEditable).toBe(false)
    expect(toolByLabel(wrapper, 'Bold').attributes('disabled')).toBeDefined()
    expect(wrapper.classes()).toContain('is-disabled')
  })

  it('is not editable when readonly', async () => {
    const wrapper = await mountEditor({ readonly: true })

    expect(editorOf(wrapper).isEditable).toBe(false)
  })

  it('shows the placeholder only while empty', async () => {
    const wrapper = await mountEditor({ placeholder: 'Write something' })
    expect(wrapper.get('.wx-rich-text__placeholder').text()).toBe('Write something')

    editorOf(wrapper).commands.setContent('<p>Filled</p>')
    await flush()

    expect(wrapper.find('.wx-rich-text__placeholder').exists()).toBe(false)
  })

  it('takes the error state from a form item', async () => {
    const wrapper = await mountEditor({ status: 'error' })

    expect(wrapper.classes()).toContain('wx-rich-text--error')
  })

  it('renders only the tools it was given', async () => {
    const wrapper = await mountEditor({ tools: ['bold', 'italic'] })

    expect(wrapper.findAll('.wx-rich-text__tool')).toHaveLength(2)
  })
})
