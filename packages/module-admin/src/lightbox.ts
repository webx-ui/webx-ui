import { openLightbox, type LightboxSource, type OpenLightboxOptions } from '@webx-ui/core'
import { useTranslate } from './i18n'

export interface PanelLightbox {
  /** The gallery's words, in the language the panel is being read in. */
  labels(): OpenLightboxOptions
  /** The gallery over the panel, on `start`. */
  open(items: LightboxSource[], start?: number): void
  /**
   * A click on a thumbnail: the gallery on a plain click, and the browser's own way with a
   * modified one — so a thumbnail that is also a link still opens in a new tab on Ctrl or the
   * middle button.
   */
  click(event: MouseEvent, items: LightboxSource[], start?: number): void
}

/**
 * Every picture the panel shows small opens large the same way: `WxLightbox`, over the whole
 * set it stands in — all the pictures of the product, all the files of the folder, all the
 * covers of the page — and on the full picture, never the thumbnail.
 *
 * The core speaks English only, so this is where it is handed the panel's words. They are read
 * when the gallery opens rather than kept in a computed: the gallery is mounted outside the
 * screen, handed plain strings once, and nothing re-renders it.
 *
 *     const lightbox = useLightbox()
 *     lightbox.open(images.map((image) => ({ src: image.url, thumb: image.thumb })), 2)
 *
 * A thumbnail that has no element of its own to click is wrapped in `WxPreviewThumb`.
 */
export function useLightbox(): PanelLightbox {
  const t = useTranslate('webx-admin')

  function labels(): OpenLightboxOptions {
    return {
      ariaLabel: t('lightbox.gallery'),
      prevLabel: t('lightbox.previous'),
      nextLabel: t('lightbox.next'),
      closeLabel: t('lightbox.close'),
      zoomInLabel: t('lightbox.zoom-in'),
      zoomOutLabel: t('lightbox.zoom-out'),
      originalLabel: t('lightbox.original'),
      playLabel: t('lightbox.play'),
      counterText: (index: number, total: number) => t('lightbox.counter', { index, total }),
    }
  }

  function open(items: LightboxSource[], start = 0): void {
    if (items.length === 0) return

    void openLightbox(items, { ...labels(), start: Math.min(Math.max(start, 0), items.length - 1) })
  }

  function click(event: MouseEvent, items: LightboxSource[], start = 0): void {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return
    }

    event.preventDefault()
    event.stopPropagation()
    open(items, start)
  }

  return { labels, open, click }
}
