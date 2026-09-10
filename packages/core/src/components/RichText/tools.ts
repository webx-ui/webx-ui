import type { RichTextTool } from './types'

/**
 * Kept out of the component because `withDefaults` cannot reference a binding
 * declared inside `<script setup>` — it is hoisted out of `setup()`.
 */
export const DEFAULT_TOOLS: RichTextTool[] = [
  'bold',
  'italic',
  'strike',
  'code',
  'divider',
  'h2',
  'h3',
  'h4',
  'divider',
  'bulletList',
  'orderedList',
  'blockquote',
  'hr',
  'divider',
  'link',
  'table',
  'image',
  'youtube',
  'divider',
  'undo',
  'redo',
]

export const DEFAULT_ACCEPT = [
  'image/png',
  'image/jpeg',
  'image/gif',
  'image/webp',
  'image/svg+xml',
]

export interface ToolMeta {
  label: string
  icon?: string
  text?: string
}

export const TOOL_META: Record<Exclude<RichTextTool, 'divider'>, ToolMeta> = {
  bold: { label: 'Bold', icon: 'bold' },
  italic: { label: 'Italic', icon: 'italic' },
  strike: { label: 'Strikethrough', icon: 'strike' },
  code: { label: 'Inline code', icon: 'code' },
  h2: { label: 'Heading 2', text: 'H2' },
  h3: { label: 'Heading 3', text: 'H3' },
  h4: { label: 'Heading 4', text: 'H4' },
  bulletList: { label: 'Bulleted list', icon: 'bulletList' },
  orderedList: { label: 'Numbered list', icon: 'orderedList' },
  blockquote: { label: 'Quote', icon: 'blockquote' },
  hr: { label: 'Divider', icon: 'hr' },
  link: { label: 'Link', icon: 'link' },
  table: { label: 'Table', icon: 'table' },
  image: { label: 'Image', icon: 'image' },
  youtube: { label: 'YouTube video', icon: 'youtube' },
  undo: { label: 'Undo', icon: 'undo' },
  redo: { label: 'Redo', icon: 'redo' },
}

/** Shown as a second row while the caret sits inside a table. */
export const TABLE_TOOLS = [
  { key: 'addRowAfter', label: 'Row below', icon: 'rowAfter' },
  { key: 'addRowBefore', label: 'Row above', icon: 'rowBefore' },
  { key: 'addColumnAfter', label: 'Column after', icon: 'columnAfter' },
  { key: 'addColumnBefore', label: 'Column before', icon: 'columnBefore' },
  { key: 'deleteRow', label: 'Delete row', icon: 'deleteRow' },
  { key: 'deleteColumn', label: 'Delete column', icon: 'deleteColumn' },
  { key: 'mergeOrSplit', label: 'Merge or split cells', icon: 'mergeCells' },
  { key: 'deleteTable', label: 'Delete table', icon: 'deleteTable' },
] as const

export type TableToolKey = (typeof TABLE_TOOLS)[number]['key']
