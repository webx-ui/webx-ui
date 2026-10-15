/*
 * «Tasks» (WEBX_UI_MODULE_TASKS.md) without agents: two projects and a handful of tasks in every
 * state the screens have to draw — a draft, one in work with a stage waiting for a person's
 * review, one whose agent asked a question, one done. The moves of §3 are played out in memory,
 * simplified: enough to press every button of the task screen and see the right one appear next.
 *
 * The words of refusals come from the PHP package's dictionary, the way the real API says them.
 */

type On = (
  method: string,
  pattern: string,
  handler: (context: {
    params: string[]
    query: URLSearchParams
    body: Record<string, unknown>
    locale: string
  }) => unknown,
) => void
type Fail = (status: number, message: string) => Error
type Line = (locale: string, namespace: string, path: string) => string

type TaskStatus = 'draft' | 'ready' | 'in_progress' | 'review' | 'done' | 'cancelled'
type StageStatus =
  'pending' | 'ready' | 'in_progress' | 'review' | 'rework' | 'blocked' | 'done' | 'cancelled'

interface Project {
  id: number
  slug: string
  title: string
  workdir: string | null
  repo_url: string | null
  default_branch: string | null
  rules: string | null
  archived: boolean
}

interface Stage {
  id: number
  position: number
  title: string
  brief: string | null
  acceptance: string | null
  review_mode: 'auto' | 'human'
  status: StageStatus
  assignee: string | null
  attempt: number
  report: string | null
  result_ref: string | null
  claimed_at: string | null
  reported_at: string | null
  accepted_at: string | null
  status_changed_at: string | null
}

interface Entry {
  id: number
  stage_id: number | null
  actor_type: 'admin' | 'agent' | 'system'
  actor_name: string
  kind: string
  body: string | null
  meta: Record<string, unknown> | null
  created_at: string
}

interface Task {
  id: number
  project_id: number
  number: number
  title: string
  status: TaskStatus
  priority: 'low' | 'normal' | 'high'
  spec: string | null
  acceptance: string | null
  branch: string | null
  stages: Stage[]
  journal: Entry[]
  created_at: string
  updated_at: string
  closed_at: string | null
}

const PERSON = 'Admin'

const ago = (hours: number) => new Date(Date.now() - hours * 3_600_000).toISOString()

let nextId = 100
const id = () => ++nextId

const projects: Project[] = [
  {
    id: 1,
    slug: 'shop',
    title: 'Online store',
    workdir: '/work/shop',
    repo_url: 'https://git.example.com/shop.git',
    default_branch: 'main',
    rules: 'Run `pnpm gate` before reporting. Never touch `config/payments.php`.',
    archived: false,
  },
  {
    id: 2,
    slug: 'docs',
    title: 'Documentation site',
    workdir: '/work/docs',
    repo_url: null,
    default_branch: 'main',
    rules: null,
    archived: false,
  },
]

function stage(
  position: number,
  title: string,
  status: StageStatus,
  more: Partial<Stage> = {},
): Stage {
  return {
    id: id(),
    position,
    title,
    brief: `Do **${title.toLowerCase()}**.\n\n- keep the public API\n- add tests`,
    acceptance: 'The gate is green; the new behaviour is covered by a test.',
    review_mode: 'auto',
    status,
    assignee: null,
    attempt: 1,
    report: null,
    result_ref: null,
    claimed_at: null,
    reported_at: null,
    accepted_at: null,
    status_changed_at: ago(2),
    ...more,
  }
}

function entry(
  stageId: number | null,
  actor: string,
  kind: string,
  hours: number,
  body: string | null = null,
  meta: Record<string, unknown> | null = null,
): Entry {
  return {
    id: id(),
    stage_id: stageId,
    actor_type: actor === 'system' ? 'system' : actor === PERSON ? 'admin' : 'agent',
    actor_name: actor,
    kind,
    body,
    meta,
    created_at: ago(hours),
  }
}

const report =
  '## Done\nThe cart keeps its items between visits.\n\n## How to check\nAdd an item, close the tab, come back.\n\n## Result\n`feat/cart@3f9c2a1`\n\n## Not done and why\nNothing.\n\n## Risks\nThe session table grows; a cleanup is in the next stage.'

