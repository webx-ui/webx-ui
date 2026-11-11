import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import EmbedField from './EmbedField.vue'
import { adminTypes } from './screenTypes'

/**
 * `wx-embed` as an editor meets it: a textarea the code is pasted into, as it is — the server
 * keeps the address and refuses another site's frame — with the node's `source` kept off the DOM.
 */
describe('WxEmbedField', () => {
  it('hands on what is pasted, as it is', async () => {
    const wrapper = mount(EmbedField, {
      props: { modelValue: '', source: 'google-maps', rows: 4, placeholder: '<iframe …>' },
    })
    const textarea = wrapper.find('textarea')
    const code = '<iframe src="https://www.google.com/maps/embed?pb=!1m18"></iframe>'

    await textarea.setValue(code)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([code])
    expect(textarea.attributes('rows')).toBe('4')
    expect(textarea.attributes('placeholder')).toBe('<iframe …>')
    expect(wrapper.html()).not.toContain('google-maps')
    wrapper.unmount()
  })

  it('is a wide field of the panel', () => {
    expect(adminTypes['wx-embed']).toMatchObject({
      component: EmbedField,
      kind: 'field',
      wide: true,
    })
  })
})
