import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref } from 'vue'
import { runScopeHandlers, type EditorView } from '@codemirror/view'
import { currentCompletions, startCompletion } from '@codemirror/autocomplete'
import { provideLocales } from '../../composables/useLocalized'
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

  it('paints the tags of a Mustache template', async () => {
    const wrapper = await mountEditor({
      language: 'mustache-html',
      modelValue: '<b>{{form}}</b> {{#items}}x{{/items}} {{! note }} {{>footer}}',
    })

    expect(wrapper.findAll('.wx-cm-mustache').map((mark) => mark.text())).toEqual([
      '{{form}}',
      '{{#items}}',
      '{{/items}}',
      '{{! note }}',
      '{{>footer}}',
    ])
    expect(wrapper.find('.wx-cm-mustache--section').text()).toBe('{{#items}}')
    expect(wrapper.find('.wx-cm-mustache--invalid').text()).toBe('{{>footer}}')
  })

  it('suggests from the completions it is given', async () => {
    const wrapper = await mountEditor({
      modelValue: 'fo',
      completions: [{ label: 'form', detail: 'variable' }, { label: 'note' }],
    })
    const view = viewOf(wrapper)
    view.dispatch({ selection: { anchor: 2 } })

    startCompletion(view)
    await vi.waitFor(() => expect(currentCompletions(view.state).length).toBeGreaterThan(0))

    expect(currentCompletions(view.state).map((one) => one.label)).toEqual(['form'])
  })

  it('asks a completer about the place and writes a snippet over the tail it names', async () => {
    // The `}}` after the caret is what closing brackets typed: the suggestion takes it over, and
    // the list is still matched against what was typed, not against that tail.
    const wrapper = await mountEditor({
      modelValue: '{{}}',
      completions: ({ text, pos }: { text: string; pos: number }) =>
        text.slice(0, pos).endsWith('{{')
          ? {
              from: pos,
              to: pos + 2,
              options: [{ label: 'items', apply: '#items}}${}{{/items}}' }],
            }
          : null,
    })
    const view = viewOf(wrapper)
    view.dispatch({ selection: { anchor: 2 } })

    startCompletion(view)
    await vi.waitFor(() => expect(currentCompletions(view.state)).toHaveLength(1))

    const chosen = currentCompletions(view.state)[0]!
    const apply = chosen.apply as (
      view: EditorView,
      completion: unknown,
      from: number,
      to: number,
    ) => void
    apply(view, chosen, 2, 2)

    expect(docOf(wrapper)).toBe('{{#items}}{{/items}}')
    expect(view.state.selection.main.head).toBe(10)
  })

  it('keeps one line when single-line: Enter takes a suggestion and breaks no line', async () => {
    const wrapper = await mountEditor({
      modelValue: 'New: fo',
      singleLine: true,
      completions: [{ label: 'form' }],
    })
    const view = viewOf(wrapper)
    const enter = () =>
      runScopeHandlers(view, new KeyboardEvent('keydown', { key: 'Enter' }), 'editor')

    expect(wrapper.find('.wx-code-editor').classes()).toContain('is-single-line')

    // A line break pasted or typed in is a space.
    view.dispatch({ changes: { from: 0, insert: 'One\ntwo ' } })
    expect(docOf(wrapper)).toBe('One two New: fo')

    view.dispatch({ selection: { anchor: view.state.doc.length } })
    expect(enter()).toBe(true)
    expect(docOf(wrapper)).toBe('One two New: fo')

    startCompletion(view)
    await vi.waitFor(() => expect(currentCompletions(view.state)).toHaveLength(1))
    await new Promise((resolve) => setTimeout(resolve, 100))

    enter()
    expect(docOf(wrapper)).toBe('One two New: form')
    expect(view.state.doc.lines).toBe(1)
  })

  it('edits one language at a time when localized', async () => {
    const model = ref<Record<string, string>>({ en: 'Hello', uk: 'Привіт' })
    const Host = defineComponent({
      setup() {
        provideLocales(ref([{ code: 'en' }, { code: 'uk' }]))

        return () =>
          h(WxCodeEditor, {
            localized: true,
            modelValue: model.value,
            'onUpdate:modelValue': (value: unknown) =>
              (model.value = value as Record<string, string>),
          })
      },
    })
    const host = mount(Host, { attachTo: document.body })
    await nextTick()
    const editor = host.findComponent(WxCodeEditor)

    expect(docOf(editor as unknown as Wrapper)).toBe('Hello')
    expect(editor.classes()).toContain('is-localized')

    await host.findAll('.wx-locale-picker__code')[1]!.trigger('click')
    await nextTick()

    expect(docOf(editor as unknown as Wrapper)).toBe('Привіт')
    // Swapping the language is not an edit of it.
    expect(model.value).toEqual({ en: 'Hello', uk: 'Привіт' })

    typeInto(editor as unknown as Wrapper, '!')
    await nextTick()

    expect(model.value).toEqual({ en: 'Hello', uk: 'Привіт!' })
  })
})