const tasks: Task[] = (() => {
  const reviewHuman = stage(2, 'Cart screens', 'review', {
    review_mode: 'human',
    assignee: 'worker-3',
    report,
    result_ref: 'https://git.example.com/shop/pulls/41',
    claimed_at: ago(5),
    reported_at: ago(1),
  })
  const cartDone = stage(1, 'Cart storage', 'done', {
    assignee: 'worker-1',
    report: '## Done\nTables and the model.',
    result_ref: 'feat/cart@a1b2c3d',
    accepted_at: ago(6),
  })
  const blocked = stage(1, 'Pick the search engine', 'blocked', {
    assignee: 'worker-2',
    claimed_at: ago(3),
  })

  return [
    {
      id: 1,
      project_id: 1,
      number: 12,
      title: 'Cart that survives a closed tab',
      status: 'review',
      priority: 'high',
      spec: 'Visitors lose their cart when the tab closes.\n\nKeep it **server-side** for 30 days.',
      acceptance: '- the cart is back after a new visit\n- an empty cart is never stored',
      branch: 'feat/cart',
      stages: [cartDone, reviewHuman, stage(3, 'Cleanup of old carts', 'pending')],
      journal: [
        entry(null, PERSON, 'status', 30, null, { from: null, to: 'draft' }),
        entry(null, PERSON, 'status', 29, null, { from: 'draft', to: 'ready' }),
        entry(cartDone.id, 'worker-1', 'report', 8, '## Done\nTables and the model.', {
          from: 'in_progress',
          to: 'review',
        }),
        entry(cartDone.id, 'dispatcher', 'status', 6, null, { from: 'review', to: 'done' }),
        entry(reviewHuman.id, 'worker-3', 'report', 1, report, {
          from: 'in_progress',
          to: 'review',
        }),
      ],
      created_at: ago(30),
      updated_at: ago(1),
      closed_at: null,
    },
    {
      id: 2,
      project_id: 1,
      number: 13,
      title: 'Search with typos',
      status: 'in_progress',
      priority: 'normal',
      spec: 'Search should forgive one typo in a word.',
      acceptance: null,
      branch: null,
      stages: [blocked, stage(2, 'Wire it to the catalogue', 'pending')],
      journal: [
        entry(
          blocked.id,
          'worker-2',
          'question',
          2,
          'Manticore is already on the stand — use it, or the database search with trigrams?',
          { from: 'in_progress', to: 'blocked' },
        ),
      ],
      created_at: ago(20),
      updated_at: ago(2),
      closed_at: null,
    },
    {
      id: 3,
      project_id: 2,
      number: 1,
      title: 'Guide for the task queue',
      status: 'draft',
      priority: 'low',
      spec: 'A page in the guide: how a person writes a task and how agents work it.',
      acceptance: null,
      branch: 'docs/tasks',
      stages: [
        stage(1, 'Write the guide', 'pending'),
        stage(2, 'Screenshots', 'pending', { review_mode: 'human' }),
      ],
      journal: [entry(null, PERSON, 'status', 4, null, { from: null, to: 'draft' })],
      created_at: ago(4),
      updated_at: ago(4),
      closed_at: null,
    },
    {
      id: 4,
      project_id: 2,
      number: 2,
      title: 'Fix broken links',
      status: 'done',
      priority: 'normal',
      spec: null,
      acceptance: null,
      branch: null,
      stages: [
        stage(1, 'Find and fix', 'done', {
          assignee: 'worker-4',
          report: 'Fixed 7 links.',
          accepted_at: ago(50),
        }),
      ],
      journal: [],
      created_at: ago(60),
      updated_at: ago(50),
      closed_at: ago(50),
    },
  ]
})()

function derive(task: Task): void {
  if (task.status === 'draft' || task.status === 'cancelled') return

  const open = task.stages.filter((one) => one.status !== 'cancelled').map((one) => one.status)
  const any = (...statuses: StageStatus[]) => open.some((one) => statuses.includes(one))

  task.status = any('in_progress', 'rework', 'blocked')
    ? 'in_progress'
    : open.length > 0 && open.every((one) => one === 'done')
      ? 'done'
      : any('review')
        ? 'review'
        : 'ready'
  task.closed_at = task.status === 'done' ? new Date().toISOString() : null
}

