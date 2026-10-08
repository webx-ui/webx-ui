import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { adminKey, type AdminContext } from './admin'
import { loadShortcodes, useShortcodes, type ShortcodeToken } from './shortcodes'

const answer = {
  data: [
    {
      name: 'email',
      description: null,
      html: '<a href="mailto:hello@example.com">hello@example.com</a>',
      plain: 'hello@example.com',
      origin: 'settings',
    },
    {
      name: 'phone',
      description: 'The main line',
      html: '<a href="tel:+15550100">+1 555 0100</a>',
      plain: '+1 555 0100',
      origin: 'code',
    },
  ],
}

function panel(get: (path: string) => Promise<unknown>): AdminContext {
  return { apiPath: '/api/cms', http: { get } } as unknown as AdminContext
}

describe('shortcodes', () => {
  it('asks the panel once and offers the text value of each', async () => {
    const get = vi.fn(() => Promise.resolve(answer))
    const admin = panel(get)

    const first = await loadShortcodes(admin)
    const second = await loadShortcodes(admin)

    expect(get).toHaveBeenCalledTimes(1)
    expect(get).toHaveBeenCalledWith('/api/cms/shortcodes')
    expect(second).toBe(first)
    expect(first).toEqual([
      { name: 'email', value: 'hello@example.com', description: undefined },
      { name: 'phone', value: '+1 555 0100', description: 'The main line' },
    ])
  })

  it('answers with nothing when the request fails, and asks again next time', async () => {
    const get = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(answer)
    const admin = panel(get)

    expect(await loadShortcodes(admin)).toEqual([])
    expect(await loadShortcodes(admin)).toHaveLength(2)
  })

  it('fills a field once the panel answers, and stays empty outside a panel', async () => {
    let seen: ShortcodeToken[] = []
    const Probe = defineComponent({
      setup() {
        const tokens = useShortcodes()
        return () => {
          seen = tokens.value
          return h('div')
        }
      },
    })

    mount(Probe, {
      global: { provide: { [adminKey as symbol]: panel(() => Promise.resolve(answer)) } },
    })
    expect(seen).toEqual([])
    await flushPromises()
    expect(seen.map((token) => token.name)).toEqual(['email', 'phone'])

    mount(Probe)
    await flushPromises()
    expect(seen).toEqual([])
  })
})
