import { ref, type Ref } from 'vue'

/**
 * Whether the pointer on this device can hover.
 *
 * `false` on a touch screen, and that is the whole point: a tip that opens on hover has no
 * hover to open on. What a phone does instead is open it on the tap that was meant for the
 * button — so the reader taps, a black label appears over what they were aiming at, and the
 * button does its job underneath. A tooltip there is never information, only interference.
 *
 * One query and one listener for the whole application, because every `WxAction` on a screen
 * would otherwise register its own. A device that gains a mouse — a tablet with a keyboard
 * folded on — changes the answer, so this is a ref and not a constant read once.
 */
let hoverable: Ref<boolean> | null = null

export function useHoverPointer(): Ref<boolean> {
  if (hoverable !== null) return hoverable

  // Assume hover where the question cannot be asked — on the server and in jsdom, where the
  // answer would otherwise be "touch" for every test that has never seen a screen at all.
  const state = ref(true)

  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    const query = window.matchMedia('(hover: hover)')

    state.value = query.matches
    query.addEventListener('change', (event) => {
      state.value = event.matches
    })
  }

  hoverable = state

  return hoverable
}
