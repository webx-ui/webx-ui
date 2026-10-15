import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import type * as Core from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import TaskFormPage from './TaskFormPage.vue'
import TaskPage from './TaskPage.vue'
import type { Stage, TaskDetail } from './types'

const { confirm, ask } = vi.hoisted(() => ({ confirm: vi.fn(), ask: vi.fn() }))

/* The dialogs are the core's; what is checked here is which move asks and what it sends. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
  createModal: () => ask,
}))

function stage(
  id: number,
  position: number,
  status: Stage['status'],
  over: Partial<Stage> = {},
): Stage {
  return {
    id,
    position,
    title: `Stage ${position}`,
    brief: 'Do it.',
    acceptance: null,
    review_mode: 'auto',
    status,
    assignee: null,
    attempt: 1,
    report: null,
    result_ref: null,
    claimed_at: null,
    reported_at: null,
    accepted_at: null,
    status_changed_at: null,
    ...over,
  }
}

function task(stages: Stage[], over: Partial<TaskDetail> = {}): TaskDetail {
  return {
    id: 7,
    ref: 'shop#7',
    project: 'shop',
    number: 7,
    title: 'Cart page',
    status: 'review',
    priority: 'normal',
    branch: 'feat/cart',
    done: 0,
    current_stage: null,
    updated_at: null,
    spec: '**Keep** the cart.',
    acceptance: null,
    created_at: null,
    closed_at: null,
    stages,
    journal: [
      {
        id: 1,
        stage_id: 12,
        actor_type: 'agent',
        actor_name: 'worker-2',
        kind: 'question',
        body: 'Which provider?',
        meta: { from: 'in_progress', to: 'blocked' },
        created_at: null,
      },
    ],
    ...over,
  }
}

/* The form's nodes as plain inputs: the page's patch finds every one it locks. */
const screen: ScreenNode[] = [
  'project_id',
  'title',
  'priority',
  'branch',
  'spec',
  'acceptance',
].map((name) => ({ id: name === 'project_id' ? 'project' : name, type: 'wx-input', name }))

async function panel(
  path: string,
  detail: TaskDetail,
  permissions: string[] = ['tasks.manage', 'tasks.review'],
) {
  const get = vi
    .fn()
    .mockImplementation((url: string) =>
      Promise.resolve(
        url.endsWith('/projects')
          ? { data: [{ id: 1, slug: 'shop', title: 'Shop', archived: false }] }
          : { data: detail },
      ),
    )
  const post = vi.fn().mockResolvedValue({ data: detail })
  const put = vi.fn().mockResolvedValue({ data: detail })
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: (permission: string) => permissions.includes(permission),
    refreshManifest: vi.fn().mockResolvedValue(undefined),
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/tasks', component: { template: '<div />' } },
      {
        path: '/tasks/:id(\\d+)',
        component: TaskPage,
        props: (r) => ({ id: Number(r.params.id) }),
      },
      {
        path: '/tasks/:id(\\d+)/edit',
        component: TaskFormPage,
        props: (r) => ({ id: Number(r.params.id) }),
      },
    ],
  })

  await router.push(path)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      attachTo: document.body,
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, post, put, router }
}

const buttons = (wrapper: Awaited<ReturnType<typeof panel>>['wrapper']) =>
  wrapper.findAll('.wx-task__moves button').map((button) => button.text())

beforeEach(() => {
  confirm.mockReset()
  ask.mockReset()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('WxTaskPage', () => {
  it('offers a reviewer the decision on a reported stage, and only to a reviewer', async () => {
    const detail = task([stage(11, 1, 'done'), stage(12, 2, 'review', { report: '## Done' })])

    const reviewer = await panel('/tasks/7', detail, ['tasks.review'])
    expect(buttons(reviewer.wrapper)).toEqual(['Accept', 'Send back'])
    reviewer.wrapper.unmount()

    const viewer = await panel('/tasks/7', detail, ['tasks.view'])
    expect(buttons(viewer.wrapper)).toEqual([])
  })

  it('accepts after asking, and sends back only with remarks', async () => {
    const { wrapper, post } = await panel('/tasks/7', task([stage(12, 1, 'review')]), [
      'tasks.review',
    ])

    confirm.mockResolvedValueOnce(true)
    await wrapper.findAll('.wx-task__moves button')[0]!.trigger('click')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/api/cms/tasks/stages/12/accept', { note: undefined })

    ask.mockResolvedValueOnce('The empty cart crashes.')
    await wrapper.findAll('.wx-task__moves button')[1]!.trigger('click')
    await flushPromises()
    expect(ask).toHaveBeenCalledWith(expect.objectContaining({ required: true }))
    expect(post).toHaveBeenCalledWith('/api/cms/tasks/stages/12/rework', {
      remarks: 'The empty cart crashes.',
    })
  })

  it('shows the question of a blocked stage and lets whoever plans answer it', async () => {
    const { wrapper, post } = await panel('/tasks/7', task([stage(12, 1, 'blocked')]), [
      'tasks.manage',
    ])

    expect(wrapper.text()).toContain('Which provider?')
    expect(buttons(wrapper)).toEqual(['Answer', 'Cancel the stage'])

    ask.mockResolvedValueOnce('The configured one.')
    await wrapper.findAll('.wx-task__moves button')[0]!.trigger('click')
    await flushPromises()

    expect(ask).toHaveBeenCalledWith(expect.objectContaining({ message: 'Which provider?' }))
    expect(post).toHaveBeenCalledWith('/api/cms/tasks/stages/12/unblock', {
      answer: 'The configured one.',
    })
  })

  it('renders the specification as markdown and the journal with its people', async () => {
    const { wrapper } = await panel('/tasks/7', task([stage(12, 1, 'blocked')]))

    expect(wrapper.find('.wx-tasks-markdown strong').text()).toBe('Keep')
    expect(wrapper.text()).toContain('worker-2')
  })
})

describe('WxTaskFormPage', () => {
  it('puts the stages back around the started ones and sends only the priority once in work', async () => {
    const detail = task([stage(11, 1, 'done'), stage(12, 2, 'ready'), stage(13, 3, 'pending')], {
      status: 'in_progress',
    })
    const { wrapper, put } = await panel('/tasks/7/edit', detail)

    // The started stage is listed apart; the editor holds the two nobody took.
    expect(wrapper.find('.wx-task-form__locked').text()).toContain('Stage 1')

    const priority = wrapper.findAll('input')[2]!
    await priority.setValue('high')
    await wrapper.find('.wx-action-bar button.wx-button--primary').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/tasks/tasks/7', {
      priority: 'high',
      stages: [
        { id: 11 },
        { id: 12, title: 'Stage 2', brief: 'Do it.', acceptance: null, review_mode: 'auto' },
        { id: 13, title: 'Stage 3', brief: 'Do it.', acceptance: null, review_mode: 'auto' },
      ],
    })
  })
})
