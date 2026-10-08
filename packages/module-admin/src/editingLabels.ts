import { onMounted, shallowRef } from 'vue'
import { useLocales } from '@webx-ui/core'
import type { ScreenNode } from '@webx-ui/schema'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'
import type { MergeSegment } from './merge'
import type { BlockLabels } from './types'

export interface EditingLabelsOptions {
  /** The screen the record is edited on: its fields are named the way the form names them. */
  screen?: string
  /** What a top-level field is called, when the editor knows better than the screen. */
  field?: (name: string) => string | undefined
  /** What a type of block is called, when the editor knows better than the block library. */
  block?: (type: string) => string | undefined
}

export interface EditingLabels {
  /** A place in the record as the person reads it: «Hero › Eyebrow · EN». */
  label(path: MergeSegment[]): string
}

/**
 * Names for the places in a record, the way the person who edits it knows them.
 *
 * A notice that says «Hero › Below cta · EN» has turned `below_cta` into words by guessing; the
 * block type calls that field «Text below the button», and that is the name the person looks for
 * in the form. The screen names the record's own fields, the block library names the blocks and
 * their fields, and a key nobody named is turned into words as a last resort.
 */
export function useEditingLabels(options: EditingLabelsOptions = {}): EditingLabels {
  const admin = useAdmin()
  const t = useTranslate('webx-admin')
  const locales = useLocales()

  const fields = shallowRef<Map<string, string>>(new Map())
  const blocks = shallowRef<BlockLabels | null>(null)

  onMounted(() => {
    if (options.screen && typeof admin.loadScreen === 'function') {
      admin
        .loadScreen(options.screen)
        .then((root) => (fields.value = screenLabels(root)))
        .catch(() => undefined)
    }

    // A hand-made context in a test, or a panel without the block library: keys turned into words.
    admin
      .blockLabels?.()
      .then((labels) => (blocks.value = labels))
      .catch(() => undefined)
  })

  function blockName(type: string): string {
    return options.block?.(type) ?? blocks.value?.type(type) ?? humanize(type || t('editing.block'))
  }

  function label(path: MergeSegment[]): string {
    const codes = new Set(locales.list.value.map((locale) => locale.code))
    const parts: string[] = []
    let language = ''
    // The type of the block whose own field this step may be: set on a block, kept over `values`.
    let inBlock: string | null = null

    path.forEach((step, index) => {
      if ('block' in step) {
        parts.push(blockName(step.type))
        inBlock = step.type

        return
      }

      // A block's fields sit under `values`, which is the shape of a node and not a word anybody
      // would recognise.
      if (step.field === 'values' && index > 0 && 'block' in path[index - 1]!) return

      // Nor is the field that holds a list of blocks: «Hero» says where it is, «Blocks › Hero»
      // only says it twice.
      const next = path[index + 1]

      if (next !== undefined && 'block' in next) return

      if (index === path.length - 1 && index > 0 && codes.has(step.field)) {
        language = step.field.toUpperCase()

        return
      }

      const owner = inBlock
      inBlock = null

      const named =
        owner !== null
          ? blocks.value?.field(owner, step.field)
          : index === 0
            ? (options.field?.(step.field) ?? fields.value.get(step.field))
            : undefined

      parts.push(named ?? humanize(step.field))
    })

    const named = parts.length === 0 ? t('editing.order') : parts.join(' › ')

    return language === '' ? named : `${named} · ${language}`
  }

  return { label }
}

/** Every named field of a screen tree with its label, wherever it sits — in a tab, in a card. */
export function screenLabels(root: ScreenNode[]): Map<string, string> {
  const found = new Map<string, string>()

  const walk = (nodes: ScreenNode[]): void => {
    for (const node of nodes) {
      // A block's schema keys its values by `id` where a screen names them; both are read.
      const name = (node as { name?: unknown }).name ?? (node as { id?: unknown }).id
      const label = (node as { label?: unknown }).label

      if (typeof name === 'string' && typeof label === 'string' && label !== '' && !found.has(name))
        found.set(name, label)

      if (Array.isArray(node.children)) walk(node.children)
    }
  }

  walk(root)

  return found
}

export function humanize(name: string): string {
  const words = name.replace(/[_-]+/g, ' ').trim()

  return words.charAt(0).toUpperCase() + words.slice(1)
}
