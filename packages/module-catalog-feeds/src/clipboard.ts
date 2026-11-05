import { toast } from '@webx-ui/core'

/**
 * An address onto the clipboard, or on screen when the browser refuses: a dev address over http has
 * no clipboard at all, and the person still needs the address.
 */
export async function copyAddress(address: string, done: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(address)
    toast.success(done)
  } catch {
    toast.info(address)
  }
}
