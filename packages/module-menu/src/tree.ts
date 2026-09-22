import type { MenuItemRow } from './types'

/** The item with this id, anywhere in the tree. */
export function findItem(items: MenuItemRow[], id: number): MenuItemRow | null {
  for (const item of items) {
    if (item.id === id) return item

    const found = findItem(item.children, id)

    if (found !== null) return found
  }

  return null
}

/** How many items are under this one — what a delete takes with it. */
export function countBranch(item: MenuItemRow): number {
  return item.children.reduce((total, child) => total + 1 + countBranch(child), 0)
}

/**
 * Which item moved, given one level before and after a drag.
 *
 * A drag between two levels changes two lists and the library says nothing about either beyond
 * their new contents, so what moved has to be read out of the difference. Three cases, and one
 * rule covers them: an id that is here and was not is the one that arrived; a level that lost
 * one says nothing, because the level that gained it will; and a level whose contents are the
 * same set in another order gives up the one id that, taken out of both, leaves them equal.
 *
 * Null means "nothing for the server to hear about" — a removal, or a drag that ended where it
 * started.
 */
export function movedId(before: number[], after: number[]): number | null {
  const arrived = after.find((id) => !before.includes(id))

  if (arrived !== undefined) return arrived
  if (after.length !== before.length) return null

  for (const id of after) {
    if (after.indexOf(id) === before.indexOf(id)) continue

    const rest = after.filter((other) => other !== id)
    const was = before.filter((other) => other !== id)

    if (rest.length === was.length && rest.every((other, index) => other === was[index])) {
      return id
    }
  }

  return null
}
