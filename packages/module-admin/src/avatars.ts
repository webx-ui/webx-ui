import { ref, watch, type Ref } from 'vue'
import type { AdminContext } from './admin'

/**
 * The photographs of the people a feed names — the author of a note, of a line of the journal.
 *
 * The server sends the key a photograph is stored under, not an address: where a key lives is the
 * library's answer, asked here for the whole feed in one batch (and remembered for the visit by
 * `assetUrls`). Until it answers, and for anybody without a photograph or in a panel without a
 * library, `src` is undefined and the avatar draws initials.
 */
export function useAvatars(
  admin: AdminContext,
  keys: () => (string | null | undefined)[],
): {
  urls: Ref<Record<string, string>>
  src: (key: string | null | undefined) => string | undefined
} {
  const urls = ref<Record<string, string>>({})

  watch(
    keys,
    async (wanted) => {
      const missing = [
        ...new Set(wanted.filter((key): key is string => typeof key === 'string' && key !== '')),
      ].filter((key) => urls.value[key] === undefined)

      if (missing.length === 0 || !admin.assetUrls) return

      try {
        const found = await admin.assetUrls(missing)
        const known = Object.entries(found).filter(
          (entry): entry is [string, string] => typeof entry[1] === 'string',
        )

        if (known.length > 0) urls.value = { ...urls.value, ...Object.fromEntries(known) }
      } catch {
        // A photograph that does not arrive is initials — never a feed that fails to draw.
      }
    },
    { immediate: true },
  )

  return { urls, src: (key) => (key ? urls.value[key] : undefined) }
}
