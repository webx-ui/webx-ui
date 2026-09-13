import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import WxInput from '../Input/Input.vue'
import WxLocales from './Locales.vue'
import { localesKey, localizedValue, type LocalesContext } from '../../composables/useLocalized'

function panel(codes: string[]): { provide: Record<symbol, LocalesContext> } {
  return {
    provide: {
      [localesKey as symbol]: {
        list: ref(codes.map((code) => ({ code }))),
        active: ref(codes[0] ?? ''),
      },
    },
  }
}

/** The one somebody is typing into; the rest ride along hidden. */
const visible = (wrapper: { findAll: (q: string) => unknown[] }) =>
  wrapper.findAll('input:not([type="hidden"])')

describe('WxLocales', () => {
  it('renders every language and shows one', () => {
    const wrapper = mount(WxLocales, {
      global: panel(['uk', 'ru']),
      slots: { default: '<p class="pane">pane</p>' },
    })

    // All of them are in the DOM: a control that is not rendered is one a form post leaves out,
    // and saving would wipe the languages nobody happened to be looking at.
    expect(wrapper.findAll('.pane')).toHaveLength(2)
    expect(wrapper.findAll('.wx-locales__pane')[1]?.attributes('style')).toContain('display: none')
  })

  it('still renders the section when no languages are configured', () => {
    const wrapper = mount(WxLocales, {
      props: { locales: [] },
      slots: { default: '<p class="pane">pane</p>' },
    })

    expect(wrapper.findAll('.pane')).toHaveLength(1)
    expect(wrapper.find('.wx-locale-picker').exists()).toBe(false)
  })
})

describe('a localized field', () => {
  it('keeps its own root element', () => {
    // The box is the component's root, and a root is what a parent's scoped CSS is stamped on
    // and what `class` lands on. Wrapping it for the sake of a selector moves both.
    const plain = mount(WxInput, { props: { modelValue: 'x' } })
    const localized = mount(WxInput, {
      global: panel(['uk', 'ru']),
      props: { localized: true, modelValue: { uk: 'x' } },
    })

    expect(plain.classes()).toContain('wx-input')
    expect(localized.classes()).toContain('wx-input')
    expect(localized.classes()).toContain('is-localized')
  })

  it('writes a record keyed by language, and names the inputs the way a form post is read', async () => {
    const value = ref<Record<string, string> | string>({ uk: 'Двигуни', ru: 'Двигатели' })

    const wrapper = mount(WxInput, {
      global: panel(['uk', 'ru']),
      props: {
        localized: true,
        name: 'title',
        modelValue: value.value,
        'onUpdate:modelValue': (next: unknown) => {
          value.value = next as Record<string, string>
        },
      },
    })

    expect(wrapper.findAll('input').map((input) => input.attributes('name'))).toEqual([
      'title[uk]',
      'title[ru]',
    ])
    // The language not on screen rides along, so saving does not wipe it.
    expect(wrapper.find('input[type="hidden"]').attributes('value')).toBe('Двигатели')

    await wrapper.find('input:not([type="hidden"])').setValue('Двигуни у зборі')

    expect(value.value).toEqual({ uk: 'Двигуни у зборі', ru: 'Двигатели' })
  })

  it('moves every field on the screen together', async () => {
    const screen = defineComponent({
      setup() {
        const title = ref<Record<string, string>>({ uk: 'Двигуни', ru: 'Двигатели' })
        const note = ref<Record<string, string>>({ uk: 'Опис', ru: 'Описание' })

        return () => [
          h(WxInput, { modelValue: title.value, localized: true }),
          h(WxInput, { modelValue: note.value, localized: true }),
        ]
      },
    })

    const wrapper = mount(screen, { global: panel(['uk', 'ru']) })

    const shown = () =>
      visible(wrapper).map((input) => (input as { element: HTMLInputElement }).element.value)

    expect(shown()).toEqual(['Двигуни', 'Опис'])

    // The second field's own picker, switching both: a page half in one language and half in
    // another is how a record gets saved untranslated.
    await wrapper.findAll('.wx-locale-picker__code')[3]?.trigger('click')

    expect(shown()).toEqual(['Двигатели', 'Описание'])
  })

  it('keeps the words a column already held when the field is switched on', async () => {
    const value = ref<Record<string, string> | string>('Двигуни')

    const wrapper = mount(WxInput, {
      global: panel(['uk', 'ru']),
      props: {
        localized: true,
        modelValue: value.value,
        'onUpdate:modelValue': (next: unknown) => {
          value.value = next as Record<string, string>
        },
      },
    })

    // They belong to the first language rather than to nothing.
    expect(wrapper.find<HTMLInputElement>('input:not([type="hidden"])').element.value).toBe(
      'Двигуни',
    )

    await wrapper.findAll('.wx-locale-picker__code')[1]?.trigger('click')
    await wrapper.find('input:not([type="hidden"])').setValue('Двигатели')

    expect(value.value).toEqual({ uk: 'Двигуни', ru: 'Двигатели' })
  })

  it('is an ordinary field when the site publishes in one language', () => {
    const wrapper = mount(WxInput, {
      props: { localized: true, name: 'title', modelValue: 'Двигуни' },
    })

    expect(wrapper.findAll('input')).toHaveLength(1)
    expect(wrapper.find('input').attributes('name')).toBe('title')
    expect(wrapper.find('.wx-locale-picker').exists()).toBe(false)
  })
})

describe('localizedValue', () => {
  it('falls back to a language that has words', () => {
    expect(localizedValue({ uk: 'Двигуни', ru: 'Двигатели' }, 'ru')).toBe('Двигатели')
    expect(localizedValue({ uk: 'Двигуни', ru: '' }, 'ru')).toBe('Двигуни')
    expect(localizedValue('Двигуни', 'ru')).toBe('Двигуни')
    expect(localizedValue(null, 'ru', '—')).toBe('—')
    expect(localizedValue({}, 'ru', '—')).toBe('—')
  })
})
