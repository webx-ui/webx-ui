import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import type { EditorView } from '@codemirror/view'
import WxCodeEditor from './CodeEditor.vue'
import type { CodeEditorDiagnostic } from './types'

type Wrapper = ReturnType<typeof mount>

/** The editor is created on mount, so every test starts by letting that settle. */
async function mountEditor(props: Record<string, unknown> = {}) {
  const wrapper = mount(WxCodeEditor, { props, attachTo: document.body })
  await nextTick()
  return wrapper
}

function viewOf(wrapper: Wrapper): EditorView {
  // Exposed refs are unwrapped on the instance proxy; tolerate both shapes.
  const exposed = (wrapper.vm as unknown as { view: EditorView | { value: EditorView } }).view
  return 'state' in exposed ? exposed : exposed.value
}

function docOf(wrapper: Wrapper): string {
  return viewOf(wrapper).state.doc.toString()
}

/** Types through the editor's own dispatch — what a keystroke ends up as. */
function typeInto(wrapper: Wrapper, text: string) {
  const view = viewOf(wrapper)
  view.dispatch({ changes: { from: view.state.doc.length, insert: text } })
}

describe('WxCodeEditor', () => {
  it('renders an editing surface with line numbers', async () => {
    const wrapper = await mountEditor()

    expect(wrapper.find('.cm-editor').exists()).toBe(true)
    expect(wrapper.find('.cm-lineNumbers').exists()).toBe(true)
  })

  it('starts from the model value', async () => {
    const wrapper = await mountEditor({ modelValue: '{"a": 1}' })

    expect(docOf(wrapper)).toBe('{"a": 1}')
  })

  it('updates the model and emits change when the document changes', async () => {
    const wrapper = await mountEditor({ modelValue: 'a' })
    typeInto(wrapper, 'b')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['ab'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['ab'])
  })

  it('follows the model when it changes from outside', async () => {
    const wrapper = await mountEditor({ modelValue: 'one' })
    await wrapper.setProps({ modelValue: 'two' })

    expect(docOf(wrapper)).toBe('two')
  })

  it('does not replace the document with an identical value', async () => {
    const wrapper = await mountEditor({ modelValue: 'same' })
    const dispatch = vi.spyOn(viewOf(wrapper), 'dispatch')
    await wrapper.setProps({ modelValue: 'same' })

    expect(dispatch).not.toHaveBeenCalled()
  })

  it('hides the gutter when line numbers are off', async () => {
    const wrapper = await mountEditor({ lineNumbers: false })

    expect(wrapper.find('.cm-lineNumbers').exists()).toBe(false)
  })

  it('shows a placeholder while empty', async () => {
    const wrapper = await mountEditor({ placeholder: 'Paste JSON…' })

    expect(wrapper.find('.cm-placeholder').text()).toBe('Paste JSON…')
  })

  it('is not editable when readonly or disabled', async () => {
    const readonly = await mountEditor({ readonly: true })
    expect(viewOf(readonly).state.readOnly).toBe(true)
    expect(readonly.classes()).toContain('is-readonly')

    const disabled = await mountEditor({ disabled: true })
    expect(viewOf(disabled).state.readOnly).toBe(true)
    expect(disabled.classes()).toContain('is-disabled')
    expect(disabled.get('.cm-content').attributes('tabindex')).toBe('-1')
  })

  it('reconfigures when props change after mount', async () => {
    const wrapper = await mountEditor({ modelValue: 'x' })
    await wrapper.setProps({ readonly: true })
    expect(viewOf(wrapper).state.readOnly).toBe(true)

    await wrapper.setProps({ readonly: false, lineNumbers: false })
    expect(viewOf(wrapper).state.readOnly).toBe(false)
    expect(wrapper.find('.cm-lineNumbers').exists()).toBe(false)
  })

  it('passes the accessible label and id to the editing surface', async () => {
    const wrapper = await mountEditor({ id: 'body', ariaLabel: 'Body' })
    const content = wrapper.get('.cm-content')

    expect(content.attributes('id')).toBe('body')
    expect(content.attributes('aria-label')).toBe('Body')
  })

  it('reports JSON problems through the lint event', async () => {
    vi.useFakeTimers()
    try {
      const wrapper = await mountEditor({ language: 'json', modelValue: '{"a": }' })
      await vi.advanceTimersByTimeAsync(1000)
      const reported = wrapper.emitted('lint')?.at(-1)?.[0] as CodeEditorDiagnostic[]

      expect(reported).toHaveLength(1)
      expect(reported[0]?.severity).toBe('error')

      await wrapper.setProps({ modelValue: '{"a": 1}' })
      await vi.advanceTimersByTimeAsync(1000)

      expect(wrapper.emitted('lint')?.at(-1)?.[0]).toEqual([])
    } finally {
      vi.useRealTimers()
    }
  })

  it('formats JSON and refuses anything else', async () => {
    const wrapper = await mountEditor({ language: 'json', modelValue: '{"a":[1,2]}' })
    const format = (wrapper.vm as unknown as { format: () => boolean }).format

    expect(format()).toBe(true)
    expect(docOf(wrapper)).toBe('{\n  "a": [\n    1,\n    2\n  ]\n}')

    await wrapper.setProps({ modelValue: '{"a":' })
    expect(format()).toBe(false)
    expect(docOf(wrapper)).toBe('{"a":')

    await wrapper.setProps({ language: 'yaml', modelValue: 'a: 1' })
    expect(format()).toBe(false)
  })

  it('applies the size and status classes', async () => {
    const wrapper = await mountEditor({ size: 'sm', status: 'error' })

    expect(wrapper.classes()).toContain('wx-code-editor--sm')
    expect(wrapper.classes()).toContain('wx-code-editor--error')
    expect(wrapper.get('.cm-content').attributes('aria-invalid')).toBe('true')
  })

  it('destroys the editor on unmount', async () => {
    const wrapper = await mountEditor()
    const destroy = vi.spyOn(viewOf(wrapper), 'destroy')
    wrapper.unmount()

    expect(destroy).toHaveBeenCalled()
  })
})
