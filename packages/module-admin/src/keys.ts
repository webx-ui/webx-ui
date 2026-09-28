import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Hands an editor the keys pressed while nothing on the page has focus.
 *
 * An editor listens with `@keydown` on its own root, so Ctrl+S reaches it from any field inside.
 * A click on empty space — the page around the form, a label, a gap between cards — moves focus
 * to `<body>`, and from there the key never passes through the editor: the browser offered to
 * save the page as HTML instead. Only keys from `<body>` are passed on; one pressed in a dialog or
 * a menu belongs to that, and the editor's own root still gets everything from inside it.
 */
export function useBodyKeys(handler: (event: KeyboardEvent) => void): void {
  function listener(event: KeyboardEvent): void {
    if (event.target === document.body || event.target === document.documentElement) handler(event)
  }

  onMounted(() => window.addEventListener('keydown', listener))
  onBeforeUnmount(() => window.removeEventListener('keydown', listener))
}
