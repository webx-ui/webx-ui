import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { useLayerTeleport, type TeleportTarget } from './useLayerTeleport'
import WxDatePicker from '../components/DatePicker/DatePicker.vue'

function probe(asked: TeleportTarget | undefined) {
  let seen: unknown

  const Probe = defineComponent({
    setup() {
      const root = ref<HTMLElement | null>(null)
      const target = useLayerTeleport(root, () => asked)

      return () => {
        seen = target.value
        return h('span', { ref: root })
      }
    },
  })

  return { Probe, seen: () => seen }
}

describe('useLayerTeleport', () => {
  it('goes into the dialog the control sits in, where body takes no clicks', async () => {
    const host = document.createElement('div')
    host.setAttribute('role', 'dialog')
    document.body.append(host)
    const { Probe, seen } = probe(true)

    const wrapper = mount(Probe, { attachTo: host })
    await wrapper.vm.$nextTick()

    expect(seen()).toBe(host)
    wrapper.unmount()
    host.remove()
  })

  it('stays on body outside a layer, and leaves an explicit target alone', async () => {
    const outside = probe(true)
    const wrapper = mount(outside.Probe, { attachTo: document.body })
    await wrapper.vm.$nextTick()
    expect(outside.seen()).toBe(true)
    wrapper.unmount()

    const host = document.createElement('div')
    host.setAttribute('role', 'dialog')
    document.body.append(host)
    for (const asked of [false, '#somewhere'] as const) {
      const one = probe(asked)
      const inner = mount(one.Probe, { attachTo: host })
      await inner.vm.$nextTick()
      expect(one.seen()).toBe(asked)
      inner.unmount()
    }
    host.remove()
  })

  it('is what WxDatePicker hands its calendar inside a dialog', async () => {
    const host = document.createElement('div')
    host.setAttribute('role', 'dialog')
    document.body.append(host)

    const wrapper = mount(WxDatePicker, { attachTo: host })
    await wrapper.vm.$nextTick()

    expect(wrapper.findComponent(VueDatePicker).props('teleport')).toBe(host)
    wrapper.unmount()
    host.remove()
  })
})
