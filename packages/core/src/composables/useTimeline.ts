import { inject, type ComputedRef, type InjectionKey } from 'vue'

export type TimelineSize = 'sm' | 'md'

/** What `WxTimeline` hands down to the items inside it. */
export interface TimelineContext {
  size: ComputedRef<TimelineSize>
}

export const timelineKey: InjectionKey<TimelineContext> = Symbol('wx-timeline')

export function useTimeline(): TimelineContext | null {
  return inject(timelineKey, null)
}
