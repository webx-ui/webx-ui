type Translate = (key: string, replace?: Record<string, string | number>) => string

/**
 * `total_minutes` the way the list says it: `45 min`, `1 h 15 min`, `2 h`. The number is at the
 * end of nothing and the start of nothing a plural would bend (CLAUDE.md §4 on `:count`), so one
 * line per shape is enough in every language.
 */
export function formatMinutes(minutes: number | null | undefined, t: Translate): string {
  if (minutes == null || minutes <= 0) return ''

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  if (hours === 0) return t('panel.minutes', { count: rest })
  if (rest === 0) return t('panel.hours', { hours })

  return t('panel.hours-minutes', { hours, minutes: rest })
}
