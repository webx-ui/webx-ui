import type { AvatarShape, AvatarSize } from '../Avatar/types'

export interface AvatarGroupProps {
  /**
   * How many to show before the rest become a count. `0` shows every one of them.
   */
  max?: number
  /** Size for every avatar in the group; each one may still set its own. */
  size?: AvatarSize
  shape?: AvatarShape
  /** How far each avatar slides over the one before it, as a share of its width. */
  overlap?: number
}
