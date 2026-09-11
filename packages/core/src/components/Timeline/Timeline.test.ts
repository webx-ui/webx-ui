import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxTimeline from './Timeline.vue'
import WxTimelineItem from '../TimelineItem/TimelineItem.vue'

function timeline(props: Record<string, unknown> = {}, items?: string) {
  return mount(WxTimeline, {
    props,
    slots: {
      default:
        items ??
        `<wx-timeline-item timestamp="12.03" title="Created" />
         <wx-timeline-item timestamp="14.03" title="Published" type="success" />`,
    },
    global: { components: { WxTimelineItem } },
  })
}

describe('WxTimeline', () => {
  it('is a list of entries', () => {
    const wrapper = timeline()

    expect(wrapper.element.tagName).toBe('UL')
    expect(wrapper.findAll('li.wx-timeline-item')).toHaveLength(2)
    expect(wrapper.classes()).toContain('wx-timeline--md')
  })

  it('hands its size to the items', () => {
    const wrapper = timeline({ size: 'sm' })

    expect(wrapper.classes()).toContain('wx-timeline--sm')
    expect(wrapper.get('.wx-timeline-item').classes()).toContain('wx-timeline-item--sm')
  })
})

describe('WxTimelineItem', () => {
  it('shows the timestamp above the entry by default', () => {
    const wrapper = mount(WxTimelineItem, {
      props: { timestamp: '12 March', datetime: '2026-03-12', title: 'Created' },
      slots: { default: 'by Maria' },
    })

    const time = wrapper.get('time')
    expect(time.text()).toBe('12 March')
    expect(time.attributes('datetime')).toBe('2026-03-12')
    expect(time.classes()).not.toContain('wx-timeline-item__timestamp--bottom')
    expect(wrapper.get('.wx-timeline-item__title').text()).toBe('Created')
    expect(wrapper.get('.wx-timeline-item__body').text()).toBe('by Maria')
  })

  it('moves the timestamp under the entry on request', () => {
    const wrapper = mount(WxTimelineItem, {
      props: { timestamp: '12 March', timestampPlacement: 'bottom' },
    })

    expect(wrapper.get('time').classes()).toContain('wx-timeline-item__timestamp--bottom')
  })

  it('colours the dot and can hollow it out', () => {
    const wrapper = mount(WxTimelineItem, { props: { type: 'danger', hollow: true } })
    const dot = wrapper.get('.wx-timeline-item__dot')

    expect(dot.classes()).toContain('wx-timeline-item__dot--danger')
    expect(dot.classes()).toContain('wx-timeline-item__dot--hollow')
  })

  it('grows the dot around an icon or a custom mark', () => {
    expect(mount(WxTimelineItem, { props: { icon: 'check' } }).classes()).toContain(
      'wx-timeline-item--mark',
    )
    expect(
      mount(WxTimelineItem, { slots: { dot: '<span class="avatar" />' } }).classes(),
    ).toContain('wx-timeline-item--mark')
    expect(mount(WxTimelineItem, { props: { title: 'Plain' } }).classes()).not.toContain(
      'wx-timeline-item--mark',
    )
  })

  it('can drop the line under itself', () => {
    expect(mount(WxTimelineItem, { props: { hideLine: true } }).classes()).toContain(
      'wx-timeline-item--no-line',
    )
  })

  it('omits the timestamp element when there is no timestamp', () => {
    expect(
      mount(WxTimelineItem, { props: { title: 'Created' } })
        .find('time')
        .exists(),
    ).toBe(false)
  })
})
