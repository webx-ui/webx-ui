/**
 * `WxImageGroup`'s own props. Everything else — `loop`, `thumbnails`, the labels — goes to
 * the lightbox it opens, except `class` and `style`, which stay on the group's element.
 */
export interface ImageGroupProps {
  /** The element the group renders as. */
  tag?: string
}
