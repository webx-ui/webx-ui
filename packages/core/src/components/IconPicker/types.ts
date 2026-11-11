import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export interface IconPickerProps {
  /**
   * The names to pick from. Without it — the panel's own set, every name `WxIcon` draws.
   * A screen gives it in JSON (or a patch sets it), and a name outside it is marked unknown.
   */
  icons?: string[]
  /**
   * Address of an SVG sprite whose `<symbol id="<name>">` draw the names of `icons` —
   * the icons of the site's theme rather than the panel's. Without it the names are drawn
   * by `WxIcon`, so a list without a sprite is a part of the panel's set.
   */
  sprite?: string
  /** Show a button that empties the field. */
  clearable?: boolean
  placeholder?: string
  /** What the field says when the name it holds is not in the set. */
  unknownText?: string
  /** What the panel says when nothing matches what was typed. */
  emptyText?: string
  /** Label of the button that empties the field, for screen readers. */
  clearLabel?: string
  /** Render the panel in a portal so it escapes `overflow: hidden`. */
  teleport?: boolean
  disabled?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  name?: string
  /** Accessible label used when the field has no visible `<label>`. */
  ariaLabel?: string
}

export interface IconPickerEmits {
  change: [value: string | null]
}
