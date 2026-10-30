import { HttpError } from './http'
import { useTranslate, type Translate } from './i18n'

/**
 * What to say out loud when a request came back a failure.
 *
 * The panel used to print whatever the server put in `message`, which is fine right up until
 * the server has not written it. `No query results for model [WebxUi\Pages\Models\Page] 8` is
 * Laravel talking to a developer — a namespace, in English, in a panel translated into ten
 * languages — and it reached an editor who had only clicked a row in the bin (§13.3).
 *
 * So the rule is which status the panel trusts to speak for itself, not which words look
 * technical. A 422 is ours: every refusal that reaches one is written by a module to be read,
 * through `trans()`, and the validator's lines are translated by Laravel. Everything else —
 * 404, 403, 500, a server that never answered — carries text nobody wrote for this reader, and
 * the panel says it in its own words instead.
 */
export function errorText(error: unknown, t: Translate, fallback?: string): string {
  const status = error instanceof HttpError ? error.status : statusOf(error)

  // The one the panel wrote itself. `fallback` under it for the rare 422 with nothing in it —
  // a refusal with only field errors, where the form is already showing them.
  if (status === 422) {
    return validationMessage(error) ?? fallback ?? t('errors.unknown')
  }

  if (status === 401) return t('errors.signed-out')
  if (status === 403) return t('errors.forbidden')
  if (status === 404) return t('errors.gone')
  if (status === 409) return t('errors.conflict')

  if (status === 429) {
    const seconds = error instanceof HttpError ? error.retryAfter : null

    return seconds === null ? t('errors.throttled') : t('errors.throttled-in', { seconds })
  }

  if (status !== null && status >= 500) return t('errors.server')

  // No status at all: the request never reached a server, or never came back from one.
  if (status === null) return t('errors.offline')

  return fallback ?? t('errors.unknown')
}

/**
 * The same thing for a component, bound to the panel's own dictionary.
 *
 * A module calls this rather than its own `t`, because these lines belong to the panel: every
 * section fails in the same handful of ways, and translating "you are not allowed to do that"
 * once per module is how nine of them end up saying it differently.
 */
export function useErrorText(): (error: unknown, fallback?: string) => string {
  const t = useTranslate('webx-admin')

  return (error, fallback) => errorText(error, t, fallback)
}

function statusOf(error: unknown): number | null {
  const status = (error as { status?: unknown } | null)?.status

  return typeof status === 'number' ? status : null
}

/**
 * A failed form says its first refusal, and only that. Laravel sums the rest up after it —
 * «Enter a phone or an e-mail. (and 1 more error)» — in a line of its own that no module
 * translates, so a toast in Russian ended in English. The fields show every refusal anyway.
 */
function validationMessage(error: unknown): string | null {
  const message = bodyMessage(error)
  const errors = (error as { errors?: unknown } | null)?.errors
  const first =
    errors !== null && typeof errors === 'object'
      ? Object.values(errors as Record<string, unknown>).find(
          (messages): messages is string[] => Array.isArray(messages) && typeof messages[0] === 'string',
        )?.[0]
      : undefined

  if (message !== null && first !== undefined && message.startsWith(first)) {
    return /^\s*\(.*\)\s*$/.test(message.slice(first.length)) ? first : message
  }

  return message
}

function bodyMessage(error: unknown): string | null {
  const body = (error as { body?: { message?: unknown } } | null)?.body
  const message = body?.message

  return typeof message === 'string' && message !== '' ? message : null
}
