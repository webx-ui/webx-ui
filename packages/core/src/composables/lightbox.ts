import LightboxModal from '../internal/LightboxModal.vue'
import { openModal, type ModalOptions, type ModalPromise } from './useModal'
import type { LightboxSource, OpenLightboxOptions } from '../components/Lightbox/types'

/**
 * Opens a gallery over the page, from anywhere:
 *
 * ```ts
 * openLightbox(product.images, 2)
 * openLightbox(['/a.jpg', '/b.jpg'], { start: 1, loop: true })
 * ```
 *
 * It answers nothing — the promise settles, with `undefined`, when the lightbox is closed —
 * and carries `close()` for a gallery that should not outlive something else.
 */
export function openLightbox(
  items: LightboxSource[],
  options: number | OpenLightboxOptions = {},
  modal: ModalOptions = {},
): ModalPromise<void> {
  const props = typeof options === 'number' ? { start: options } : options

  return openModal<void>(LightboxModal, {
    ...modal,
    props: { ...modal.props, ...props, items },
  })
}