function advance(task: Task): void {
  const next = task.stages.find((one) => one.status !== 'done' && one.status !== 'cancelled')

  if (next?.status === 'pending') {
    next.status = 'ready'
    task.journal.push(entry(next.id, 'system', 'status', 0, null, { from: 'pending', to: 'ready' }))
  }
}

function move(
  task: Task,
  one: Stage,
  to: StageStatus,
  kind = 'status',
  body: string | null = null,
): void {
  task.journal.push(
    entry(one.id, PERSON, kind, 0, body, { from: one.status, to, attempt: one.attempt }),
  )
  one.status = to
  one.status_changed_at = new Date().toISOString()
  task.updated_at = one.status_changed_at
}

const projectOf = (task: Task) => projects.find((one) => one.id === task.project_id)!

function row(task: Task) {
  const current = task.stages.find((one) => one.status !== 'done' && one.status !== 'cancelled')

  return {
    id: task.id,
    ref: `${projectOf(task).slug}#${task.number}`,
    project: projectOf(task).slug,
    number: task.number,
    title: task.title,
    status: task.status,
    priority: task.priority,
    branch: task.branch,
    stages: task.stages.length,
    done: task.stages.filter((one) => one.status === 'done').length,
    current_stage: current
      ? {
          id: current.id,
          position: current.position,
          title: current.title,
          status: current.status,
          review_mode: current.review_mode,
          assignee: current.assignee,
          attempt: current.attempt,
        }
      : null,
    updated_at: task.updated_at,
  }
}

function full(task: Task) {
  return {
    ...row(task),
    spec: task.spec,
    acceptance: task.acceptance,
    created_at: task.created_at,
    closed_at: task.closed_at,
    stages: task.stages,
    journal: task.journal,
  }
}

const waiting = (task: Task) =>
  task.stages.some(
    (one) => (one.status === 'review' && one.review_mode === 'human') || one.status === 'blocked',
  )

/** What the manifest puts beside «Tasks» in the menu. */
export function tasksBadge(): number {
  return tasks.filter(
    (task) => !['draft', 'done', 'cancelled'].includes(task.status) && waiting(task),
  ).length
}

const VIEWS: Record<string, (task: Task) => boolean> = {
  all: () => true,
  waiting,
  active: (task) => ['ready', 'in_progress', 'review'].includes(task.status),
  draft: (task) => task.status === 'draft',
  closed: (task) => task.status === 'done' || task.status === 'cancelled',
}

/* The notification settings (§6a): a token is never answered, only whether one is set. */
const notify: Record<string, unknown> = {
  'tasks.telegram.chats': '',
  'tasks.notify.review_human': true,
  'tasks.notify.task_done': true,
  'tasks.notify.blocked': true,
  'tasks.notify.rework': false,
  'tasks.notify.review_auto': false,
  'tasks.notify.stale': true,
  'tasks.notify.stale_hours': 6,
}
let tokenSet = false

const settingsAnswer = () => ({
  data: { values: { ...notify, 'tasks.telegram.token': '' }, token_set: tokenSet },
})

