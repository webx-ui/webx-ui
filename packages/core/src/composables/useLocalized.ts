import { computed, inject, provide, ref, type ComputedRef, type InjectionKey, type Ref } from 'vue'

/**
 * One language the *content* is written in — not the language the panel is drawn in.
 *
 * A panel in English routinely edits a site published in Ukrainian and Russian, so the two lists
 * are unrelated: this one comes from the site's own settings.
 */
export interface LocaleOption {
  code: string
  /** What the selector shows. The code in capitals when nothing better is given. */
  label?: string
}

/** A field's value in every language it is written in: `{ uk: 'Двигуни', ru: 'Двигатели' }`. */
export type LocalizedValue = Record<string, string>

export interface LocalesContext {
  list: Ref<LocaleOption[]>
  /**
   * The language being edited, shared by every field on the screen.
   *
   * One selector per field, all moving together: switching a page to Russian and then finding
   * the field below still in Ukrainian is how half-translated records get saved.
   */
  active: Ref<string>
}

export const localesKey: InjectionKey<LocalesContext> = Symbol('wx-locales')

/*
 * The fallback, for a field used outside a panel — a story, a test, a form assembled by hand.
 * Module-level so those fields still agree with each other, and replaced by `provideLocales`
 * wherever there is a real application to ask.
 */
const standalone: LocalesContext = { list: ref([]), active: ref('') }

/**
 * Tells every localized field below which languages the site publishes in.
 *
 * ```ts
 * provideLocales(computed(() => admin.i18n.state.contentLocales))
 * ```
 */
export function provideLocales(list: Ref<LocaleOption[]> | ComputedRef<LocaleOption[]>): void {
  provide(localesKey, { list, active: ref(list.value[0]?.code ?? '') })
}

export function useLocales(): LocalesContext {
  return inject(localesKey, standalone)
}

/** The label a selector shows for a locale. */
export function localeLabel(locale: LocaleOption): string {
  return locale.label ?? locale.code.toUpperCase()
}

/**
 * Reads a localized value for display: the asked-for language, then anything that is filled in.
 *
 * A record half-translated is the normal state of a site being worked on, and a blank cell in a
 * table says less than the language that does have the words.
 */
export function localizedValue(
  value: LocalizedValue | string | null | undefined,
  locale?: string,
  fallback = '',
): string {
  if (value === null || value === undefined) return fallback
  if (typeof value === 'string') return value

  const asked = locale === undefined ? undefined : value[locale]
  if (asked) return asked

  for (const line of Object.values(value)) {
    if (line) return line
  }

  return fallback
}

export interface LocalizedFieldProps {
  /**
   * Edits the value in every language the site publishes in, with a selector on the field.
   *
   * The model then carries `{ uk: '…', ru: '…' }` rather than a string.
   */
  localized?: boolean
}

/**
 * What a text control needs to render itself once per language.
 *
 * The non-localized case is the same code path with one nameless slot, so a control has one
 * template rather than two that drift apart.
 */
export function useLocalized(props: LocalizedFieldProps, model: Ref<unknown>) {
  const locales = useLocales()

  const on = computed(() => props.localized === true && locales.list.value.length > 0)

  const active = computed({
    get: () => {
      const chosen = locales.active.value
      const known = locales.list.value.some((locale) => locale.code === chosen)

      return known ? chosen : (locales.list.value[0]?.code ?? '')
    },
    set: (code: string) => {
      locales.active.value = code
    },
  })

  /** One entry per rendered control: the locales, or a single `undefined` when off. */
  const slots = computed<(string | undefined)[]>(() =>
    on.value ? locales.list.value.map((locale) => locale.code) : [undefined],
  )

  function read(code: string | undefined): string {
    const value = model.value

    if (code === undefined) {
      return value === null || value === undefined ? '' : String(value)
    }

    /*
     * A plain string under `localized` is a column that used to hold one language — switching
     * the field on should show those words, not hide them. They belong to the first locale,
     * and the first write turns the value into a record.
     */
    if (typeof value === 'string') {
      return code === locales.list.value[0]?.code ? value : ''
    }

    if (typeof value !== 'object' || value === null) return ''

    return String((value as LocalizedValue)[code] ?? '')
  }

  function write(code: string | undefined, next: string): void {
    if (code === undefined) {
      model.value = next

      return
    }

    const current = model.value
    const base: LocalizedValue =
      typeof current === 'string'
        ? { [locales.list.value[0]?.code ?? code]: current }
        : typeof current === 'object' && current !== null
          ? { ...(current as LocalizedValue) }
          : {}

    model.value = { ...base, [code]: next }
  }

  /** `title` becomes `title[uk]`, which is the shape a classic form post is read back in. */
  function nameFor(name: unknown, code: string | undefined): string | undefined {
    if (typeof name !== 'string' || name === '') return undefined

    return code === undefined ? name : `${name}[${code}]`
  }

  return { on, active, slots, list: locales.list, read, write, nameFor }
}
