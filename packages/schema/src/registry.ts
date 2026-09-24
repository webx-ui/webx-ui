import {
  WxAlert,
  WxAutocomplete,
  WxCard,
  WxCascader,
  WxCheckbox,
  WxCheckboxGroup,
  WxCodeEditor,
  WxCol,
  WxColorPicker,
  WxDatePicker,
  WxDateRangePicker,
  WxDateTimePicker,
  WxDivider,
  WxHeading,
  WxIconPicker,
  WxInput,
  WxInputNumber,
  WxRadioGroup,
  WxRate,
  WxRow,
  WxSegmented,
  WxSelect,
  WxSlider,
  WxSwitch,
  WxTab,
  WxTagsInput,
  WxText,
  WxTextarea,
  WxTimePicker,
  WxTransfer,
  WxTreeSelect,
} from '@webx-ui/core'
import ScreenRepeater from './ScreenRepeater.vue'
import ScreenTabs from './ScreenTabs.vue'
import type { NodeKind, ScreenNode, TypeEntry, TypeRegistry } from './types'

/** Every field name under a node, however deep — what a tab has to open for. */
function fieldNames(node: ScreenNode): string[] {
  return (node.children ?? []).flatMap((child) =>
    child.name === undefined ? fieldNames(child) : [child.name],
  )
}

/**
 * The types every panel has: the core's layout, form and display components under
 * their full names. A module or a project adds its own the same way — `wx-media` comes
 * from `module-media`, `map` from whoever has a map.
 */
export const coreTypes: TypeRegistry = {
  'wx-tabs': {
    component: ScreenTabs,
    kind: 'layout',
    // Which fields each tab holds, so a refused save can open the tab it was refused on.
    bind: (node) => ({
      fields: Object.fromEntries((node.children ?? []).map((tab) => [tab.id, fieldNames(tab)])),
    }),
  },
  'wx-tab': {
    component: WxTab,
    kind: 'layout',
    labelProp: 'label',
    // A tab is selected by value, and the node's id is the one stable thing it has.
    bind: (node) => ({ value: node.id }),
  },
  'wx-card': { component: WxCard, kind: 'layout', labelProp: 'title' },
  'wx-row': { component: WxRow, kind: 'layout' },
  'wx-col': {
    component: WxCol,
    kind: 'layout',
    // Marked so the renderer can stack what a column holds (see `ScreenRenderer.vue`),
    // without touching a `WxCol` that some field happens to use inside itself.
    bind: (node) => ({ class: ['wx-screen__col', node.props?.class] }),
  },
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
  'wx-checkbox-group': { component: WxCheckboxGroup, kind: 'field' },
  'wx-segmented': { component: WxSegmented, kind: 'field' },
  'wx-slider': { component: WxSlider, kind: 'field' },
  'wx-rate': { component: WxRate, kind: 'field' },
  'wx-time-picker': { component: WxTimePicker, kind: 'field' },
  'wx-date-time-picker': {
    component: WxDateTimePicker,
    kind: 'field',
    // A moment, not a wall clock. Without the offset the server reads the hour in its own
    // timezone and the browser in the reader's, and one value shows two different times. Bound
    // over `props` on purpose: the server parses exactly this shape.
    bind: () => ({ valueFormat: "yyyy-MM-dd'T'HH:mm:ssXXX" }),
  },
  'wx-date-range-picker': { component: WxDateRangePicker, kind: 'field' },
  'wx-tags-input': { component: WxTagsInput, kind: 'field' },
  'wx-autocomplete': { component: WxAutocomplete, kind: 'field' },
  'wx-icon-picker': { component: WxIconPicker, kind: 'field' },
  'wx-cascader': { component: WxCascader, kind: 'field' },
  'wx-tree-select': { component: WxTreeSelect, kind: 'field' },
  // Wide: two lists with the buttons between them do not fit in half a form.
  'wx-transfer': { component: WxTransfer, kind: 'field', wide: true },
  // Wide for the reason an editor is: code is read in long lines.
  'wx-code-editor': { component: WxCodeEditor, kind: 'field', wide: true },
  'wx-repeater': { component: ScreenRepeater, kind: 'field', nested: true, wide: true },

  'wx-heading': { component: WxHeading, kind: 'display' },
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
