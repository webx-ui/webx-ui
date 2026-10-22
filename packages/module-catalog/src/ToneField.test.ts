import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { localesKey } from '@webx-ui/core'
import { categoryEditorKey } from '@webx-ui/module-admin'
import ColumnValue from './ColumnValue.vue'
import ToneField from './ToneField.vue'
import { contrastText } from './tones'

const options = [
  { label: 'Neutral', value: 'neutral' },
  { label: 'Primary', value: 'primary' },
  { label: 'Success', value: 'success' },
  { label: 'Warning', value: 'warning' },
  { label: 'Danger', value: 'danger' },
  { label: 'Info', value: 'info' },
]

function field(
  modelValue: string | null,
  title: Record<string, string> | null = null,
  extra: { custom?: boolean; text_color?: string | null } = {},
) {
  const { text_color = null, ...props } = extra

  return mount(ToneField, {
    props: { modelValue, options, customLabel: 'Own colour', ...props },
    global: {
      provide: {
        [localesKey as symbol]: { list: ref([{ code: 'en', name: 'English' }]), active: ref('en') },
        ...(title === null
          ? {}
          : {
              [categoryEditorKey as symbol]: {
                category: ref(null),
                values: ref({ title, text_color }),
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

  it('offers no colour of its own unless the screen asks', () => {
    expect(field('neutral').find('[data-tone="custom"]').exists()).toBe(false)
  })

  it('offers a colour of its own as a seventh choice and keeps it as #rrggbb', async () => {
    const wrapper = field('danger', { en: 'Hit' }, { custom: true })
    const custom = wrapper.find('[data-tone="custom"]')

    expect(custom.text()).toBe('Own colour')
    expect(wrapper.find('.wx-color-picker').exists()).toBe(false)

    await custom.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#2563eb'])

    // The arrows walk into it and out of it like into any tone.
    await wrapper.find('[data-tone="custom"]').trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['neutral'])
    await wrapper.setProps({ modelValue: 'info' })
    await wrapper.find('[data-tone="info"]').trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#2563eb'])
  })

  it('paints the sample in the colour, with the text by contrast until one is chosen', async () => {
    const wrapper = field('#FFEB3B', { en: 'Hit' }, { custom: true })

    expect(wrapper.find('[aria-checked="true"]').attributes('data-tone')).toBe('custom')
    expect(wrapper.find('.wx-color-picker').exists()).toBe(true)

    const sample = wrapper.find('.wx-catalog-tone__sample .wx-badge')
    expect(sample.text()).toBe('Hit')
    expect(sample.attributes('style')).toContain('--wx-badge-solid: #ffeb3b')
    expect(sample.attributes('style')).toContain('--wx-badge-contrast: #000000')

    const chosen = field('#1a237e', { en: 'Hit' }, { custom: true, text_color: '#ffeb3b' })
    const painted = chosen.find('.wx-catalog-tone__sample .wx-badge')
    expect(painted.attributes('style')).toContain('--wx-badge-solid: #1a237e')
    expect(painted.attributes('style')).toContain('--wx-badge-contrast: #ffeb3b')
  })

  it('takes a colour of its own for neutral where the kind has none', () => {
    expect(field('#ffeb3b').find('[aria-checked="true"]').attributes('data-tone')).toBe('neutral')
  })
})

describe('contrastText()', () => {
  it('is black or white, whichever WCAG says reads better', () => {
    expect(contrastText('#ffffff')).toBe('#000000')
    expect(contrastText('#000000')).toBe('#ffffff')
    expect(contrastText('#ffeb3b')).toBe('#000000')
    expect(contrastText('#1a237e')).toBe('#ffffff')
    expect(contrastText('#d32f2f')).toBe('#ffffff')
    expect(contrastText('#808080')).toBe('#000000')
  })
})

describe('WxCatalogColumnValue', () => {
  it('draws a tone as a tag of it and a colour of its own as a solid tag in it', () => {
    const wrapper = mount(ColumnValue, {
      props: {
        value: [
          { id: 1, name: 'Sale', color: 'danger', text: null },
          { id: 2, name: 'Hit', color: '#1a237e', text: '#ffffff' },
        ],
      },
    })
    const [tone, own] = wrapper.findAll('.wx-badge')

    expect(tone!.classes()).toContain('wx-badge--danger')
    expect(own!.classes()).toContain('wx-badge--solid')
    expect(own!.attributes('style')).toContain('--wx-badge-solid: #1a237e')
    expect(own!.attributes('style')).toContain('--wx-badge-contrast: #ffffff')
  })
})
