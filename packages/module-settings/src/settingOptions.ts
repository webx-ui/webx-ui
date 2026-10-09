import type { AutocompleteOption } from '@webx-ui/core'
import type { ScreenModel, ScreenNode } from '@webx-ui/schema'

/**
 * The types whose value a shortcode can print as it is: a line of text or a number. A picture, a
 * list or a switch would print as nothing, so they are not offered.
 */
const PRINTABLE = new Set(['wx-input', 'wx-textarea', 'wx-input-number', 'wx-autocomplete'])

/** The list the shortcodes keep themselves in — reading it would print nothing. */
const OWN_KEY = 'shortcodes.data'

/** Long enough to recognise an address by, short enough for one line of the dropdown. */
const PREVIEW = 40

/**
 * The settings a shortcode can read, for the autocomplete of its key: labelled the way the form
 * shows them («Contacts → Phone»), with the key and what the setting holds now underneath, so the
 * one being picked is the one meant.
 */
export function settingOptions(
  root: ScreenNode[],
  values: ScreenModel,
  locale: string,
): AutocompleteOption[] {
  const options: AutocompleteOption[] = []

  const walk = (nodes: ScreenNode[], trail: string[]): void => {
    for (const node of nodes) {
      // A repeater's children are fields of its rows, not settings.
      if (node.type === 'wx-repeater') continue

      if (node.name !== undefined) {
        if (PRINTABLE.has(node.type) && node.name !== OWN_KEY) {
          const label = [...trail, node.label ?? node.name].join(' → ')
          const now = preview(values[node.name], locale)

          options.push({
            value: node.name,
            label,
            description: now === '' ? node.name : `${node.name} · ${now}`,
          })
        }
        continue
      }

      // Tabs and cards name a group; the rest of the layout does not.
      const named = (node.type === 'wx-tab' || node.type === 'wx-card') && node.label
      walk(node.children ?? [], named ? [...trail, node.label as string] : trail)
    }
  }

  walk(root, [])

  return options
}

/** A value as a line: the panel's language of a translated one, or the first language filled. */
function preview(value: unknown, locale: string): string {
  let text = value

  if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
    const languages = value as Record<string, unknown>
    text =
      languages[locale] ||
      Object.values(languages).find((one) => typeof one === 'string' && one !== '')
  }

  if (typeof text === 'number') text = String(text)
  if (typeof text !== 'string') return ''

  const line = text.replace(/\s+/g, ' ').trim()

  return line.length > PREVIEW ? `${line.slice(0, PREVIEW - 1)}…` : line
}
