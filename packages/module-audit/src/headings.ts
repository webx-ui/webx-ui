/** One heading as the parser kept it: its level and its text. */
export type OutlineEntry = [number, string]

/** A row of the drawn tree — a heading, or a level the page jumped over (`missing`). */
export interface OutlineRow {
  level: number
  text: string
  missing: boolean
  /** For a missing level: the level the page jumped from. */
  after?: number
}

/**
 * The tree to draw: every heading, and between two of them the levels skipped on the way down,
 * so a gap stands where the heading should have been.
 */
export function outlineRows(outline: OutlineEntry[]): OutlineRow[] {
  const out: OutlineRow[] = []
  let previous = 0

  for (const [level, text] of outline) {
    for (let gap = previous + 1; previous > 0 && gap < level; gap++) {
      out.push({ level: gap, text: '', missing: true, after: previous })
    }

    out.push({ level, text, missing: false })
    previous = level
  }

  return out
}

/**
 * What breaks the usual order — one H1, first; each level one step below the one above; text in
 * every heading — as sentences in the reader's language. Empty when the order is fine.
 */
export function outlineHints(
  outline: OutlineEntry[],
  t: (key: string, params?: Record<string, string | number>) => string,
): string[] {
  if (!outline.length) return []

  const ones = outline.filter(([level]) => level === 1).length
  const jumps = outline.filter(
    ([level], index) => index > 0 && level > outline[index - 1][0] + 1,
  ).length
  const empty = outline.filter(([, text]) => text === '').length
  const out: string[] = []

  if (ones === 0) out.push(t('page.headings-no-h1'))
  if (ones > 1) out.push(t('page.headings-many-h1', { count: ones }))
  if (ones > 0 && outline[0][0] !== 1) {
    out.push(t('page.headings-h1-not-first', { level: `H${outline[0][0]}` }))
  }
  if (jumps > 0) out.push(t('page.headings-skipped', { count: jumps }))
  if (empty > 0) out.push(t('page.headings-empty', { count: empty }))

  return out
}
