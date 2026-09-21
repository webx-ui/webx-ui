import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import CallList from './CallList.vue'
import { createAdminsApi } from './admins'
import type { AgentCall } from './types'

function call(row: Partial<AgentCall> & { id: number }): AgentCall {
  return {
    at: '2026-09-21T09:05:00+00:00',
    user: { id: 1, name: 'Anna' },
    client: 'Claude',
    tool: 'pages_list',
    arguments: null,
    dry_run: false,
    ok: true,
    error: null,
    duration_ms: 38,
    ...row,
  }
}

/** The server's own shape: the numbers under `meta`, the filters beside them. */
function body(rows: AgentCall[]) {
  return {
    data: rows,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 30,
      total: rows.length,
      from: rows.length > 0 ? 1 : null,
      to: rows.length,
    },
    filters: {
      users: [
        { id: 1, name: 'Anna' },
        { id: 7, name: null },
        { id: null, name: null },
      ],
      tools: ['pages_create', 'pages_list'],
    },
  }
}

function panel(rows: AgentCall[]) {
  const get = vi.fn().mockResolvedValue(body(rows))

  // The real dictionary: the package's own English is what a panel sees before the server's
  // translations arrive, and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  return {
    get,
    admin,
    wrapper: mount(CallList, {
      global: {
        plugins: [WebxUI],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the list asks for and what it says about a row. Nothing about how it looks: jsdom
 * computes no layout, so the columns that fold and the cards are checked in a browser.
 */
describe('WxAgentCallList', () => {
  it('names who called, with what outcome, and what it was called with underneath', async () => {
    const { wrapper } = panel([
      call({
        id: 3,
        tool: 'pages_delete',
        ok: false,
        error: 'No such page.',
        arguments: '{\n    "id": 12\n}',
      }),
      call({ id: 2, tool: 'pages_create', dry_run: true, arguments: '{\n    "title": "About"\n}' }),
      call({ id: 1, user: null, client: null }),
    ])

    await flushPromises()

    const text = wrapper.text()

    expect(text).toContain('Anna')
    expect(text).toContain('Local server')
    expect(text).toContain('pages_delete')
    expect(text).toContain('Refused')
    expect(text).toContain('Dry run')
    expect(text).toContain('Answered')

    // Nothing is open until it is asked for: the arguments are a block, not a column.
    expect(text).not.toContain('"id": 12')

    const expanders = wrapper.findAll('.wx-table__expander')
    // The stdio row has neither arguments nor an error, so it has nothing to open.
    expect(expanders).toHaveLength(2)

    await expanders[0]!.trigger('click')

    expect(wrapper.text()).toContain('Refused with')
    expect(wrapper.text()).toContain('No such page.')
    expect(wrapper.find('.wx-call-list__arguments').text()).toContain('"id": 12')
  })

  it('offers the people and the tools the server found in the log', async () => {
    const { wrapper } = panel([call({ id: 1 })])

    await flushPromises()

    // The dropdowns live behind the funnel, and a shut panel has no fields to find.
    await wrapper.get('.wx-table__filter button').trigger('click')
    await flushPromises()

    const selects = wrapper.findAllComponents({ name: 'WxSelect' })
    expect(selects).toHaveLength(3)

    const labels = (index: number) =>
      (selects[index]!.props('options') as { label: string; value: string }[]).map(
        (one) => one.label,
      )

    // A deleted administrator stays as their number; the stdio server is a person of its own.
    expect(labels(0)).toEqual(['Everybody', 'Anna', 'Administrator #7', 'Local server'])
    expect(labels(1)).toEqual(['All tools', 'pages_create', 'pages_list'])
    expect(labels(2)).toEqual(['Any outcome', 'Answered only', 'Refused only', 'Dry runs only'])
  })

  it('asks the server to narrow, in the words the server uses', async () => {
    const { get, wrapper } = panel([call({ id: 1 })])

    await flushPromises()

    expect(get).toHaveBeenLastCalledWith(
      '/api/cms/auth/mcp-calls',
      expect.objectContaining({
        query: expect.objectContaining({ user: undefined, tool: undefined }),
      }),
    )

    await wrapper.get('.wx-table__filter button').trigger('click')
    await flushPromises()

    const [who, tool, outcome] = wrapper.findAllComponents({ name: 'WxSelect' })

    await who!.vm.$emit('update:modelValue', 'none')
    await tool!.vm.$emit('update:modelValue', 'pages_create')
    await outcome!.vm.$emit('update:modelValue', 'failed')
    await flushPromises()

    expect(get).toHaveBeenLastCalledWith(
      '/api/cms/auth/mcp-calls',
      expect.objectContaining({
        query: expect.objectContaining({
          user: 'none',
          tool: 'pages_create',
          outcome: 'failed',
          page: 1,
        }),
      }),
    )

    // Chips say what is on, and take it off again.
    const chips = wrapper.text()
    expect(chips).toContain('Administrator: Local server')
    expect(chips).toContain('Tool: pages_create')
    expect(chips).toContain('Outcome: Refused only')
  })

  it('flattens the page the way the table reads it', async () => {
    const get = vi.fn().mockResolvedValue(body([call({ id: 1 })]))
    const api = createAdminsApi({ apiPath: '/api/cms', http: { get } } as unknown as AdminContext)

    const page = await api.calls({ user: '1', outcome: 'ok' })

    expect(page.total).toBe(1)
    expect(page.data[0]!.tool).toBe('pages_list')
    expect(page.filters.tools).toEqual(['pages_create', 'pages_list'])
    expect(get).toHaveBeenCalledWith('/api/cms/auth/mcp-calls', {
      query: { user: '1', tool: undefined, outcome: 'ok', page: undefined, per_page: undefined },
    })
  })
})
