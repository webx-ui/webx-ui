import type { Translate } from '@webx-ui/module-admin'

/** The sources the panel has a word for; a gateway's or a carrier's own name stays as it is. */
const KNOWN = ['panel', 'api', 'mcp', 'checkout', 'quick', 'command']

/**
 * Who wrote a line of the order's history — the timeline's and the notes' alike: the
 * administrator's name where one is known; otherwise the source in the panel's words («Site
 * API», «Agent»), or as the module that wrote it named itself.
 */
export function authorOf(t: Translate, source: string, admin: string | null): string {
  if (source === 'mcp') {
    return admin ? t('panel.source-mcp-admin', { name: admin }) : t('panel.source-mcp')
  }

  if (admin) return admin
  if (source.startsWith('status#')) return t('panel.source-status')
  if (KNOWN.includes(source)) return t(`panel.source-${source}`)

  return source
}
