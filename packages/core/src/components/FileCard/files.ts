import { resolveIcon } from '../Icon/icons'
import type { IconName } from '../Icon/types'

/**
 * The extension of a file name, lower case and without the dot. A URL is accepted too:
 * the query and the fragment are cut off first, because `photo.jpg?v=2` is a JPEG.
 */
export function extensionOf(name: string): string {
  const path = name.split(/[?#]/)[0]
  const base = path.slice(path.lastIndexOf('/') + 1)
  const dot = base.lastIndexOf('.')
  /* A leading dot is a dotfile, not an extension: `.gitignore` has none. */
  return dot > 0 ? base.slice(dot + 1).toLowerCase() : ''
}

/** Extensions a browser will draw in an `<img>`. */
const PICTURES = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg', 'ico'])

/**
 * Whether the file is a picture, and so has a preview rather than a glyph.
 *
 * The MIME type is asked first because it is the one that knows: an extension is a
 * guess a file name makes about itself. `image/*` minus the formats no browser draws —
 * a TIFF is an image and still renders as a broken picture.
 */
export function isPicture(name: string, type?: string): boolean {
  if (type) {
    if (!type.startsWith('image/')) return false
    const subtype = type.slice('image/'.length).split(';')[0].trim().toLowerCase()
    return PICTURES.has(subtype === 'svg+xml' ? 'svg' : subtype)
  }
  return PICTURES.has(extensionOf(name))
}

/**
 * The icon for a file, by extension.
 *
 * The registry is what answers, not a list kept here, so `registerIcons({ 'file-dwg': … })`
 * in an application is all it takes for a `.dwg` to have its own drawing — no release of
 * this library, and nothing to pass per card.
 */
export function fileIconName(name: string): IconName {
  const ext = extensionOf(name)
  if (!ext) return 'file-generic'
  const named = `file-${ext}`
  return resolveIcon(named) ? named : 'file-generic'
}
