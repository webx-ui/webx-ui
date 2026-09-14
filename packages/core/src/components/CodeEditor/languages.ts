import type { Extension } from '@codemirror/state'
import { json, jsonParseLinter } from '@codemirror/lang-json'
import { javascript } from '@codemirror/lang-javascript'
import { html } from '@codemirror/lang-html'
import { css } from '@codemirror/lang-css'
import { markdown } from '@codemirror/lang-markdown'
import { yaml } from '@codemirror/lang-yaml'
import { php } from '@codemirror/lang-php'
import type { LintSource } from '@codemirror/lint'
import type { CodeEditorLanguage } from './types'

interface LanguageSupport {
  extension: () => Extension
  /** Only JSON has a parser cheap enough to run on every keystroke. */
  linter?: () => LintSource
}

/**
 * Each entry is a factory rather than an instance: language support carries
 * state fields, and CodeMirror expects a fresh one per editor.
 */
export const LANGUAGES: Record<CodeEditorLanguage, LanguageSupport> = {
  json: { extension: () => json(), linter: () => jsonParseLinter() },
  javascript: { extension: () => javascript() },
  typescript: { extension: () => javascript({ typescript: true }) },
  html: { extension: () => html() },
  css: { extension: () => css() },
  markdown: { extension: () => markdown() },
  yaml: { extension: () => yaml() },
  php: { extension: () => php() },
  plain: { extension: () => [] },
}
