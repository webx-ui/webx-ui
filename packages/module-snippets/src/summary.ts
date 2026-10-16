import type { Translate } from '@webx-ui/module-admin'
import type { SnippetRow } from './types'

/**
 * Where a code is printed, in a few words for its row: the whole site, or how many paths it is
 * limited to and kept away from. The masks themselves are in the form — a row that lists ten
 * of them is a row nobody reads.
 */
export function coverageOf(
  row: Pick<SnippetRow, 'only_paths' | 'except_paths'>,
  t: Translate,
): string {
  const parts: string[] = []

  parts.push(
    row.only_paths.length > 0
      ? t('panel.only-paths', { count: row.only_paths.length })
      : t('panel.whole-site'),
  )

  if (row.except_paths.length > 0) {
    parts.push(t('panel.except-paths', { count: row.except_paths.length }))
  }

  return parts.join(' · ')
}

/** The languages of a code, or "all" for none chosen — which is what none chosen means. */
export function localesOf(row: Pick<SnippetRow, 'locales'>, t: Translate): string {
  return row.locales.length > 0
    ? row.locales.map((code) => code.toUpperCase()).join(' · ')
    : t('panel.all-locales')
}

/**
 * The places a code may be dragged between: only declared ones. A place taken out of the config
 * prints nothing, so nothing is dropped into it, and the server refuses its order anyway.
 */
export const DRAG_GROUP = 'wx-snippets'
