import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { adminKey, type AdminContext } from './admin'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import RichTextField from './RichTextField.vue'

/** The editor is Tiptap; what is under test is what the field hands it. */
const Editor = defineComponent({
  name: 'WxRichText',
  props: {
    labels: { type: Object, default: undefined },
    pickImage: { type: Function, default: undefined },
  },
  template: '<div class="editor" />',
})

function field(pickImage: AdminContext['pickImage'] = null) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-admin', adminMessages)

  return mount(RichTextField, {
    props: { modelValue: '<p>Hello</p>', localized: true },
    global: {
      provide: { [adminKey as symbol]: { pickImage } as AdminContext, [i18nKey as symbol]: i18n },
      stubs: { WxRichText: Editor },
    },
  })
}

describe('WxRichTextField', () => {
  it('hands the editor the panel’s words', () => {
    const labels = field().getComponent(Editor).props('labels') as Record<string, string>

    expect(labels.bold).toBe('Bold')
    expect(labels.mergeOrSplit).toBe('Merge or split cells')
    expect(labels.uploading).toBe('Uploading…')
  })

  it('offers the library when a module has one, and nothing when none does', () => {
    const picker = () => Promise.resolve({ url: '/files/one.png', path: '2026/09/one.png' })

    expect(field(picker).getComponent(Editor).props('pickImage')).toBe(picker)
    // `undefined` and not `null`: the editor draws no image button when it is handed nothing,
    // and a `null` prop would be a value it has to check for instead.
    expect(field().getComponent(Editor).props('pickImage')).toBeUndefined()
  })

  it('passes the node’s own props straight through', () => {
    // Nothing of the editor's is declared here, so `localized` arrives as it was written
    // rather than as the `false` an undeclared boolean prop would be coerced into.
    expect(field().getComponent(Editor).attributes('localized')).toBe('true')
  })
})
