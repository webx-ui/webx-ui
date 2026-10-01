import type { BadgeType } from '@webx-ui/core'
import type { ExchangeRun } from './exchange'

type Translate = (key: string, params?: Record<string, string | number>) => string

export interface RunTotal {
  key: string
  text: string
  tone: 'success' | 'danger' | 'muted' | 'default'
}

/**
 * What a run did, as short lines: an import by its counters — only the ones that are not zero —
 * an export by the products it wrote. Counted at the end of each line ("Created: 12"), because
 * the panel has no plurals (CLAUDE.md §4).
 */
export function runTotals(run: ExchangeRun, t: Translate): RunTotal[] {
  if (run.direction === 'export') {
    return run.rows_done > 0 || run.status === 'done'
      ? [
          {
            key: 'exported',
            text: t('panel.exchange-exported', { count: run.rows_done }),
            tone: 'default',
          },
        ]
      : []
  }

  const lines: RunTotal[] = []
  const add = (key: string, count: number, tone: RunTotal['tone']) => {
    if (count > 0) lines.push({ key, text: t(`panel.exchange-${key}`, { count }), tone })
  }

  add('created', run.created, 'success')
  add('updated', run.updated, 'default')
  add('skipped', run.skipped, 'muted')
  add('absent', run.absent, 'default')
  if (run.failed > 0) {
    lines.push({
      key: 'failed',
      text: t('panel.exchange-errors-count', { count: run.failed }),
      tone: 'danger',
    })
  }

  return lines
}

const TONES: Record<ExchangeRun['status'], BadgeType> = {
  queued: 'default',
  running: 'info',
  done: 'success',
  stopped: 'warning',
  failed: 'danger',
}

/** The badge of a run's status. A finished import with errors is done, and says so in amber. */
export function runStatus(run: ExchangeRun, t: Translate): { label: string; type: BadgeType } {
  const type = run.status === 'done' && run.failed > 0 ? 'warning' : TONES[run.status]

  return { label: t(`panel.exchange-status-${run.status}`), type }
}
