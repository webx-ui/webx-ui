import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WxAccordionItem from '../AccordionItem/AccordionItem.vue'
import WxAccordion from './Accordion.vue'

const items = `
  <wx-accordion-item value="general" title="General">Name and slug</wx-accordion-item>
  <wx-accordion-item value="seo" title="SEO" subtitle="Title, description">Meta tags</wx-accordion-item>
  <wx-accordion-item value="danger" title="Danger zone" disabled>Delete</wx-accordion-item>
`

function factory(props: Record<string, unknown> = {}, slots?: string) {
  return mount(WxAccordion, {
    props,
    slots: { default: slots ?? items },
    global: { components: { WxAccordionItem } },
  })
}

function triggers(wrapper: ReturnType<typeof factory>) {
  return wrapper.findAll('.wx-accordion-item__trigger')
}

describe('WxAccordion', () => {
  it('is a list of headed panels, all closed to begin with', () => {
    const wrapper = factory()

    expect(triggers(wrapper)).toHaveLength(3)
    expect(wrapper.findAll('h3')).toHaveLength(3)
    expect(triggers(wrapper)[0].attributes('aria-expanded')).toBe('false')
    expect(wrapper.text()).not.toContain('Name and slug')
  })

  it('opens what is clicked and closes what was open', async () => {
    const wrapper = factory()

    await triggers(wrapper)[0].trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Name and slug')

    await triggers(wrapper)[1].trigger('click')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['seo'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['seo'])
    expect(wrapper.text()).toContain('Meta tags')
    expect(wrapper.text()).not.toContain('Name and slug')
  })

  it('closes the open item when it is clicked again', async () => {
    const wrapper = factory({ modelValue: 'general' })
    await flushPromises()

    await triggers(wrapper)[0].trigger('click')

    // Nothing open reads back as `undefined`, not as an empty string.
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([undefined])
  })

  it('holds that one open when it is told not to collapse', async () => {
    const wrapper = factory({ modelValue: 'general', collapsible: false })
    await flushPromises()

    await triggers(wrapper)[0].trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('keeps several open at once when multiple is on', async () => {
    const wrapper = factory({ multiple: true, modelValue: ['general'] })
    await flushPromises()

    await triggers(wrapper)[1].trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['general', 'seo']])
  })

  it('follows v-model from the outside', async () => {
    const wrapper = factory({ modelValue: 'general' })
    await flushPromises()
    expect(wrapper.text()).toContain('Name and slug')

    await wrapper.setProps({ modelValue: 'seo' })
    await flushPromises()

    expect(wrapper.text()).toContain('Meta tags')
  })

  it('ignores a disabled item', async () => {
    const wrapper = factory()

    expect(triggers(wrapper)[2].attributes('disabled')).toBeDefined()

    await triggers(wrapper)[2].trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('hands its size, variant, chevron side and heading level to the items', () => {
    const wrapper = factory({
      size: 'sm',
      variant: 'separated',
      iconPosition: 'start',
      headingTag: 'h2',
    })

    expect(wrapper.classes()).toContain('wx-accordion--separated')
    expect(wrapper.get('.wx-accordion-item').classes()).toEqual(
      expect.arrayContaining([
        'wx-accordion-item--separated',
        'wx-accordion-item--sm',
        'wx-accordion-item--chevron-start',
      ]),
    )
    expect(wrapper.findAll('h2')).toHaveLength(3)
  })
})

describe('WxAccordionItem', () => {
  it('shows a subtitle and an icon beside the title', () => {
    const wrapper = factory(
      {},
      '<wx-accordion-item value="a" title="SEO" subtitle="Title, description" icon="search">Meta</wx-accordion-item>',
    )

    expect(wrapper.get('.wx-accordion-item__title').text()).toBe('SEO')
    expect(wrapper.get('.wx-accordion-item__subtitle').text()).toBe('Title, description')
    expect(wrapper.find('.wx-accordion-item__icon').exists()).toBe(true)
    // The icon indents the title, and the panel text follows it.
    expect(wrapper.get('.wx-accordion-item').classes()).toContain('wx-accordion-item--icon')
  })

  it('says it has no icon when it has none', () => {
    const wrapper = factory({}, '<wx-accordion-item value="a" title="SEO">Meta</wx-accordion-item>')

    expect(wrapper.get('.wx-accordion-item').classes()).not.toContain('wx-accordion-item--icon')
  })

  /* A switch in the header has to be usable without the header opening under it. */
  it('keeps the extra slot outside the button', () => {
    const wrapper = factory(
      {},
      `<wx-accordion-item value="a" title="Comments">
         <template #extra><button class="toggle">On</button></template>
         Body
       </wx-accordion-item>`,
    )

    const extra = wrapper.get('.wx-accordion-item__extra')
    expect(extra.find('.toggle').exists()).toBe(true)
    expect(wrapper.get('.wx-accordion-item__trigger').find('.toggle').exists()).toBe(false)
    // And outside the heading: a heading reads out as its own text when skimmed.
    expect(wrapper.get('h3').find('.toggle').exists()).toBe(false)
    expect(wrapper.get('h3').element.children).toHaveLength(1)
  })
})
