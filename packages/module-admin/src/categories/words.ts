import { useTranslate, type Translate } from '../i18n'
import type { CategoryWord } from './types'

/**
 * The shared screens' words, the module's where it has them and the panel's where it does not.
 *
 * The panel's say "category"; a module that calls them something else hands its own keys in,
 * because a blog that says "rubric" on its list and "category" in the dialog over it has two
 * words for one thing (CLAUDE.md §4 — the words of a shared component are the caller's).
 */
export function useCategoryWords(
  words: Partial<Record<CategoryWord, string>> = {},
): (word: CategoryWord, params?: Record<string, string | number>) => string {
  const t: Translate = useTranslate('webx-admin')

  return (word, params) => t(words[word] ?? `webx-admin::categories.${word}`, params)
}
