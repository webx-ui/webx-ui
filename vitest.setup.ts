import { afterEach, vi } from 'vitest'

/**
 * jsdom implements the DOM but not layout, so anything that measures geometry
 * returns nothing useful — and ProseMirror measures the caret whenever it scrolls
 * the selection into view. These stubs make those calls answer instead of throw;
 * nothing in our tests asserts on geometry.
 */
const emptyRectList = {
  length: 0,
  item: () => null,
  [Symbol.iterator]: function* () {},
} as unknown as DOMRectList

if (typeof Range !== 'undefined') {
  Range.prototype.getClientRects = () => emptyRectList
  Range.prototype.getBoundingClientRect = () => new DOMRect()
}

if (typeof Element !== 'undefined') {
  Element.prototype.scrollIntoView = () => {}
  if (!Element.prototype.getClientRects) {
    Element.prototype.getClientRects = () => emptyRectList
  }
}

/** Reka's slider measures its track through a ResizeObserver, which jsdom lacks. */
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver
}

/*
 * A modal opened from code (`openModal`, `confirm`) takes its host away on its own timer, a
 * moment after it closes. A test that answers a confirm and ends at once leaves that timer
 * behind; when it fires after the file's jsdom is gone, Vue unmounts the dialog's teleport
 * into nothing — "Cannot read properties of null (reading 'nextSibling')" — and fails the job
 * with every test green. Registered here, this hook runs after every file's own after-hooks.
 * Under fake timers the test owns the clock, and a host that never goes is one still open —
 * it has no timer to outlive anything, so the wait gives up on it.
 */
afterEach(async () => {
  if (typeof document === 'undefined' || vi.isFakeTimers()) return

  const deadline = Date.now() + 1000

  while (document.querySelector('.wx-modal-host') && Date.now() < deadline) {
    await new Promise((settle) => setTimeout(settle, 10))
  }
})
