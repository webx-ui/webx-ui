import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxSteps from './Steps.vue'
import WxStep from '../Step/Step.vue'

const global = { components: { WxStep } }

const three = `
  <wx-step title="Details" />
  <wx-step title="Delivery" />
  <wx-step title="Payment" />
`

function mountSteps(props: Record<string, unknown> = {}) {
  return mount(WxSteps, { props, slots: { default: three }, global })
}

function states(wrapper: ReturnType<typeof mountSteps>) {
  return wrapper.findAll('.wx-step').map((step) => {
    const found = step.classes().find((name) => name.startsWith('wx-step--'))
    return found?.replace('wx-step--', '')
  })
}

describe('WxSteps', () => {
  it('numbers the steps in the order they were written', () => {
    const wrapper = mountSteps()

    expect(wrapper.findAll('.wx-step__marker').map((m) => m.text())).toEqual(['1', '2', '3'])
  })

  it('marks what is behind, where you are, and what is ahead', () => {
    expect(states(mountSteps({ current: 1 }))).toEqual(['done', 'current', 'todo'])
  })

  it('starts on the first step', () => {
    expect(states(mountSteps())).toEqual(['current', 'todo', 'todo'])
  })

  it('shows a tick for a step that is behind', () => {
    const wrapper = mountSteps({ current: 2 })

    expect(wrapper.findAll('.wx-step__marker')[0].find('.wx-icon').exists()).toBe(true)
    expect(wrapper.findAll('.wx-step__marker')[2].text()).toBe('3')
  })

  it('marks the current step as the one that went wrong', () => {
    expect(states(mountSteps({ current: 1, error: true }))).toEqual(['done', 'error', 'todo'])
  })

  it('says which step is current to a screen reader', () => {
    const wrapper = mountSteps({ current: 1 })

    expect(wrapper.findAll('.wx-step')[1].attributes('aria-current')).toBe('step')
    expect(wrapper.findAll('.wx-step')[0].attributes('aria-current')).toBeUndefined()
  })

  it('keeps its numbering right on the first render', () => {
    // No await: a wizard that renumbers itself a frame later flickers on every page.
    expect(states(mountSteps({ current: 1 }))).toEqual(['done', 'current', 'todo'])
  })

  it('goes back to a finished step, and never forward', async () => {
    const wrapper = mountSteps({ current: 1, clickable: true })
    const heads = wrapper.findAll('.wx-step__head')

    await heads[0].trigger('click')
    expect(wrapper.emitted('change')?.at(-1)).toEqual([0])

    // The step ahead is not a button at all, so there is nothing to press.
    expect(heads[2].element.tagName).toBe('DIV')
  })

  it('is not clickable unless asked', () => {
    const wrapper = mountSteps({ current: 2 })

    expect(wrapper.findAll('.wx-step__head')[0].element.tagName).toBe('DIV')
  })
})
