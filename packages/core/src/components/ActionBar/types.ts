export interface ActionBarProps {
  /**
   * Keeps the bar at the bottom of the window while there is screen left below it, and
   * lets it settle into its place in the flow at the end.
   *
   * `--wx-action-bar-bottom` is how far off the bottom edge it stops, so a shell that
   * insets its column can say so; the strip below the bar is painted over, or the content
   * scrolling past would show through it.
   *
   * While it sticks, the bar writes `--wx-action-bar-room` on its parent — its own height
   * plus that offset — so anything else pinned to the window can stop short of it.
   */
  sticky?: boolean
  /** Rule around the bar. Off leaves the surface and the shadow. */
  bordered?: boolean
}
