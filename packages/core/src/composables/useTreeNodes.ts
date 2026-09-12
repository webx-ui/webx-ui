import { computed, ref, toValue, type MaybeRefOrGetter, type Ref } from 'vue'

export type TreeKey = string | number

/** Where a dragged node lands relative to the one it was dropped on. */
export type TreeDropZone = 'before' | 'after' | 'inside'

/**
 * How to read one node. Everything else here works through these four, which is what
 * lets the same machinery drive a tree of its own and a table whose rows happen to
 * nest: neither has to agree with the other on what a node looks like.
 */
export interface TreeAccessors<T> {
  key: (node: T, path: number[]) => TreeKey
  children: (node: T) => T[] | undefined
  /** Called when a branch that had no `children` array needs one. */
  setChildren: (node: T, children: T[]) => void
  label?: (node: T) => string
  disabled?: (node: T) => boolean
  /** Says a node has nothing under it — only needed with `lazy`, where an empty
   * `children` cannot tell "not loaded yet" from "nothing there". */
  leaf?: (node: T) => boolean
}

/** One node and everything about where it sits, kept for the whole tree. */
export interface TreeEntry<T> {
  node: T
  key: TreeKey
  depth: number
  /** The array this node currently lives in — moving is a splice on it. */
  siblings: T[]
  index: number
  parent: T | null
  parentKey: TreeKey | null
  children: T[]
}

/** A node as the screen shows it: visible, in order, at a depth. */
export interface TreeRow<T> extends TreeEntry<T> {
  expandable: boolean
  expanded: boolean
  loading: boolean
  disabled: boolean
  /** Matched the filter itself, as opposed to merely leading to a match. */
  matched: boolean
}

export interface UseTreeNodesOptions<T> {
  /**
   * The tree. A getter or a plain array is enough for a tree that is only read; a
   * `ref` is what a tree that can be rearranged wants, since a move splices the array
   * the caller is holding.
   */
  nodes: MaybeRefOrGetter<T[]>
  accessors: TreeAccessors<T>
  /** Keys of the open branches. Owned by the caller, so `v-model:expanded` works. */
  expanded: Ref<TreeKey[]>
  /** Only branches matching this, and the branches leading to them, are shown. */
  filter?: MaybeRefOrGetter<string | undefined>
  /** Opening a branch closes the others at its level. */
  accordion?: MaybeRefOrGetter<boolean>
  /** Children arrive when a branch opens. Requires `load`. */
  lazy?: MaybeRefOrGetter<boolean>
  load?: (node: T) => T[] | Promise<T[]>
  /** Vetoes a move before it happens. */
  allowDrop?: (drag: T, drop: T, zone: TreeDropZone) => boolean
}

/**
 * The parts of a tree that have nothing to do with how it looks: what is where, what is
 * open, what a filter leaves standing, and what a move does to the arrays underneath.
 */
