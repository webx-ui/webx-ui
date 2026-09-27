import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import BlockDataField from './BlockDataField.vue'

/** The code editor stood in by a textarea: CodeMirror is heavy, and the JSON is the point. */
const CodeEditor = {
  name: 'WxCodeEditor',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template:
    '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
}

function field(modelValue: unknown) {
  return mount(BlockDataField, {
    props: { modelValue },
    global: { stubs: { WxCodeEditor: CodeEditor }, provide: panel() },
  })
}

/** The panel's dictionary: the English this package seeds, not the keys. */
function panel() {
  const i18n = createI18n()
  const admin = { i18n } as unknown as AdminContext

  return { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n }
}

describe('wx-data in the sample form', () => {
  it('shows the structure as JSON', () => {
    const wrapper = field({ title: 'Porridge', minutes: 15 })

    expect(JSON.parse((wrapper.get('textarea').element as HTMLTextAreaElement).value)).toEqual({
      title: 'Porridge',
      minutes: 15,
    })
  })

  it('hands on what parses', async () => {
    const wrapper = field({})

    await wrapper.get('textarea').setValue('{ "title": "Soup" }')

    expect(wrapper.emitted('update:modelValue')).toEqual([[{ title: 'Soup' }]])
    expect(wrapper.text()).toBe('')
  })

  /* Half a bracket is what the text looks like on the way to the next valid JSON: the sample
     must not turn into nothing, and redraw the stage empty, on every keystroke. */
  it('keeps the value and says why while the text does not parse', async () => {
    const wrapper = field({ title: 'Porridge' })

    await wrapper.get('textarea').setValue('{ "title": ')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.text()).toContain('Not valid JSON')
  })

  /* The call that passes nothing is a case a component has to survive, and the sample is where
     that is tried: an empty editor is `null`, not an error. */
  it('takes an empty editor for null, and shows null as an empty editor', async () => {
    const wrapper = field({ title: 'Porridge' })

    await wrapper.get('textarea').setValue('  ')

    expect(wrapper.emitted('update:modelValue')).toEqual([[null]])
    expect(wrapper.text()).toBe('')

    await wrapper.setProps({ modelValue: null })

    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('  ')
    expect((field(null).get('textarea').element as HTMLTextAreaElement).value).toBe('')
  })

  it('keeps a typed null rather than turning it into an empty object', async () => {
    const wrapper = field({ title: 'Porridge' })

    await wrapper.get('textarea').setValue('null')
    await wrapper.setProps({ modelValue: null })

    expect(wrapper.emitted('update:modelValue')).toEqual([[null]])
    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('null')
  })

  it('takes a new value from outside, and not the echo of what was typed', async () => {
    const wrapper = field({ a: 1 })

    await wrapper.get('textarea').setValue('{"a":   1}')
    await wrapper.setProps({ modelValue: { a: 1 } })

    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('{"a":   1}')

    await wrapper.setProps({ modelValue: { b: 2 } })

    expect(JSON.parse((wrapper.get('textarea').element as HTMLTextAreaElement).value)).toEqual({
      b: 2,
    })
  })
})
