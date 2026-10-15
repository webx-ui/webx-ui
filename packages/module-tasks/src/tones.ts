import type { BadgeType } from '@webx-ui/core'
import type { Priority, StageStatus, TaskStatus } from './types'

/**
 * The colour each status is said in. One table for the list and the task, so a stage that waits
 * for a person is the same colour in both: warning is "somebody has to act", primary is "work is
 * going", success and quiet are over.
 */
export const taskTones: Record<TaskStatus, BadgeType> = {
  draft: 'default',
  ready: 'info',
  in_progress: 'primary',
  review: 'warning',
  done: 'success',
  cancelled: 'default',
}

export const stageTones: Record<StageStatus, BadgeType> = {
  pending: 'default',
  ready: 'info',
  in_progress: 'primary',
  review: 'warning',
  rework: 'danger',
  blocked: 'warning',
  done: 'success',
  cancelled: 'default',
}

export const priorityTones: Record<Priority, BadgeType> = {
  low: 'default',
  normal: 'info',
  high: 'danger',
}
