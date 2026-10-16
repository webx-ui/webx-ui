<script setup lang="ts">
/**
 * The name of the site in the panel's head, made into the way to the site: the name itself is
 * the link, and the small arrow after it says the link leaves the panel.
 *
 * A new tab, because the panel is where the work is — somebody who glances at the site wants
 * to come back to the screen they left, not to a sign-in and a menu.
 *
 * The arrow sits right after the name rather than at the far end of the row: the name is cut
 * short with an ellipsis before the arrow is, so a long one never pushes it out.
 */
defineProps<{
  href: string
  /** What the link is called aloud: the site's name, then what following it does. */
  label: string
  /** The words of the tooltip. */
  hint: string
}>()
</script>

<template>
  <a
    class="wx-admin-site"
    :href="href"
    target="_blank"
    rel="noopener"
    :aria-label="label"
    :title="hint"
  >
    <slot />
    <wx-icon name="external-link" size="sm" class="wx-admin-site__icon" />
  </a>
</template>

<style scoped>
.wx-admin-site {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  max-width: 100%;
  border-radius: var(--wx-radius-xs);
  color: inherit;
  text-decoration: none;
}

.wx-admin-site:hover {
  color: var(--wx-text-link);
}

.wx-admin-site:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}

.wx-admin-site__icon {
  flex: none;
  color: var(--wx-text-muted);
}

.wx-admin-site:hover .wx-admin-site__icon {
  color: inherit;
}
</style>
