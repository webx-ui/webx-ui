import { inject, provide, reactive, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { createMediaApi } from '@webx-ui/module-media'
import type { ConfiguratorSchema } from './engine/types'
import type { Problem } from './engine/validator'

/**
 * What every part of the constructor shares: the document being edited, the language a new text
 * is written in, whether it may be written at all, and its problems — the JS check as it is typed,
 * the server's `image.unknown` beside it (only the server knows the media library).
 */
export interface Builder {
  schema: Ref<ConfiguratorSchema>
  /** The site's default language: what a text needs words in, and what the check asks for. */
  locale: ComputedRef<string>
  disabled: ComputedRef<boolean>
  problems: ComputedRef<Problem[]>
  /** The words of a problem, in the panel's language. */
  message(problem: Problem): string
  /** The address of a picture of the media library by its key; `null` until it is known. */
  picture(path: string | null | undefined): string | null
  /** Whether a step or an option (by its row id) is unfolded. */
  isOpen(id: number): boolean
  toggle(id: number): void
  /** A place to unfold and bring into view — set by whoever points at a problem from elsewhere. */
  focus: Ref<string | null>
}

/** The words of the problems at exactly a place — what a field shows under itself. */
export function errorsAt(builder: Builder, path: string): string[] {
  return builder.problems.value.filter((one) => one.path === path).map(builder.message)
}

/**
 * The problems of a condition, by the step a row names: `steps.3.when.glass` and
 * `steps.3.when.glass.0` both belong to the row of `glass`; the condition's own problem to `''`.
 */
export function conditionErrors(builder: Builder, path: string): Record<string, string[]> {
  const found: Record<string, string[]> = {}

  for (const problem of builder.problems.value) {
    if (problem.path !== path && !problem.path.startsWith(`${path}.`)) continue

    const step = problem.path.slice(path.length + 1).split('.')[0] ?? ''
    found[step] = [...(found[step] ?? []), builder.message(problem)]
  }

  return found
}

const key: InjectionKey<Builder> = Symbol('wx-configurator-builder')

export function provideBuilder(builder: Builder): void {
  provide(key, builder)
}

export function useBuilder(): Builder {
  const builder = inject(key, null)

  if (!builder) throw new Error('A part of the configurator constructor is used outside of one.')

  return builder
}

/** The words of a problem: `errors.<code>` with its params, the server's own words otherwise. */
export function useProblemWords(): (problem: Problem & { message?: string }) => string {
  const t = useTranslate('webx-configurator')

  return (problem) => {
    const key = `errors.${problem.code}`
    const words = t(key, problem.params ?? {})

    return words === key && problem.message ? problem.message : words
  }
}

/**
 * Addresses of pictures by their keys, asked once each and kept for the life of the editor —
 * the document holds keys (decision 5), and a picture is drawn by its address.
 */
export function usePictures(): (path: string | null | undefined) => string | null {
  const api = createMediaApi(useAdmin())
  const known = reactive(new Map<string, string | null>())

  return (path) => {
    if (!path) return null

    if (!known.has(path)) {
      known.set(path, null)
      void api.fileByPath(path).then((file) => known.set(path, file?.url ?? null))
    }

    return known.get(path) ?? null
  }
}
