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

/** The file name out of a stored key, for a file the library can no longer be asked about. */
export function nameFromPath(path: string): string {
  return path.split('/').pop() || path
}
