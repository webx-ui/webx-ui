import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxStatistic from './Statistic.vue'

describe('WxStatistic', () => {
  it('shows a title and a grouped number', () => {
    const wrapper = mount(WxStatistic, {
      props: { title: 'Daily active users', value: 268500, locale: 'en-US' },
    })

    expect(wrapper.get('.wx-statistic__title').text()).toBe('Daily active users')
    expect(wrapper.get('.wx-statistic__number').text()).toBe('268,500')
  })

  it('respects precision and can drop the grouping', () => {
    expect(
      mount(WxStatistic, { props: { value: 1234.5678, precision: 2, locale: 'en-US' } })
        .get('.wx-statistic__number')
        .text(),
    ).toBe('1,234.57')

    expect(
      mount(WxStatistic, { props: { value: 2026, grouping: false, locale: 'en-US' } })
        .get('.wx-statistic__number')
        .text(),
    ).toBe('2026')
  })

  it('prints a string value as it was given', () => {
    const wrapper = mount(WxStatistic, { props: { value: '138/100' } })

    expect(wrapper.get('.wx-statistic__number').text()).toBe('138/100')
  })

  it('hands the value to a formatter when one is given', () => {
    const wrapper = mount(WxStatistic, {
      props: { value: 0.734, formatter: (value) => `${Number(value) * 100}%` },
    })

    expect(wrapper.get('.wx-statistic__number').text()).toBe('73.4%')
  })

  it('renders prefix and suffix only when there is something to show', () => {
    const bare = mount(WxStatistic, { props: { value: 5 } })
    expect(bare.findAll('.wx-statistic__affix')).toHaveLength(0)

    const wrapper = mount(WxStatistic, { props: { value: 5, prefix: '$', suffix: '/mo' } })
    const affixes = wrapper.findAll('.wx-statistic__affix')
    expect(affixes.map((node) => node.text())).toEqual(['$', '/mo'])
  })

  it('takes size, tone and alignment', () => {
    const wrapper = mount(WxStatistic, {
      props: { value: 1, size: 'lg', tone: 'success', align: 'center' },
    })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining([
        'wx-statistic--lg',
        'wx-statistic--tone-success',
        'wx-statistic--align-center',
      ]),
    )
  })

  it('renders the footer slot under the number', () => {
    const wrapper = mount(WxStatistic, {
      props: { value: 12 },
      slots: { default: 'since last week' },
    })

    expect(wrapper.get('.wx-statistic__footer').text()).toBe('since last week')
  })
})
