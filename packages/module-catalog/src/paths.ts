/**
 * The last segment of an address on the site, without slashes: `/laptops/` → `laptops`,
 * `https://shop.test/en/lamp-12` → `lamp-12`. The panel compares it with the slug being typed;
 * the language prefix and the host in front of it are not part of what the slug field edits.
 */
export function lastSegment(url: string | null | undefined): string | null {
  if (!url) return null

  let path: string

  try {
    path = new URL(url, 'http://panel.invalid').pathname
  } catch {
    return null
  }

  const segments = path.split('/').filter((segment) => segment !== '')

  return segments.length > 0 ? decodeURIComponent(segments[segments.length - 1]!) : null
}