export function useTreeNodes<T>(options: UseTreeNodesOptions<T>) {
  const { nodes, accessors, expanded } = options

  const loading = ref(new Set<TreeKey>())
  /** Branches `load` has already answered for — an empty answer still counts. */
  const loaded = ref(new Set<TreeKey>())

  /*
   * Bumped when a fetched branch is written into its node. A tree handed over as a
   * plain array — which is what a tree arriving through a prop usually is — holds no
   * proxies, so writing children into one of its nodes changes nothing the index is
   * watching. This is what it watches instead.
   */
  const fetchedAt = ref(0)

  const entries = computed(() => {
    void fetchedAt.value

    const map = new Map<TreeKey, TreeEntry<T>>()

    const walk = (
      list: T[],
      parent: T | null,
      parentKey: TreeKey | null,
      depth: number,
      path: number[],
    ) => {
      list.forEach((node, index) => {
        const here = [...path, index]
        const key = accessors.key(node, here)
        const children = accessors.children(node) ?? []
        map.set(key, { node, key, depth, siblings: list, index, parent, parentKey, children })
        if (children.length) walk(children, node, key, depth + 1, here)
      })
    }

    walk(toValue(nodes), null, null, 0, [])
    return map
  })

  const entry = (key: TreeKey) => entries.value.get(key)
  const keyOf = (node: T, path: number[] = []) => accessors.key(node, path)

  /* -------------------------------------------------------------- filtering */

  /**
   * A filter answers with two sets rather than a new tree: the nodes that matched, and
   * every node that has to stay on screen for a match to be reachable. Rebuilding the
   * array instead would lose the identity the expansion state and the drop targets are
   * keyed by.
   */
  const filtered = computed(() => {
    const term = toValue(options.filter)?.trim().toLowerCase()
    if (!term || !accessors.label) return null

    const keep = new Set<TreeKey>()
    const matched = new Set<TreeKey>()

    for (const item of entries.value.values()) {
      if (!accessors.label(item.node).toLowerCase().includes(term)) continue
      matched.add(item.key)
      let step: TreeEntry<T> | undefined = item
      while (step) {
        keep.add(step.key)
        step = step.parentKey === null ? undefined : entries.value.get(step.parentKey)
      }
    }

    return { keep, matched }
  })

  /* ------------------------------------------------------------- expansion */

  const openKeys = computed(() => new Set(expanded.value))

  const isExpandable = (node: T, children: T[]) => {
    if (children.length) return true
    if (!toValue(options.lazy)) return false
    return !accessors.leaf?.(node)
  }

  const isExpanded = (key: TreeKey) => openKeys.value.has(key)

  /** Visible rows, top to bottom — what a list renders, filter and all. */
  const rows = computed<TreeRow<T>[]>(() => {
    const out: TreeRow<T>[] = []
    const match = filtered.value

    const walk = (list: T[], path: number[]) => {
      list.forEach((node, index) => {
        const here = [...path, index]
        const key = accessors.key(node, here)
        if (match && !match.keep.has(key)) return

        const item = entries.value.get(key)
        if (!item) return

        const children = item.children
        const expandable = isExpandable(node, children)
        // A filter opens every branch it kept: hiding a match behind a closed
        // parent is the one thing a search must never do.
        const open = match ? true : openKeys.value.has(key)

        out.push({
          ...item,
          expandable,
          expanded: expandable && open,
          loading: loading.value.has(key),
          disabled: accessors.disabled?.(node) ?? false,
          matched: match ? match.matched.has(key) : false,
        })

        if (open && children.length) walk(children, here)
      })
    }

    walk(toValue(nodes), [])
    return out
  })

  function setExpanded(key: TreeKey, open: boolean) {
    const has = openKeys.value.has(key)
    if (has === open) return

    if (!open) {
      expanded.value = expanded.value.filter((k) => k !== key)
      return
    }

    if (toValue(options.accordion)) {
      const item = entry(key)
      const siblings = new Set((item?.siblings ?? []).map((node, index) => keyOf(node, [index])))
      expanded.value = [...expanded.value.filter((k) => !siblings.has(k)), key]
      return
    }

    expanded.value = [...expanded.value, key]
  }

  /**
   * Opening a lazy branch for the first time is a fetch. The branch opens once the
   * children are in, so that a spinner is never followed by an empty box that then
   * fills — and a failed load leaves the branch closed and unmarked, free to retry.
   */
  async function expand(key: TreeKey) {
    const item = entry(key)
    if (!item) return

    if (toValue(options.lazy) && options.load && !loaded.value.has(key) && !item.children.length) {
      loading.value = new Set(loading.value).add(key)
      try {
        const children = await options.load(item.node)
        accessors.setChildren(item.node, children)
        loaded.value = new Set(loaded.value).add(key)
        fetchedAt.value += 1
      } finally {
        const next = new Set(loading.value)
        next.delete(key)
        loading.value = next
      }
    }

    setExpanded(key, true)
  }

  const collapse = (key: TreeKey) => setExpanded(key, false)

  const toggle = (key: TreeKey) => (isExpanded(key) ? collapse(key) : expand(key))

  function expandAll() {
    const keys: TreeKey[] = []
    for (const item of entries.value.values()) {
      if (item.children.length) keys.push(item.key)
    }
    expanded.value = keys
  }

  const collapseAll = () => {
    expanded.value = []
  }

  /**
   * Opens a named path, one level at a time, waiting for each level before asking for
   * the next. It is what `reveal` cannot do on a lazy tree: there the ancestors are not
   * in the tree yet, so the way to them has to be walked rather than looked up. Stops
   * at the first key the level above did not contain — a path into a branch somebody
   * has since moved or deleted opens as far as it still goes.
   */
  async function openPath(keys: TreeKey[]) {
    for (const key of keys) {
      if (!entry(key)) return false
      await expand(key)
    }
    return true
  }

  /** Opens every branch on the way to a node, so it can be scrolled to. */
  function reveal(key: TreeKey) {
    const path = ancestors(key).map((item) => item.key)
    if (!path.length) return
    const open = new Set(expanded.value)
    for (const step of path) open.add(step)
    expanded.value = [...open]
  }

  /* ------------------------------------------------------------- relations */

  function ancestors(key: TreeKey): TreeEntry<T>[] {
    const out: TreeEntry<T>[] = []
    let step = entry(key)
    while (step && step.parentKey !== null) {
      const parent = entry(step.parentKey)
      if (!parent) break
      out.unshift(parent)
      step = parent
    }
    return out
  }

  /** The node itself and everything above it — the breadcrumb of a tree. */
  const path = (key: TreeKey): TreeEntry<T>[] => {
    const item = entry(key)
    return item ? [...ancestors(key), item] : []
  }

  const isAncestorOf = (key: TreeKey, other: TreeKey) =>
    ancestors(other).some((item) => item.key === key)

  function descendants(key: TreeKey): TreeEntry<T>[] {
    const out: TreeEntry<T>[] = []
    const item = entry(key)
    if (!item) return out

    const walk = (list: T[]) => {
      for (const node of list) {
        const child = entry(keyOf(node))
        if (!child) continue
        out.push(child)
        walk(child.children)
      }
    }

    walk(item.children)
    return out
  }

  /* ----------------------------------------------------------------- moving */

  /**
   * Whether a drop is allowed at all. A node cannot land on itself or inside its own
   * subtree — the check every tree needs and the one every tree that skips it loses a
   * branch to.
   */
  function canDrop(dragKey: TreeKey, dropKey: TreeKey, zone: TreeDropZone) {
    if (dragKey === dropKey) return false

    const drag = entry(dragKey)
    const drop = entry(dropKey)
    if (!drag || !drop) return false
    if (isAncestorOf(dragKey, dropKey)) return false

    return options.allowDrop?.(drag.node, drop.node, zone) ?? true
  }

  /**
   * Performs the move and reports where the node ended up. The arrays are spliced in
   * place, so a caller holding the tree in a `ref` sees it rearranged without a copy.
   */
  function move(dragKey: TreeKey, dropKey: TreeKey, zone: TreeDropZone) {
    if (!canDrop(dragKey, dropKey, zone)) return null

    const drag = entry(dragKey)
    const drop = entry(dropKey)
    if (!drag || !drop) return null

    drag.siblings.splice(drag.index, 1)

    if (zone === 'inside') {
      let children = accessors.children(drop.node)
      if (!children) {
        children = []
        accessors.setChildren(drop.node, children)
      }
      children.push(drag.node)
      setExpanded(dropKey, true)
      return { node: drag.node, parent: drop.node, siblings: children, index: children.length - 1 }
    }

    // Read after the splice above: dropping below a node that sat under the dragged
    // one in the same array means its index has already moved up by one.
    const siblings = drop.siblings
    const at = siblings.indexOf(drop.node) + (zone === 'after' ? 1 : 0)
    siblings.splice(at, 0, drag.node)

    return { node: drag.node, parent: drop.parent, siblings, index: at }
  }

  return {
    rows,
    entries,
    entry,
    keyOf,
    filtered,
    isExpanded,
    expand,
    collapse,
    toggle,
    expandAll,
    collapseAll,
    reveal,
    openPath,
    ancestors,
    path,
    descendants,
    isAncestorOf,
    canDrop,
    move,
    loading,
  }
}

export type UseTreeNodes<T> = ReturnType<typeof useTreeNodes<T>>
