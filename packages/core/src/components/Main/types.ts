export interface MainProps {
  /** Inner padding. */
  padding?: 'none' | 'sm' | 'md' | 'lg'
  /** Scrolls the column on its own instead of scrolling the page. */
  scroll?: boolean
  /** Caps the content width and centres it — for forms and reading columns. */
  maxWidth?: number | string
}
