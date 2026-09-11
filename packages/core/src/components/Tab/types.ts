import type { IconName } from '../Icon/types'
import type { TabValue } from '../Tabs/types'

export interface TabProps {
  /** Unique within the tabs; this is what `v-model` holds. */
  value: TabValue
  /** Text of the tab itself. The `label` slot replaces it. */
  label?: string
  /** Icon before the label. */
  icon?: IconName
  /** A count or a short marker after the label. */
  badge?: string | number
  disabled?: boolean
  /** Keep this one panel mounted while it is hidden, whatever the tabs say. */
  keepAlive?: boolean
}
