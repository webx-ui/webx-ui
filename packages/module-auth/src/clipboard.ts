/**
 * Copying, with the old way behind it.
 *
 * The clipboard API is missing outside a secure context and refused inside some frames, and the
 * things this package copies — the address agents connect to, a key shown once — are exactly
 * where "it did not work and said nothing" is worst. Resolves whether it worked.
 */
export async function copyText(value: string): Promise<boolean> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)

      return true
    }
  } catch {
    // Refused; the old way may still work.
  }

  return byHand(value)
}

function byHand(value: string): boolean {
  const field = document.createElement('textarea')

  field.value = value
  field.setAttribute('readonly', '')
  field.style.position = 'fixed'
  field.style.opacity = '0'

  document.body.append(field)
  field.select()

  try {
    return document.execCommand('copy')
  } catch {
    return false
  } finally {
    field.remove()
  }
}
