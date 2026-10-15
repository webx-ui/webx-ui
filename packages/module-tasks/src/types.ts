import type { Paginated } from '@webx-ui/core'

/** Where a task is: `draft` and `cancelled` are set, the rest is what its stages add up to. */
export type TaskStatus = 'draft' | 'ready' | 'in_progress' | 'review' | 'done' | 'cancelled'

export type StageStatus =
  'pending' | 'ready' | 'in_progress' | 'review' | 'rework' | 'blocked' | 'done' | 'cancelled'

export type Priority = 'low' | 'normal' | 'high'

export type ReviewMode = 'auto' | 'human'

/** The tabs of the list (§6.1). */
export type TaskView = 'all' | 'waiting' | 'active' | 'draft' | 'closed'

export interface Project {
  id: number
  slug: string
  title: string
  workdir: string | null
  repo_url: string | null
  default_branch: string | null
  rules: string | null
  archived: boolean
  /** Only in the list of projects. */
  tasks_count?: number
}

export type ProjectInput = Partial<Omit<Project, 'id' | 'tasks_count'>>

/** The stage a task is on, as a row of the list shows it. */
export interface CurrentStage {
  id: number
  position: number
  title: string
  status: StageStatus
  review_mode: ReviewMode
  assignee: string | null
  attempt: number
}

export interface TaskRow {
  id: number
  /** `shop#12` — how people and agents name it. */
  ref: string
  project: string
  number: number
  title: string
  status: TaskStatus
  priority: Priority
  branch: string | null
  /** How many stages there are. In the full task this is the list of them instead. */
  stages: number
  done: number
  current_stage: CurrentStage | null
  updated_at: string | null
}

export interface Stage {
  id: number
  position: number
  title: string
  brief: string | null
  acceptance: string | null
  review_mode: ReviewMode
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

export type JournalKind =
  'status' | 'report' | 'rework' | 'question' | 'answer' | 'edit' | 'comment'

export interface JournalEntry {
  id: number
  stage_id: number | null
  actor_type: 'admin' | 'agent' | 'system'
  actor_name: string
  kind: JournalKind
  body: string | null
  meta: Record<string, unknown> | null
  created_at: string | null
}

export interface TaskDetail extends Omit<TaskRow, 'stages'> {
  spec: string | null
  acceptance: string | null
  created_at: string | null
  closed_at: string | null
  stages: Stage[]
  journal: JournalEntry[]
}

/** A stage as the form sends it: an id for one that exists, none for a new one. */
export interface StageInput {
  id?: number
  title?: string
  brief?: string | null
  acceptance?: string | null
  review_mode?: ReviewMode
}

export interface TaskInput {
  project_id?: number
  title?: string
  priority?: Priority
  spec?: string | null
  acceptance?: string | null
  branch?: string | null
  stages?: StageInput[]
}

export interface TaskQuery {
  view?: TaskView
  project?: number | null
  q?: string
  page?: number
  per_page?: number
}

/** Laravel's paginator, as the table takes it, with the count of every view for the tabs. */
export interface TasksPage extends Paginated<TaskRow> {
  counts: Record<TaskView, number>
}

/** The section's notification settings (§6a): the screen's values, and whether a bot token is set. */
export interface NotificationSettings {
  values: Record<string, unknown>
  token_set: boolean
}
