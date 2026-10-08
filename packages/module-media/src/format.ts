/** Bytes as somebody would say them: 32 MB, not 33 554 432. */
export function readable(bytes: number): string {
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let size = bytes
  let unit = 0

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit++
  }

  return `${size >= 10 || unit === 0 ? Math.round(size) : size.toFixed(1)} ${units[unit]}`
}

/**
 * What a file is, in one line: `JPG · image/jpeg · 1200×800 · 240 KB`.
 *
 * Names in the library are titles without an extension, so this is where the format is said
 * in words — the line under a card in a field, and the tip over one in the grid.
 */
export function details(file: {
  extension: string
  mime?: string
  width: number | null
  height: number | null
  size: number
}): string {
  return [
    file.extension ? file.extension.toUpperCase() : null,
    file.mime || null,
    file.width && file.height ? `${file.width}×${file.height}` : null,
    readable(file.size),
  ]
    .filter(Boolean)
    .join(' · ')
}

/** The file name out of a stored key, for a file the library can no longer be asked about. */
export function nameFromPath(path: string): string {
  return path.split('/').pop() || path
}
