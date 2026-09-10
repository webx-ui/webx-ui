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
