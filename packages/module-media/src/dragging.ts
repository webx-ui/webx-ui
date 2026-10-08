/**
 * Files of the library dragged from the grid onto a folder of the tree.
 *
 * A type of our own on the drag, so that nothing else mistakes it for its own: the drop zone
 * of the grid uploads anything that says `Files` — which a picture dragged in Chrome says too —
 * and the tree moves folders with its own drag, which carries none of this.
 */
export const FILES_TYPE = 'application/x-webx-media-files'

export function carriesLibraryFiles(event: DragEvent): boolean {
  return (event.dataTransfer?.types ?? []).includes(FILES_TYPE)
}

/** Start a drag of these ids, and nothing else: a picture's own data is dropped. */
export function startDrag(event: DragEvent, ids: number[]): void {
  const transfer = event.dataTransfer

  if (!transfer) return

  transfer.items.clear()
  transfer.setData(FILES_TYPE, JSON.stringify(ids))
  transfer.effectAllowed = 'move'
}

/** The ids a drop carries, or none: only a drop can read them, a drag over says the type only. */
export function droppedIds(event: DragEvent): number[] {
  try {
    const ids: unknown = JSON.parse(event.dataTransfer?.getData(FILES_TYPE) || '[]')

    return Array.isArray(ids) ? ids.filter((id): id is number => typeof id === 'number') : []
  } catch {
    return []
  }
}
