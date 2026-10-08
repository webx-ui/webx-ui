import { readonly, ref, type Ref } from 'vue'
import type { ScreenNode } from '@webx-ui/schema'
import { cloneNode, countType, walk } from './content'
import { allows } from './move'
import { kindOf } from './schema'
import type { BlockNode, BlockType } from './types'

/**
 * Copy and paste of blocks, across pages and across modules of the same panel.
 *
 * The clipboard is the browser's storage rather than the system one: a block is not text, the
 * system clipboard asks for permission to be read, and what is copied here is only ever pasted
 * into a constructor of the same site — every module stores the same `{ key, type, values }`.
 * One entry, kept after a paste, so the same block can go onto several pages; every tab sees
 * what another one copied.
 */

export const CLIPBOARD_KEY = 'wx-blocks-clipboard'

export interface BlocksClip {
  format: 'webx-blocks-clip'
  /** Bumped when the shape changes; a clip of another version is treated as empty. */
  format_version: 1
  copied_at: string
  nodes: BlockNode[]
}

const clip = ref<BlocksClip | null>(null)
let listening = false

/** Storage that may be missing or refuse: a private window, blocked site data, a test. */
function storage(): Storage | null {
  try {
    return typeof window === 'undefined' ? null : window.localStorage
  } catch {
    return null
  }
}

/** What is stored, if it is a clip this code understands — anything else is no clip at all. */
export function parseClip(raw: string | null): BlocksClip | null {
  if (!raw) return null

  try {
    const value = JSON.parse(raw) as Partial<BlocksClip> | null

    if (
      value?.format !== 'webx-blocks-clip' ||
      value.format_version !== 1 ||
      !Array.isArray(value.nodes) ||
      value.nodes.length === 0
    ) {
      return null
    }

    return value as BlocksClip
  } catch {
    return null
  }
}

function read(): BlocksClip | null {
  try {
    return parseClip(storage()?.getItem(CLIPBOARD_KEY) ?? null)
  } catch {
    return null
  }
}

/* One listener for the whole page, never removed: the clip is one value for every field on it. */
function listen(): void {
  if (listening || typeof window === 'undefined') return

  listening = true
  clip.value = read()
  window.addEventListener('storage', (event) => {
    if (event.key === CLIPBOARD_KEY || event.key === null) clip.value = read()
  })
}

export function useBlocksClipboard(): {
  clip: Readonly<Ref<BlocksClip | null>>
  copy: (nodes: BlockNode[]) => void
  clear: () => void
} {
  listen()

  return {
    clip: readonly(clip) as Readonly<Ref<BlocksClip | null>>,
    copy(nodes) {
      const next: BlocksClip = {
        format: 'webx-blocks-clip',
        format_version: 1,
        copied_at: new Date().toISOString(),
        nodes: JSON.parse(JSON.stringify(nodes)) as BlockNode[],
      }

      clip.value = next

      try {
        storage()?.setItem(CLIPBOARD_KEY, JSON.stringify(next))
      } catch {
        // Full or refused: the clip still works in this tab, which is most of what it is for.
      }
    },
    clear() {
      clip.value = null

      try {
        storage()?.removeItem(CLIPBOARD_KEY)
      } catch {
        // Nothing to do: there was nothing it could have kept either.
      }
    },
  }
}

/** Why a block of the clip did not go in. */
export type SkipReason = 'unknown' | 'place' | 'limit' | 'full'

/** The list a paste goes into, and the rules that list stands under. */
export interface PasteTarget {
  /** The whole tree of the page, for the per-page limits of the types. */
  tree: BlockNode[]
  /** The list itself — the tree, or a container's field. */
  list: BlockNode[]
  /** The container's type, or the top level's owner (a block's sample form), or null. */
  parent: BlockType | null
  allow: string[] | null
  max: number | null
  /** What the list is called in `allowed_in`; only the top level is ever anything but `root`. */
  root: string
}

export interface PastePlan {
  /** Fresh copies, keys renewed all the way down, in the clip's order. */
  accepted: BlockNode[]
  skipped: { node: BlockNode; reason: SkipReason }[]
}

/**
 * What of the clip may go into this list, judged by the same rules the picker and "Move to"
 * go by, so a paste never puts down what adding by hand would not.
 *
 * Block by block rather than all or nothing: a page copied whole into a region that takes half
 * of it gets that half, and the rest is named. A block whose type — or the type of anything
 * inside it — this site does not offer is left out whole: half a section is not that section.
 */
export function planPaste(
  nodes: BlockNode[],
  target: PasteTarget,
  catalog: BlockType[],
): PastePlan {
  const accepted: BlockNode[] = []
  const skipped: PastePlan['skipped'] = []
  const offered = (slug: string) =>
    catalog.find(
      (type) =>
        type.slug === slug &&
        kindOf(type) !== 'component' &&
        type.is_enabled &&
        type.published !== null,
    ) ?? null

  for (const node of nodes) {
    const type = offered(node.type)
    let known = type !== null

    walk([node], (inner) => {
      if (!catalog.some((entry) => entry.slug === inner.type)) known = false
    })

    if (!type || !known) {
      skipped.push({ node, reason: 'unknown' })
      continue
    }

    if (!allows(type, target.parent, target.allow, target.root)) {
      skipped.push({ node, reason: 'place' })
      continue
    }

    if (target.max !== null && target.list.length + accepted.length >= target.max) {
      skipped.push({ node, reason: 'full' })
      continue
    }

    const page = [...target.tree, ...accepted]
    let over = false

    walk([node], (inner) => {
      const limit = catalog.find((entry) => entry.slug === inner.type)?.max_per_entity ?? null

      if (limit !== null && countType(page, inner.type) + countType([node], inner.type) > limit) {
        over = true
      }
    })

    if (over) {
      skipped.push({ node, reason: 'limit' })
      continue
    }

    accepted.push(cloneNode(node))
  }

  return { accepted, skipped }
}

/** A container field's own rules, read off its node in the type's schema. */
export function slotRules(slot: ScreenNode | null): { allow: string[] | null; max: number | null } {
  return {
    allow: (slot?.props?.allow as string[] | undefined) ?? null,
    max: (slot?.props?.max as number | undefined) ?? null,
  }
}
