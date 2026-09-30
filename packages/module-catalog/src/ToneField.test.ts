import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { localesKey } from '@webx-ui/core'
import { categoryEditorKey } from '@webx-ui/module-admin'
import ToneField from './ToneField.vue'

const options = [
  { label: 'Neutral', value: 'neutral' },
  { label: 'Primary', value: 'primary' },
  { label: 'Success', value: 'success' },
  { label: 'Warning', value: 'warning' },
  { label: 'Danger', value: 'danger' },
  { label: 'Info', value: 'info' },
]

function field(modelValue: string | null, title: Record<string, string> | null = null) {
  return mount(ToneField, {
    props: { modelValue, options },
    global: {
      provide: {
        [localesKey as symbol]: { list: ref([{ code: 'en', name: 'English' }]), active: ref('en') },
        ...(title === null
          ? {}
          : {
              [categoryEditorKey as symbol]: {
                category: ref(null),
                values: ref({ title }),
                prefix: ref(null),
                moving: () => '',
              },
            }),
      },
    },
  })
}

describe('WxCatalogToneField', () => {
  it('offers the six tones, each a tag of its own tone, and marks the chosen one', () => {
    const wrapper = field('danger')
    const radios = wrapper.findAll('[role="radio"]')

    expect(radios.map((one) => one.text())).toEqual(options.map((one) => one.label))
    expect(radios.map((one) => one.attributes('aria-checked'))).toEqual([
      'false',
      'false',
      'false',
      'false',
      'true',
      'false',
    ])
    // `neutral` is the core's `default`; the rest are the badge's types by name.
    expect(radios[0]!.find('.wx-badge').classes()).toContain('wx-badge--default')
    expect(radios[4]!.find('.wx-badge').classes()).toContain('wx-badge--solid')
  })

  it('picks a tone by a click and by the arrows', async () => {
    const wrapper = field('neutral')

    await wrapper.find('[data-tone="success"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['success'])

    await wrapper.setProps({ modelValue: 'success' })
    await wrapper.find('[data-tone="success"]').trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['warning'])

    await wrapper.setProps({ modelValue: 'neutral' })
    await wrapper.find('[data-tone="neutral"]').trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['info'])
  })

  it('shows the record as the list will, with the name being typed', () => {
    const sample = field('warning', { en: 'Sale' }).find('.wx-catalog-tone__sample .wx-badge')

    expect(sample.text()).toBe('Sale')
    expect(sample.classes()).toContain('wx-badge--warning')
  })

  it('takes an unknown tone for the neutral one rather than choosing nothing', () => {
    const wrapper = field('#ff0000')

    expect(wrapper.find('[aria-checked="true"]').attributes('data-tone')).toBe('neutral')
  })
})
