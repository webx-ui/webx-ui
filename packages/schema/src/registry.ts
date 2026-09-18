import {
  WxAlert,
  WxCard,
  WxCheckbox,
  WxCol,
  WxColorPicker,
  WxDatePicker,
  WxDivider,
  WxInput,
  WxInputNumber,
  WxRadioGroup,
  WxRow,
  WxSelect,
  WxSwitch,
  WxTab,
  WxTabs,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import ScreenRepeater from './ScreenRepeater.vue'
import type { NodeKind, TypeEntry, TypeRegistry } from './types'

/**
 * The types every panel has: the core's layout, form and display components under
 * their full names. A module or a project adds its own the same way — `wx-media` comes
 * from `module-media`, `map` from whoever has a map.
 */
export const coreTypes: TypeRegistry = {
  'wx-tabs': { component: WxTabs, kind: 'layout' },
  'wx-tab': {
    component: WxTab,
    kind: 'layout',
    labelProp: 'label',
    // A tab is selected by value, and the node's id is the one stable thing it has.
    bind: (node) => ({ value: node.id }),
  },
  'wx-card': { component: WxCard, kind: 'layout', labelProp: 'title' },
  'wx-row': { component: WxRow, kind: 'layout' },
  'wx-col': { component: WxCol, kind: 'layout' },
  'wx-divider': { component: WxDivider, kind: 'layout', labelProp: 'label' },

  'wx-input': { component: WxInput, kind: 'field' },
  'wx-textarea': { component: WxTextarea, kind: 'field' },
  'wx-input-number': { component: WxInputNumber, kind: 'field' },
  'wx-select': { component: WxSelect, kind: 'field' },
  'wx-switch': { component: WxSwitch, kind: 'field' },
  'wx-checkbox': { component: WxCheckbox, kind: 'field' },
  'wx-radio-group': { component: WxRadioGroup, kind: 'field' },
  'wx-date-picker': { component: WxDatePicker, kind: 'field' },
  'wx-color-picker': { component: WxColorPicker, kind: 'field' },
  'wx-repeater': { component: ScreenRepeater, kind: 'field', nested: true, wide: true },

  'wx-text': { component: WxText, kind: 'display' },
  'wx-alert': { component: WxAlert, kind: 'display', labelProp: 'title' },
}

/** Identity with a type: keeps a project's registry object checked without an import of the type. */
export function defineTypes<T extends TypeRegistry>(types: T): T {
  return types
}

export interface TypeDescription {
  type: string
  kind: NodeKind
  component: string
  /** Where the node's `label` ends up, in words. */
  label: string
}

function componentName(entry: TypeEntry): string {
  const component = entry.component as { name?: string; __name?: string }
  return component.name ?? component.__name ?? 'anonymous'
}

function labelDestination(entry: TypeEntry): string {
  if (entry.kind === 'field') return 'form item'
  if (entry.labelProp) return `prop \`${entry.labelProp}\``
  return entry.kind === 'display' ? 'default slot' : '—'
}

/** One row per type, in registry order — what the documentation table is made of. */
export function describeTypes(types: TypeRegistry): TypeDescription[] {
  return Object.entries(types).map(([type, entry]) => ({
    type,
    kind: entry.kind,
    component: componentName(entry),
    label: labelDestination(entry),
  }))
}

/**
 * The registry as a Markdown table, padded the way Prettier pads one, so the generated
 * block in the guide survives `prettier --check` and a test can compare it verbatim.
 */
export function typesTable(types: TypeRegistry): string {
  const rows = describeTypes(types).map((row) => [
    `\`${row.type}\``,
    row.kind,
    `\`${row.component}\``,
    row.label,
  ])
  const header = ['Type', 'Kind', 'Component', '`label` goes to']
  const widths = header.map((cell, column) =>
    Math.max(cell.length, ...rows.map((row) => row[column]!.length)),
  )
  const line = (cells: string[]) =>
    `| ${cells.map((cell, column) => cell.padEnd(widths[column]!)).join(' | ')} |`
  return [line(header), line(widths.map((width) => '-'.repeat(width))), ...rows.map(line)].join(
    '\n',
  )
}
