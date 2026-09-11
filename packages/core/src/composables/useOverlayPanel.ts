/**
 * The pieces `WxDialog` and `WxDrawer` have in common: both take a size in pixels or
 * per cent, both can be dragged to a new one, and both can be told to remember it.
 */

/** A number is pixels, however it arrives — `width="520"` in a template is a string. */
export function cssLength(value: number | string | undefined): string | undefined {
  if (value === undefined || value === null || value === '') return undefined
  if (typeof value === 'number') return Number.isFinite(value) ? `${value}px` : undefined

  const trimmed = value.trim()
  return /^-?\d+(\.\d+)?$/.test(trimmed) ? `${trimmed}px` : trimmed
}

/**
 * The pixels behind a size prop, when there are any — `'60%'` has none, and a gesture
 * that starts on such a panel has to measure it instead.
 */
export function pixelLength(value: number | string | undefined): number | undefined {
  if (typeof value === 'number') return Number.isFinite(value) ? value : undefined
  if (typeof value !== 'string') return undefined

  const trimmed = value.trim()
  if (!/^-?\d+(\.\d+)?(px)?$/.test(trimmed)) return undefined
  return Number.parseFloat(trimmed)
}

export function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), Math.max(min, max))
}

export interface PanelMemory<T> {
  read: () => Partial<T> | null
  write: (value: T) => void
  clear: () => void
}

/**
 * `localStorage`, with every way it can fail treated the same way: remembering a panel's
 * size is a convenience, and a private window or a full quota only costs the default.
 */
export function usePanelMemory<T>(prefix: string, key: () => string | undefined): PanelMemory<T> {
  const storageKey = () => {
    const name = key()
    return name ? prefix + name : null
  }

  return {
    read() {
      const id = storageKey()
      if (!id || typeof window === 'undefined') return null
      try {
        const raw = window.localStorage.getItem(id)
        return raw ? (JSON.parse(raw) as Partial<T>) : null
      } catch {
        // Unavailable, or holding something we did not write.
        return null
      }
    },
    write(value: T) {
      const id = storageKey()
      if (!id || typeof window === 'undefined') return
      try {
        window.localStorage.setItem(id, JSON.stringify(value))
      } catch {
        // A private window or a full quota.
      }
    },
    clear() {
      const id = storageKey()
      if (!id || typeof window === 'undefined') return
      try {
        window.localStorage.removeItem(id)
      } catch {
        // Nothing to undo if it cannot be removed.
      }
    },
  }
}
