/**
 * The first refusal of a field, whether the server named the field (`name`) or one language of it
 * (`name.en`). Matched on the name and a dot, not on the first dot of the key: names with a dot in
 * them exist (`stock.status`), and `name.en` must not be read as the field `name.en`.
 */
export function firstError(errors: Record<string, string[]>, field: string): string | undefined {
  const own = errors[field]?.[0]

  if (own !== undefined) return own

  const key = Object.keys(errors).find((name) => name.startsWith(`${field}.`))

  return key === undefined ? undefined : errors[key]?.[0]
}
