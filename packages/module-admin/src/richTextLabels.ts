import { computed, type ComputedRef } from 'vue'
import type { RichTextLabels } from '@webx-ui/core'
import { useTranslate } from './i18n'

/**
 * What `WxRichText` calls its buttons, in the panel's language.
 *
 * The editor is a component of the design system and carries English defaults; every editor a
 * panel opens — a screen's `wx-rich-text` or one a module draws itself — hands these in, so a
 * toolbar is never the one English line in a translated form.
 */
export function useRichTextLabels(): ComputedRef<RichTextLabels> {
  const t = useTranslate('webx-admin')

  return computed<RichTextLabels>(() => ({
    bold: t('rich-text.bold'),
    italic: t('rich-text.italic'),
    accent: t('rich-text.accent'),
    strike: t('rich-text.strike'),
    code: t('rich-text.code'),
    h2: t('rich-text.h2'),
    h3: t('rich-text.h3'),
    h4: t('rich-text.h4'),
    bulletList: t('rich-text.bullet-list'),
    orderedList: t('rich-text.ordered-list'),
    blockquote: t('rich-text.blockquote'),
    hr: t('rich-text.hr'),
    link: t('rich-text.link'),
    table: t('rich-text.table'),
    image: t('rich-text.image'),
    youtube: t('rich-text.youtube'),
    undo: t('rich-text.undo'),
    redo: t('rich-text.redo'),
    source: t('rich-text.source'),
    addRowAfter: t('rich-text.row-below'),
    addRowBefore: t('rich-text.row-above'),
    addColumnAfter: t('rich-text.column-after'),
    addColumnBefore: t('rich-text.column-before'),
    deleteRow: t('rich-text.delete-row'),
    deleteColumn: t('rich-text.delete-column'),
    mergeOrSplit: t('rich-text.merge-cells'),
    deleteTable: t('rich-text.delete-table'),
    toolbar: t('rich-text.toolbar'),
    linkAddress: t('rich-text.link-address'),
    youtubeAddress: t('rich-text.youtube-address'),
    apply: t('rich-text.apply'),
    cancel: t('rich-text.cancel'),
    uploading: t('rich-text.uploading'),
    sourceLoss: t('rich-text.source-loss'),
    sourceDrop: t('rich-text.source-drop'),
    sourceKeep: t('rich-text.source-keep'),
    expand: t('rich-text.expand'),
    collapse: t('rich-text.collapse'),
  }))
}
