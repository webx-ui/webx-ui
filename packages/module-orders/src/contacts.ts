/**
 * The address a phone or an e-mail of an order opens: `tel:` with the digits and a leading `+`
 * only — «+800 1234-5678» dials as `tel:+80012345678` — and `mailto:` with the address as it
 * is. Null when there is nothing to dial or no `@` to write to: the text then stays text.
 */
export function contactHref(kind: 'phone' | 'email', value: string): string | null {
  const trimmed = value.trim()

  if (kind === 'email') {
    return trimmed.includes('@') ? `mailto:${trimmed}` : null
  }

  const digits = trimmed.replace(/\D/g, '')

  return digits === '' ? null : `tel:${trimmed.startsWith('+') ? '+' : ''}${digits}`
}
