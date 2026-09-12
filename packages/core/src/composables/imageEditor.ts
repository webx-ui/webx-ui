import ImageEditorDialog from '../internal/ImageEditorDialog.vue'
import { createModal } from './useModal'
import type { ImageEditorModalProps, ImageEditorResult } from '../components/ImageEditor/types'

/**
 * Opens the picture in a panel and answers with what came out of it.
 *
 * ```ts
 * const edited = await openImageEditor({ src: file.url, aspect: 16 / 9 })
 * if (!edited) return
 * await upload(edited.file)
 * ```
 *
 * Nothing is uploaded and nothing is replaced: the answer is a blob, its measurements and
 * the crop it was cut from, and the screen that asked decides where all that goes. Closed
 * without saving — the ✕, escape, Cancel — it answers `undefined`, which is not an error.
 *
 * It is [`createModal`](/guide/modals) over `WxImageEditor` in a dialog, and worth reading
 * as the example: one line names the component, the type of its answer and the event that
 * carries it, and every call site is a single `await`.
 */
export const openImageEditor = createModal<ImageEditorResult, ImageEditorModalProps>(
  ImageEditorDialog,
  { resolveOn: 'save' },
)
