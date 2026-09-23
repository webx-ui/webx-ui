import { computed, inject, ref, watchEffect, type Ref } from 'vue'
import { adminKey } from '@webx-ui/module-admin'
import { siteShell } from './frame'

/**
 * One request for every thumbnail on the screen: the stage page is the same for all of them,
 * and a list of forty cards asking for it forty times is forty renders of the site's layout.
 * Kept for the life of the panel — a site's stylesheets change with a deploy, and a deploy is
 * a reload of the panel anyway.
 */
const shells = new Map<string, Promise<string | null>>()

export function loadShell(url: string): Promise<string | null> {
  let shell = shells.get(url)

  if (shell === undefined) {
    shell = fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
      .then((response) => (response.ok ? response.text() : null))
      .then((html) => (html === null ? null : siteShell(html, url)))
      // Not allowed to see the stage, or no stage at all: the thumbnail draws the block bare,
      // which is what it did before there was one.
      .catch(() => null)
    shells.set(url, shell)
  }

  return shell
}

/**
 * The site's shell for thumbnails, once it has arrived; null until then and wherever there is
 * none. Outside a panel (a test, a screen of a project's own) there is no manifest to name the
 * stage, and the thumbnail stays bare rather than failing.
 */
export function useSiteShell(): Ref<string | null> {
  const admin = inject(adminKey, null)
  const shell = ref<string | null>(null)

  const url = computed(() => {
    const meta = admin?.state.manifest?.modules.find((module) => module.id === 'blocks')?.meta
    const stage = meta?.stage

    return typeof stage === 'string' && stage !== '' ? stage : null
  })

  watchEffect(() => {
    const current = url.value
    if (current === null) return

    void loadShell(current).then((loaded) => {
      if (url.value === current) shell.value = loaded
    })
  })

  return shell
}

/** For tests: forget what was loaded. */
export function forgetShells(): void {
  shells.clear()
}
