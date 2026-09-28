import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { RegionDetail } from './types'

/**
 * What the region editor knows and the nodes of its screen do not.
 *
 * The editor is a described screen (`regions.form`), so a site can add a tab to it with a patch;
 * the history is a node type of that screen, and it needs the region being edited — which a
 * description cannot hand it. The page hosting the screen provides it instead.
 */
export interface RegionEditorContext {
  /** The region as the server last answered it, or `null` while the first request is out. */
  region: Ref<RegionDetail | null>
  /** Whether this administrator may write. */
  canManage: boolean
  /** Ask the server for the region again — after a restore. */
  reload(): Promise<void>
}

export const regionEditorKey: InjectionKey<RegionEditorContext> = Symbol('wx-region-editor')

export function provideRegionEditor(editor: RegionEditorContext): void {
  provide(regionEditorKey, editor)
}

/** The editor above this node, or `null` outside one: a node drawn in a demo draws nothing. */
export function useRegionEditor(): RegionEditorContext | null {
  return inject(regionEditorKey, null)
}

/**
 * The preview address for one page of the site.
 *
 * The server signs the region and nothing else, so the page is appended by the panel: a person
 * looking at the header on the contacts page is still looking at the same region.
 */
export function previewAt(url: string, path: string): string {
  return `${url}${url.includes('?') ? '&' : '?'}at=${encodeURIComponent(path)}`
}

/** A site path out of whatever the link picker holds: an address typed, or a page's URL. */
export function sitePath(address: string | null): string | null {
  if (address === null || address.trim() === '') return null

  const trimmed = address.trim()

  try {
    // Absolute or relative alike: the base only makes `/about` parse, and is thrown away.
    const url = new URL(trimmed, 'http://site.invalid')

    return url.pathname || '/'
  } catch {
    return trimmed.startsWith('/') ? trimmed : `/${trimmed}`
  }
}
