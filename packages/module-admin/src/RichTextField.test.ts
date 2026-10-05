import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
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
    modelValue: { type: [String, Object], default: undefined },
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

  describe('pictures from the library', () => {
    const old =
      '<p>Look</p><img src="https://old.test/storage/a.webp?v=1" data-wx-path="media/a.webp"><img src="https://cdn.test/b.webp">'

    function opened(
      modelValue: unknown,
      assetUrls: AdminContext['assetUrls'] = (paths) =>
        Promise.resolve(
          Object.fromEntries(paths.map((path) => [path, `https://new.test/storage/${path}?v=2`])),
        ),
    ) {
      const i18n = createI18n({ locale: 'en' })
      i18n.defaults('webx-admin', adminMessages)

      return mount(RichTextField, {
        props: { modelValue },
        global: {
          provide: {
            [adminKey as symbol]: { pickImage: null, assetUrls } as AdminContext,
            [i18nKey as symbol]: i18n,
          },
          stubs: { WxRichText: Editor },
        },
      })
    }

    async function settled() {
      await Promise.resolve()
      await nextTick()
    }

    it('points a saved picture at where its key lives now, and leaves other pictures alone', async () => {
      const wrapper = opened(old)
      await settled()

      const html = wrapper.getComponent(Editor).props('modelValue') as string

      expect(html).toContain('src="https://new.test/storage/media/a.webp?v=2"')
      expect(html).toContain('src="https://cdn.test/b.webp"')
      expect(html).not.toContain('old.test')
    })

    it('does the same in every language of a localized value', async () => {
      const wrapper = opened({ en: old, de: '<p>Ohne Bild</p>' })
      await settled()

      const value = wrapper.getComponent(Editor).props('modelValue') as Record<string, string>

      expect(value.en).toContain('new.test')
      expect(value.de).toBe('<p>Ohne Bild</p>')
    })

    it('keeps the saved address of a key the library no longer has', async () => {
      const wrapper = opened(old, (paths) =>
        Promise.resolve(Object.fromEntries(paths.map((path) => [path, null]))),
      )
      await settled()

      expect(wrapper.getComponent(Editor).props('modelValue')).toBe(old)
    })

    it('does not ask the library about what the editor itself sent', async () => {
      let asked = 0
      const wrapper = opened('<p>Nothing</p>', (paths) => {
        asked++
        return Promise.resolve(
          Object.fromEntries(paths.map((path) => [path, 'https://new.test/x'])),
        )
      })

      wrapper.getComponent(Editor).vm.$emit('update:modelValue', old)
      // Undeclared on purpose (it travels as an attribute), so the props type does not know it.
      await wrapper.setProps({ modelValue: old } as never)
      await settled()

      expect(asked).toBe(0)
      expect(wrapper.getComponent(Editor).props('modelValue')).toBe(old)
    })

    it('shows the document as saved on a panel without a library', async () => {
      const wrapper = opened(old, null)
      await settled()

      expect(wrapper.getComponent(Editor).props('modelValue')).toBe(old)
    })
  })
})
