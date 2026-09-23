/**
 * How wide the column holding a row's `···` has to be.
 *
 * The menu is a finger target — 44px under `(pointer: coarse)`, which is what `WxActions` gives
 * a collapsed row — and the cell around it keeps `--wx-table-padding-x` on both sides. 44 and 16
 * and 16 is this number; the 56 the sections used to declare was never enough for it, and the
 * button simply painted outside its column, which nothing said out loud until cells began to
 * clip what does not fit.
 *
 * One constant rather than a number in seven files: when the touch target changes, the columns
 * that hold it change with it.
 */
export const rowMenuWidth = 76
