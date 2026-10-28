/**
 * Copying the return address of a network, with the old way behind it: the clipboard API is
 * missing outside a secure context — a panel opened on a stand over plain http.
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
