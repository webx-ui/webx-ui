import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxEntityCard from './EntityCard.vue'

describe('WxEntityCard', () => {
  it('shows the title, the picture and the meta row', () => {
    const wrapper = mount(WxEntityCard, {
      props: {
        title: 'Alexey Sizintsev',
        image: '/avatar.jpg',
        meta: [{ label: 'Sections', text: 'News' }, { label: 'Tags' }],
      },
    })

    expect(wrapper.get('.wx-entity-card__title').text()).toBe('Alexey Sizintsev')
    expect(wrapper.get('img').attributes('src')).toBe('/avatar.jpg')
    expect(wrapper.get('img').attributes('alt')).toBe('Alexey Sizintsev')

    const facts = wrapper.findAll('.wx-entity-card__meta-item')
    expect(facts).toHaveLength(2)
    expect(facts[0].get('.wx-entity-card__meta-label').text()).toBe('Sections:')
    expect(facts[0].get('.wx-entity-card__meta-text').text()).toBe('News')
    // A field with no value still shows its name, the way an empty field reads.
    expect(facts[1].text()).toBe('Tags:')
    expect(facts[1].find('.wx-entity-card__meta-text').exists()).toBe(false)
  })

  it('falls back to the first letter when there is no picture', () => {
    const wrapper = mount(WxEntityCard, { props: { title: 'news page' } })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.get('.wx-entity-card__media--empty').text()).toBe('N')
  })

  it('links the title when href is given', () => {
    const plain = mount(WxEntityCard, { props: { title: 'Page' } })
    expect(plain.get('.wx-entity-card__title').element.tagName).toBe('SPAN')

    const linked = mount(WxEntityCard, { props: { title: 'Page', href: '/pages/12' } })
    expect(linked.get('a.wx-entity-card__title').attributes('href')).toBe('/pages/12')
  })

  it('renders the actions slot and keeps its clicks to itself', async () => {
    const wrapper = mount(WxEntityCard, {
      props: { title: 'Page' },
      slots: { actions: '<button class="edit">Edit</button>' },
    })

    await wrapper.get('.edit').trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()

    await wrapper.get('.wx-entity-card__body').trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('takes size, variant, shape and selection', () => {
    const wrapper = mount(WxEntityCard, {
      props: {
        title: 'Page',
        image: '/x.png',
        size: 'sm',
        variant: 'plain',
        shape: 'circle',
        selected: true,
        imageSize: 28,
      },
    })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining(['wx-entity-card--sm', 'wx-entity-card--plain', 'is-selected']),
    )
    expect(wrapper.get('.wx-entity-card__media').classes()).toContain(
      'wx-entity-card__media--circle',
    )
    expect(wrapper.attributes('style')).toContain('--wx-entity-card-image: 28px')
  })

  it('lets slots replace the picture, the title and the meta row', () => {
    const wrapper = mount(WxEntityCard, {
      props: { title: 'Ignored', meta: [{ text: 'ignored' }] },
      slots: {
        media: '<span class="icon" />',
        title: '<b>Custom</b>',
        meta: '<span class="custom-meta">Anything</span>',
        default: '<p>Body</p>',
      },
    })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.icon').exists()).toBe(true)
    expect(wrapper.get('.wx-entity-card__title').text()).toBe('Custom')
    expect(wrapper.get('.wx-entity-card__meta').text()).toBe('Anything')
    expect(wrapper.get('.wx-entity-card__content').text()).toBe('Body')
  })
})
