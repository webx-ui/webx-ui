export type ProseSize = 'sm' | 'md' | 'lg'

export interface ProseProps {
  /**
   * HTML to render — the output of the rich-text editor, a CMS field, a Markdown
   * renderer. It is injected as-is, so pass only markup you have sanitised on the
   * server; use the default slot for anything you assemble in the template.
   */
  html?: string
  /** Text scale of the whole block. */
  size?: ProseSize
  /** Element to render. */
  as?: string
}
