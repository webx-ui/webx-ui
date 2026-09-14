import { HighlightStyle } from '@codemirror/language'
import { tags as t } from '@lezer/highlight'

/**
 * Syntax colours come from the semantic tokens, so both themes are covered by the
 * same style and a project that re-tints its primary colour re-tints its code too.
 * The palette is deliberately small: five roles, not a rainbow.
 */
export const highlightStyle = HighlightStyle.define([
  { tag: [t.keyword, t.modifier, t.operatorKeyword], color: 'var(--wx-color-primary-active)' },
  {
    tag: [t.string, t.special(t.string), t.attributeValue],
    color: 'var(--wx-color-success-active)',
  },
  { tag: [t.number, t.bool, t.null, t.atom, t.literal], color: 'var(--wx-color-warning-active)' },
  {
    tag: [t.propertyName, t.attributeName, t.definition(t.variableName), t.labelName],
    color: 'var(--wx-text-strong)',
  },
  { tag: [t.typeName, t.className, t.namespace, t.tagName], color: 'var(--wx-color-primary)' },
  { tag: [t.function(t.variableName), t.function(t.propertyName)], color: 'var(--wx-text-strong)' },
  {
    tag: [t.comment, t.lineComment, t.blockComment, t.meta, t.processingInstruction, t.docComment],
    color: 'var(--wx-text-muted)',
    fontStyle: 'italic',
  },
  { tag: [t.punctuation, t.operator, t.separator, t.bracket], color: 'var(--wx-text-default)' },
  { tag: t.invalid, color: 'var(--wx-color-danger)' },
  // Markdown
  { tag: t.heading, color: 'var(--wx-text-strong)', fontWeight: '600' },
  { tag: t.strong, fontWeight: '600' },
  { tag: t.emphasis, fontStyle: 'italic' },
  { tag: t.strikethrough, textDecoration: 'line-through' },
  { tag: [t.link, t.url], color: 'var(--wx-text-link)', textDecoration: 'underline' },
  { tag: t.monospace, color: 'var(--wx-color-success-active)' },
])
