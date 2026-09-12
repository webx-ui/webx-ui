import { inject, reactive, type App, type InjectionKey } from 'vue'

/**
 * One language the panel can be drawn in. Mirrors what `GET /api/cms/locales` answers with.
 */
export interface LocaleDescriptor {
  code: string
  name: string
  /** The language's name in itself — what belongs in a language picker. */
  nativeName: string
  direction: 'ltr' | 'rtl'
  default: boolean
}

/** A group of lines, nested as deeply as the `lang` file that produced it. */
export type Messages = { [key: string]: string | Messages }

/** Namespace → group → lines, which is the shape the server assembles. */
export type Dictionary = Record<string, Record<string, Messages>>

export interface I18nState {
  /** The language the interface is being drawn in. */
  locale: string
  /** Where a missing line is looked for next. */
  fallback: string
  /** Languages the interface can be switched to. */
  panelLocales: LocaleDescriptor[]
  /** Languages the site publishes content in — every editing screen is built around this. */
  contentLocales: LocaleDescriptor[]
}

export type Translate = (key: string, params?: Record<string, string | number>) => string

export interface I18n {
  readonly state: I18nState
  /**
   * Strings a package ships in its own code, used until the server's dictionary arrives and
   * for whatever the dictionary does not carry.
   *
   * This is what lets a package work with no server at all — a story, a test, a panel
   * assembled by hand — and what stops a missing translation from showing a key to somebody.
   */
  defaults(namespace: string, messages: Record<string, Messages>): void
  /** Replace the dictionary with what the server sent. */
  load(dictionary: Dictionary, locale: string, fallback?: string): void
  /** A `t()` bound to one namespace, so a component writes `t('shell.loading')`. */
  scope(namespace: string): Translate
  /** Absolute form: `t('webx-admin::shell.loading')`. */
  t: Translate
}

export const i18nKey: InjectionKey<I18n> = Symbol('webx-i18n')

export function useI18n(): I18n {
  const i18n = inject(i18nKey, null)

  if (i18n === null) {
    throw new Error('useI18n() was called outside a panel created by createAdmin().')
  }

  return i18n
}

/**
 * A component that may be used outside a panel — a login card placed by hand — needs a
 * translator either way. This gives it the package's own English when there is no panel.
 */
export function useTranslate(namespace: string): Translate {
  const i18n = inject(i18nKey, null)

  return i18n === null ? createI18n().scope(namespace) : i18n.scope(namespace)
}

export function provideI18n(app: App, i18n: I18n): void {
  app.provide(i18nKey, i18n)
}

export function createI18n(options: { locale?: string; fallback?: string } = {}): I18n {
  const fallback = options.fallback ?? 'en'

  const state = reactive<I18nState>({
    locale: options.locale ?? fallback,
    fallback,
    panelLocales: [],
    contentLocales: [],
  })

  // Kept apart from the dictionary rather than merged into it: the server's answer is
  // replaced wholesale on every language change, and built-in strings have to survive that.
  const builtIn: Dictionary = {}
  const dictionary = reactive<{ value: Dictionary }>({ value: {} })

  function lookup(source: Dictionary, namespace: string, path: string[]): string | null {
    let node: string | Messages | undefined = source[namespace]?.[path[0] ?? '']

    for (const segment of path.slice(1)) {
      if (typeof node !== 'object' || node === null) {
        return null
      }

      node = node[segment]
    }

    return typeof node === 'string' ? node : null
  }

  function translate(
    namespace: string,
    key: string,
    params?: Record<string, string | number>,
  ): string {
    // An absolute key wins, so one namespace can borrow a line from another without a second
    // translator.
    const [explicitNamespace, rest] = key.includes('::')
      ? (key.split('::', 2) as [string, string])
      : [namespace, key]

    const path = rest.split('.')

    const line =
      lookup(dictionary.value, explicitNamespace, path) ??
      lookup(builtIn, explicitNamespace, path) ??
      // Not an empty string: a key on screen is ugly, but it says which key, and a blank
      // label says nothing to anybody trying to fix it.
      key

    return params === undefined ? line : fill(line, params)
  }

  return {
    state,
    defaults(namespace, messages) {
      builtIn[namespace] = { ...builtIn[namespace], ...messages }
    },
    load(next, locale, nextFallback) {
      // Guarded rather than trusted: an answer that is not the shape expected should leave
      // the panel in English, not without any words at all.
      dictionary.value = next ?? {}
      state.locale = locale ?? state.locale

      if (nextFallback !== undefined) {
        state.fallback = nextFallback
      }
    },
    scope(namespace) {
      return (key, params) => translate(namespace, key, params)
    },
    t: (key, params) => translate('', key, params),
  }
}

/**
 * `:name` placeholders, the way Laravel writes them — the strings come from its `lang` files,
 * so they should read the same on both sides.
 */
function fill(line: string, params: Record<string, string | number>): string {
  let filled = line

  for (const [name, value] of Object.entries(params)) {
    filled = filled.replaceAll(`:${name}`, String(value))
  }

  return filled
}
