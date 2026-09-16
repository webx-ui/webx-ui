import { inject, provide, type InjectionKey, type Ref } from 'vue'

/**
 * What the page hosting a constructor knows and the constructor does not: where the preview
 * of the entity is. The address is `Preview::url()` on the server — signed, short-lived, one
 * entity — and it is the consumer module's screen that asks for it, because only that
 * screen knows which entity it is editing.
 *
 * The constructor shows the page in an iframe when this is provided, and works as a tree
 * with a form when it is not.
 */
export interface BlocksPreview {
  /** The preview address, or null while there is none — an entity not yet saved. */
  url: Ref<string | null>
  /** Bump to reload the frame: after an autosave, when the tree changed shape. */
  reload?: Ref<number>
}

export const blocksPreviewKey: InjectionKey<BlocksPreview> = Symbol('wx-blocks-preview')

/** Called by the hosting screen, above the `wx-blocks` field. */
export function provideBlocksPreview(preview: BlocksPreview): void {
  provide(blocksPreviewKey, preview)
}

export function useBlocksPreview(): BlocksPreview | null {
  return inject(blocksPreviewKey, null)
}

/**
 * Set by the constructor for everything it draws, so that a `wx-blocks` node inside a
 * block's own form knows it is nested — and draws a note instead of a second constructor.
 */
export const blocksRootKey: InjectionKey<boolean> = Symbol('wx-blocks-root')
