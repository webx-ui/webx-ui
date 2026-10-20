import { computed, inject, provide, type ComputedRef, type InjectionKey } from 'vue'

/**
 * What the site this panel runs on prints. Outside the environments that print everything (§5.5)
 * only the codes marked "on dev too" go out, and a code switched on without that mark is silent
 * here — the reason the person looking at it would otherwise have to guess.
 */
export interface SnippetsCopy {
  /** The environment the site runs as, `staging`, `local`. */
  environment: ComputedRef<string>
  /** The codes of the form being edited would not be printed here even when switched on. */
  silent: ComputedRef<boolean>
}

const KEY: InjectionKey<SnippetsCopy> = Symbol('webx-snippets-copy')

export function provideSnippetsCopy(copy: SnippetsCopy): void {
  provide(KEY, copy)
}

/** Outside the pane — a test, a screen drawn on its own — every code is printed. */
export function useSnippetsCopy(): SnippetsCopy {
  return (
    inject(KEY, null) ?? {
      environment: computed(() => 'production'),
      silent: computed(() => false),
    }
  )
}

/** A row of the list or the values of the form: switched on and still not printed on this site. */
export function silentHere(
  code: { enabled?: unknown; on_stage?: unknown },
  everythingPrinted: boolean,
): boolean {
  return !everythingPrinted && code.enabled === true && code.on_stage !== true
}