export function registerTasks(on: On, fail: Fail, line: Line): void {
  on('GET', '/tasks/settings', settingsAnswer)

  on('PUT', '/tasks/settings', ({ body }) => {
    const values = (body.values ?? {}) as Record<string, unknown>
    const token = values['tasks.telegram.token']

    for (const key of Object.keys(notify)) if (key in values) notify[key] = values[key]
    if (body.forget_token) tokenSet = false
    else if (typeof token === 'string' && token.trim() !== '') tokenSet = true

    return settingsAnswer()
  })

  /* No Telegram here: the button answers as the real one does when nothing is set up. */
  on('POST', '/tasks/settings/test', ({ locale }) => {
    if (!tokenSet || String(notify['tasks.telegram.chats']).trim() === '') {
      throw fail(409, line(locale, 'webx-tasks', 'errors.notify-not-configured'))
    }

    return { message: line(locale, 'webx-tasks', 'panel.test-sent') }
  })

  const refuse = (locale: string, key: string) =>
    fail(409, line(locale, 'webx-tasks', `errors.${key}`))

  const findTask = (value: string) => {
    const task = tasks.find((one) => one.id === Number(value))
    if (!task) throw fail(404, 'Not found.')
    return task
  }

  const findStage = (value: string) => {
    for (const task of tasks) {
      const one = task.stages.find((candidate) => candidate.id === Number(value))
      if (one) return { task, one }
    }
    throw fail(404, 'Not found.')
  }

  const text = (body: Record<string, unknown>, key: string) =>
    typeof body[key] === 'string' ? (body[key] as string).trim() : ''

  on('GET', '/tasks/projects', () => ({
    data: projects.map((project) => ({
      ...project,
      tasks_count: tasks.filter((task) => task.project_id === project.id).length,
    })),
  }))

  on('POST', '/tasks/projects', ({ body }) => {
    const project: Project = {
      id: id(),
      slug: text(body, 'slug'),
      title: text(body, 'title'),
      workdir: (body.workdir as string) || null,
      repo_url: (body.repo_url as string) || null,
      default_branch: (body.default_branch as string) || null,
      rules: (body.rules as string) || null,
      archived: false,
    }
    projects.push(project)
    return { data: project }
  })

  on('PUT', '/tasks/projects/(\\d+)', ({ params, body }) => {
    const project = projects.find((one) => one.id === Number(params[0]))
    if (!project) throw fail(404, 'Not found.')
    Object.assign(project, body)
    return { data: project }
  })

  on('GET', '/tasks/tasks', ({ query }) => {
    const view = query.get('view') ?? 'all'
    const project = Number(query.get('project')) || null
    const search = (query.get('q') ?? '').toLowerCase()
    const base = tasks.filter(
      (task) =>
        (project === null || task.project_id === project) &&
        (search === '' || task.title.toLowerCase().includes(search)),
    )
    const rank = { high: 0, normal: 1, low: 2 }
    const rows = base
      .filter(VIEWS[view] ?? VIEWS.all!)
      .sort(
        (a, b) => rank[a.priority] - rank[b.priority] || b.updated_at.localeCompare(a.updated_at),
      )
      .map(row)

    return {
      data: rows,
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: rows.length,
      counts: Object.fromEntries(
        Object.entries(VIEWS).map(([key, test]) => [key, base.filter(test).length]),
      ),
    }
  })

  on('GET', '/tasks/tasks/(\\d+)', ({ params }) => ({ data: full(findTask(params[0]!)) }))

  on('POST', '/tasks/tasks', ({ body }) => {
    const projectId = Number(body.project_id)
    const stages = Array.isArray(body.stages) ? (body.stages as Record<string, unknown>[]) : []
    const task: Task = {
      id: id(),
      project_id: projectId,
      number:
        Math.max(
          0,
          ...tasks.filter((one) => one.project_id === projectId).map((one) => one.number),
        ) + 1,
      title: text(body, 'title'),
      status: 'draft',
      priority: (body.priority as Task['priority']) ?? 'normal',
      spec: (body.spec as string) || null,
      acceptance: (body.acceptance as string) || null,
      branch: (body.branch as string) || null,
      stages: stages.map((values, index) => ({
        ...stage(index + 1, String(values.title ?? ''), 'pending'),
        brief: (values.brief as string) || null,
        acceptance: (values.acceptance as string) || null,
        review_mode: values.review_mode === 'human' ? 'human' : 'auto',
      })),
      journal: [entry(null, PERSON, 'status', 0, null, { from: null, to: 'draft' })],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      closed_at: null,
    }
    tasks.push(task)
    return { data: full(task) }
  })

  on('PUT', '/tasks/tasks/(\\d+)', ({ params, body, locale }) => {
    const task = findTask(params[0]!)
    const draft = task.status === 'draft'

    if (!draft && ['title', 'spec', 'acceptance', 'branch'].some((key) => key in body)) {
      throw refuse(locale, 'task-started')
    }

    for (const key of ['title', 'priority', 'spec', 'acceptance', 'branch'] as const) {
      if (key in body) (task as unknown as Record<string, unknown>)[key] = body[key]
    }

    if (Array.isArray(body.stages)) {
      const given = body.stages as Record<string, unknown>[]
      task.stages = given.map((values, index) => {
        const existing = task.stages.find((one) => one.id === values.id)
        const base = existing ?? stage(index + 1, '', 'pending')

        return existing && !draft && !['pending', 'ready'].includes(existing.status)
          ? existing
          : {
              ...base,
              position: index + 1,
              title: String(values.title ?? base.title),
              brief: (values.brief as string) ?? base.brief,
              acceptance: (values.acceptance as string) ?? base.acceptance,
              review_mode: values.review_mode === 'human' ? 'human' : 'auto',
            }
      })
      if (!draft) advance(task)
    }

    task.journal.push(entry(null, PERSON, 'edit', 0))
    task.updated_at = new Date().toISOString()
    derive(task)
    return { data: full(task) }
  })

  on('POST', '/tasks/tasks/(\\d+)/ready', ({ params, locale }) => {
    const task = findTask(params[0]!)
    if (task.status !== 'draft') throw refuse(locale, 'task-not-draft')
    if (task.stages.length === 0) throw refuse(locale, 'task-no-stages')
    task.journal.push(entry(null, PERSON, 'status', 0, null, { from: 'draft', to: 'ready' }))
    task.status = 'ready'
    advance(task)
    derive(task)
    return { data: full(task) }
  })

  on('POST', '/tasks/tasks/(\\d+)/cancel', ({ params, body }) => {
    const task = findTask(params[0]!)
    for (const one of task.stages) if (one.status !== 'done') move(task, one, 'cancelled')
    task.journal.push(
      entry(null, PERSON, 'status', 0, text(body, 'reason') || null, {
        from: task.status,
        to: 'cancelled',
      }),
    )
    task.status = 'cancelled'
    task.closed_at = new Date().toISOString()
    return { data: full(task) }
  })

  on('POST', '/tasks/tasks/(\\d+)/comments', ({ params, body, locale }) => {
    const task = findTask(params[0]!)
    const said = text(body, 'body')
    if (said === '') throw refuse(locale, 'text-required')
    task.journal.push(entry((body.stage_id as number) ?? null, PERSON, 'comment', 0, said))
    return { data: full(task) }
  })

  on('POST', '/tasks/stages/(\\d+)/accept', ({ params, locale }) => {
    const { task, one } = findStage(params[0]!)
    if (one.status !== 'review') throw refuse(locale, 'stage-not')
    one.accepted_at = new Date().toISOString()
    move(task, one, 'done')
    advance(task)
    derive(task)
    return { data: full(task) }
  })

  on('POST', '/tasks/stages/(\\d+)/rework', ({ params, body, locale }) => {
    const { task, one } = findStage(params[0]!)
    if (one.status !== 'review') throw refuse(locale, 'stage-not')
    one.attempt += 1
    move(task, one, 'rework', 'rework', text(body, 'remarks'))
    derive(task)
    return { data: full(task) }
  })

  on('POST', '/tasks/stages/(\\d+)/unblock', ({ params, body, locale }) => {
    const { task, one } = findStage(params[0]!)
    if (one.status !== 'blocked') throw refuse(locale, 'stage-not')
    one.assignee = null
    move(task, one, 'ready', 'answer', text(body, 'answer'))
    derive(task)
    return { data: full(task) }
  })

  on('POST', '/tasks/stages/(\\d+)/release', ({ params, locale }) => {
    const { task, one } = findStage(params[0]!)
    if (one.status !== 'in_progress') throw refuse(locale, 'stage-not')
    one.assignee = null
    move(task, one, one.attempt > 1 ? 'rework' : 'ready')
    derive(task)
    return { data: full(task) }
  })

  on('POST', '/tasks/stages/(\\d+)/cancel', ({ params, locale }) => {
    const { task, one } = findStage(params[0]!)
    if (one.status === 'done' || one.status === 'cancelled')
      throw refuse(locale, 'stage-transition')
    move(task, one, 'cancelled')
    if (task.status !== 'draft') advance(task)
    derive(task)
    return { data: full(task) }
  })
}
