export type EntityCardSize = 'sm' | 'md' | 'lg'
export type EntityCardVariant = 'card' | 'plain'
export type EntityCardShape = 'rounded' | 'square' | 'circle'

/** One fact under the title: "Sections: News", "Updated 12.03", a status. */
export interface EntityCardMeta {
  /** Prefix shown in muted text — the field name. */
  label?: string
  /** The value itself. Leave empty to show the label alone, as an empty field reads. */
  text?: string
}

export interface EntityCardProps {
  /** Name of the entity — the line that identifies the row. */
  title?: string
  /** Turns the title into a link. */
  href?: string
  /** Second line, between the title and the meta row. */
  subtitle?: string
  /** Thumbnail, avatar, cover — anything small and square. */
  image?: string
  imageAlt?: string
  /** Shape of the thumbnail. Use `circle` for people, `rounded` for content. */
  shape?: EntityCardShape
  /** Thumbnail size. A number is pixels; a string is used as a CSS length. */
  imageSize?: number | string
  /** Facts under the title. Use the `meta` slot when a fact needs markup. */
  meta?: EntityCardMeta[]
  /**
   * How many lines the name may take before it is cut.
   *
   * One by default, because a row of entities reads as a row only while every one of them is
   * the same height. Two is for a list where the name is a sentence rather than a label — an
   * article headline cut to one line on a phone is half a thought.
   */
  titleLines?: number
  size?: EntityCardSize
  /** `card` sits on its own surface; `plain` drops the background for use inside one. */
  variant?: EntityCardVariant
  bordered?: boolean
  /** Highlights the card as the current one — a picked item in a list. */
  selected?: boolean
}

export interface EntityCardEmits {
  /** The card body was clicked — the row, not the buttons in `#actions`. */
  click: [event: MouseEvent]
}
