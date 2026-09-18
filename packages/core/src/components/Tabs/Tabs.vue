<script setup lang="ts">
import {
  computed,
  Fragment,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  useSlots,
  watch,
  type VNode,
} from 'vue'
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui'
import WxBadge from '../Badge/Badge.vue'
import WxDropdown from '../Dropdown/Dropdown.vue'
import WxDropdownItem from '../DropdownItem/DropdownItem.vue'
import WxIcon from '../Icon/Icon.vue'
import WxTab from '../Tab/Tab.vue'
import { useElementWidth } from '../../composables/useElementWidth'
import type { IconName } from '../Icon/types'
import type { TabDescriptor, TabsEmits, TabsProps, TabValue } from './types'

defineOptions({ name: 'WxTabs' })

const props = withDefaults(defineProps<TabsProps>(), {
  items: undefined,
  collapseBelow: 0,
  variant: 'line',
  size: 'md',
  orientation: 'horizontal',
  align: 'start',
  activationMode: 'automatic',
  keepAlive: false,
  loop: true,
  ariaLabel: undefined,
})

const emit = defineEmits<TabsEmits>()

defineSlots<{
  /** The `WxTab`s. */
  default?: () => unknown
  /** Sits at the end of the strip — a button, a filter, a count. */
  extra?: () => unknown
}>()

const model = defineModel<TabValue | undefined>({ default: undefined })

const slots = useSlots()

/** What the last render found, for the code that runs afterwards and cannot rescan. */
let rendered: TabDescriptor[] = []

/**
 * The strip is built from the `WxTab`s in the slot, so a tab and its panel are written
 * in one place. The scan runs during render rather than in a computed: slot output is
 * not reactive state, and a cached copy would keep showing yesterday's labels.
 *
 * Given `items`, there is nothing to scan: the strip is the list, and the slot is the one
 * panel under it.
 */
function readTabs(): TabDescriptor[] {
  rendered = props.items
    ? props.items.map((item) => ({ ...item, disabled: item.disabled === true }))
    : collect((slots.default?.() ?? []) as VNode[])
  scheduleSelect()
  return rendered
}

function collect(nodes: VNode[]): TabDescriptor[] {
  const found: TabDescriptor[] = []

  for (const node of nodes) {
    // `v-for` and `<template>` wrap what they render in a fragment.
    if (node.type === Fragment) {
      found.push(...collect((node.children ?? []) as VNode[]))
      continue
    }
    if (node.type !== WxTab) continue

    const tabProps = (node.props ?? {}) as Record<string, unknown>
    // A component's slots arrive as the vnode's children.
    const tabSlots = node.children as { label?: () => unknown } | null

    found.push({
      value: tabProps.value as TabValue,
      label: tabProps.label as string | undefined,
      icon: tabProps.icon as IconName | undefined,
      badge: tabProps.badge as string | number | undefined,
      // A bare `disabled` in a template is an empty string, not `true`.
      disabled: tabProps.disabled === '' || tabProps.disabled === true,
      labelSlot: typeof tabSlots?.label === 'function' ? tabSlots.label : undefined,
    })
  }

  return found
}

/**
 * Nothing selected shows an empty panel, so the first usable tab opens by itself. The
 * check is worth repeating: tabs often arrive from a request, and the tab that was open
 * can go away with the array it came from.
 */
function selectFirst() {
  /* Folded into a switch there is no strip to scan, so the list is asked directly. */
  const tabs: TabDescriptor[] = props.items
    ? props.items.map((item) => ({ ...item, disabled: item.disabled === true }))
    : rendered

  if (tabs.length === 0) return
  if (tabs.some((tab) => tab.value === model.value)) return

  model.value = (tabs.find((tab) => !tab.disabled) ?? tabs[0]!).value
}

let selectQueued = false

/**
 * The check is asked for from the render and answered after it, because writing state
 * mid-render is how render loops start.
 *
 * It hangs off the scan rather than off `onUpdated` deliberately. Slot content belongs
 * to whoever wrote it: when the tabs come from a `v-for` in the parent, Vue patches the
 * new tabs straight into this component's output without re-rendering it, so no update
 * hook of ours would ever run — but the strip is rebuilt, and that is this function.
 */
function scheduleSelect() {
  if (selectQueued) return

  selectQueued = true
  nextTick(() => {
    selectQueued = false
    selectFirst()
  })
}

watch(model, (value) => {
  if (value !== undefined) emit('change', value)
})

/* ---------------------------------------------------------------------------
 * The strip on a small screen
 *
 * Tabs outgrow a phone long before they outgrow a desktop, so the strip scrolls
 * sideways rather than wrapping into a second row that pushes the panel down. Three
 * things follow. The active tab is scrolled into view whenever it changes, because it
 * is often changed from somewhere else — a wizard step, a route. The end that has more
 * behind it is faded, so the strip reads as scrollable without spending any width on
 * saying so. And the arrows are for a mouse only: a finger just swipes.
 * ------------------------------------------------------------------------- */

const scroller = ref<HTMLElement | null>(null)
const overflowStart = ref(false)
const overflowEnd = ref(false)

const scrollable = computed(() => props.orientation === 'horizontal')

function measure() {
  const el = scroller.value
  if (!el || !scrollable.value) {
    overflowStart.value = false
    overflowEnd.value = false
    return
  }

  /* A pixel of slack: fractional widths never land exactly on the edge. */
  const max = el.scrollWidth - el.clientWidth
  overflowStart.value = el.scrollLeft > 1
  overflowEnd.value = el.scrollLeft < max - 1
}

function step(direction: 1 | -1) {
  const el = scroller.value
  if (!el) return

  /* Most of a screenful, so a tab or two stays behind as an anchor. */
  el.scrollLeft += direction * Math.max(el.clientWidth * 0.75, 120)
  measure()
}

/**
 * `instant` is for the first placement: a strip that opens on its eighth tab should
 * already be there, not slide there while the page is still settling.
 */
function revealActive(instant = false) {
  const el = scroller.value
  if (!el || !scrollable.value) return

  const active = el.querySelector<HTMLElement>('[data-state="active"]')
  if (!active) return

  /* `offsetLeft` is read against the scroller, which is positioned for exactly that. */
  const centred = active.offsetLeft - (el.clientWidth - active.offsetWidth) / 2

  if (instant) el.style.scrollBehavior = 'auto'
  /* Past the end it clamps by itself, which is what should happen for the last tab. */
  el.scrollLeft = Math.max(0, centred)
  if (instant) el.style.scrollBehavior = ''

  measure()
}

let observer: ResizeObserver | undefined

function observe() {
  observer?.disconnect()

  /* The strip overflows for two reasons: the container shrank, or the tabs changed. */
  observer = new ResizeObserver(() => measure())
  if (scroller.value) observer.observe(scroller.value)
  if (scroller.value?.firstElementChild) observer.observe(scroller.value.firstElementChild)
}

onMounted(() => {
  selectFirst()
  observe()
  measure()
  revealActive(true)
})

onBeforeUnmount(() => observer?.disconnect())

/* A list that arrives from a request can drop the view that was open. */
watch(
  () => props.items,
  () => scheduleSelect(),
)

/* ---------------------------------------------------------------------------
 * The strip as one switch
 *
 * A strip is worth scrolling while it is navigation somebody reads along; the views of a
 * list are not that. "All / Drafts / Published / Edited / Bin" is one question, and on a
 * phone it is asked the way a question is asked — with the answer showing and the rest a
 * tap away. Not three dots: three dots are actions, and this chooses what is on screen.
 * ------------------------------------------------------------------------- */

const layout = ref<HTMLElement | null>(null)
const ownWidth = useElementWidth(layout)

const folded = computed(
  () =>
    props.items !== undefined &&
    props.collapseBelow > 0 &&
    ownWidth.value > 0 &&
    ownWidth.value < props.collapseBelow,
)

/** The view the switch is labelled with. */
const current = computed(() => props.items?.find((item) => item.value === model.value))

/* Unfolding builds a new strip, and the old observer was watching an element that is gone. */
watch(folded, async () => {
  await nextTick()
  observe()
  measure()
  revealActive(true)
})

watch(model, async () => {
  await nextTick()
  revealActive()
})

const classes = computed(() => [
  'wx-tabs',
  `wx-tabs--${props.variant}`,
  `wx-tabs--${props.size}`,
  `wx-tabs--${props.orientation}`,
  `wx-tabs--align-${props.align}`,
  { 'is-folded': folded.value },
])

defineExpose({ measure, revealActive })
</script>

<template>
  <tabs-root
    v-model="model"
    :class="classes"
    :orientation="orientation"
    :activation-mode="activationMode"
    :unmount-on-hide="!keepAlive"
  >
    <!--
      The root is the container the layout asks about, and a container query cannot
      style the container itself — so the strip and the panels are laid out one level in.
    -->
    <div ref="layout" class="wx-tabs__layout">
      <div class="wx-tabs__bar">
        <!--
          Folded up, the whole strip is one control that says which view is open. The list
          behind it is the same list, so a view is still one tap away.
        -->
        <wx-dropdown v-if="folded" class="wx-tabs__switch" align="start" match-trigger-width>
          <template #trigger>
            <button class="wx-tabs__switch-trigger" type="button" :aria-label="ariaLabel">
              <wx-icon v-if="current?.icon" class="wx-tabs__icon" :name="current.icon" />
              <span class="wx-tabs__label">{{ current?.label ?? current?.value ?? '' }}</span>
              <wx-badge v-if="current?.badge !== undefined" size="sm" round>
                {{ current.badge }}
              </wx-badge>
              <wx-icon class="wx-tabs__switch-chevron" name="chevron-down" />
            </button>
          </template>

          <wx-dropdown-item
            v-for="item in items"
            :key="item.value"
            :icon="item.icon"
            :active="item.value === model"
            :disabled="item.disabled"
            @click="model = item.value"
          >
            {{ item.label ?? item.value }}
          </wx-dropdown-item>
        </wx-dropdown>

        <button
          v-if="!folded && (overflowStart || overflowEnd)"
          class="wx-tabs__arrow"
          type="button"
          tabindex="-1"
          aria-hidden="true"
          :disabled="!overflowStart"
          @click="step(-1)"
        >
          <wx-icon name="chevron-left" />
        </button>

        <div
          v-if="!folded"
          ref="scroller"
          class="wx-tabs__scroller"
          :class="{ 'is-overflow-start': overflowStart, 'is-overflow-end': overflowEnd }"
          @scroll.passive="measure"
        >
          <tabs-list class="wx-tabs__list" :loop="loop" :aria-label="ariaLabel">
            <tabs-trigger
              v-for="tab in readTabs()"
              :key="tab.value"
              class="wx-tabs__tab"
              :value="tab.value"
              :disabled="tab.disabled"
            >
              <wx-icon v-if="tab.icon" class="wx-tabs__icon" :name="tab.icon" />
              <span class="wx-tabs__label">
                <!-- A slot function renders as a functional component, in its own scope. -->
                <component :is="tab.labelSlot" v-if="tab.labelSlot" />
                <template v-else>{{ tab.label ?? tab.value }}</template>
              </span>
              <wx-badge v-if="tab.badge !== undefined" class="wx-tabs__badge" size="sm" round>
                {{ tab.badge }}
              </wx-badge>
            </tabs-trigger>
          </tabs-list>
        </div>

        <button
          v-if="!folded && (overflowStart || overflowEnd)"
          class="wx-tabs__arrow"
          type="button"
          tabindex="-1"
          aria-hidden="true"
          :disabled="!overflowEnd"
          @click="step(1)"
        >
          <wx-icon name="chevron-right" />
        </button>

        <div v-if="$slots.extra" class="wx-tabs__extra">
          <slot name="extra" />
        </div>
      </div>

      <div class="wx-tabs__panels">
        <!--
          One panel, bound to whatever is open: the tab changes what is asked for, not what
          draws the answer. A table under it keeps its search, its page and its scroll, which
          is the whole reason a list switches views this way rather than by swapping panels.
        -->
        <tabs-content v-if="items" class="wx-tabs__panel" :value="model ?? ''">
          <slot />
        </tabs-content>
        <slot v-else />
      </div>
    </div>
  </tabs-root>
</template>

<style scoped>
.wx-tabs {
  --wx-tabs-gap: var(--wx-space-4);
  --wx-tabs-fade: 24px;

  display: block;
  box-sizing: border-box;
  min-width: 0;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  /* The strip reacts to the width it was given, not to the window's. */
  container-type: inline-size;
}

.wx-tabs__layout {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-tabs__bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-tabs__scroller {
  /* Positioned so a tab's `offsetLeft` is measured against the strip it scrolls in. */
  position: relative;
  flex: 1 1 auto;
  min-width: 0;
  overflow-x: auto;
  /* A swipe along the strip must not become a page swipe or a browser back gesture. */
  overscroll-behavior-inline: contain;
  scroll-behavior: smooth;
  scrollbar-width: none;
}

.wx-tabs__scroller::-webkit-scrollbar {
  display: none;
}

/*
 * The fade says "there is more this way" and costs no width, which is the point on a
 * phone. Both ends share one mask, so a strip cut off at both ends fades at both.
 */
.wx-tabs__scroller.is-overflow-start,
.wx-tabs__scroller.is-overflow-end {
  mask-image: linear-gradient(
    to right,
    transparent 0,
    #000 var(--wx-tabs-fade-start, 0px),
    #000 calc(100% - var(--wx-tabs-fade-end, 0px)),
    transparent 100%
  );
}

.wx-tabs__scroller.is-overflow-start {
  --wx-tabs-fade-start: var(--wx-tabs-fade);
}

.wx-tabs__scroller.is-overflow-end {
  --wx-tabs-fade-end: var(--wx-tabs-fade);
}

.wx-tabs__list {
  display: flex;
  gap: var(--wx-tabs-gap);
  /* Narrower than the scroller, the list still has to span it for the rule under it. */
  width: max-content;
  min-width: 100%;
  /*
   * With the padding counted inside that 100%. A segmented strip pads its track by four on
   * each side, and on the default box those eight pixels are added to the full width the
   * `min-width` just asked for — so a strip with three tabs and room to spare overflowed its
   * scroller by exactly its own padding, faded at the end and grew a pair of arrows for a
   * journey of eight pixels.
   */
  box-sizing: border-box;
}

.wx-tabs__tab {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  gap: var(--wx-space-6);
  box-sizing: border-box;
  margin: 0;
  padding: var(--wx-space-10) var(--wx-space-12);
  background: none;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
  white-space: nowrap;
  cursor: pointer;
  transition:
    color var(--wx-duration-fast) var(--wx-easing-standard),
    background var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

/*
 * Given room, the tabs are page-level navigation and read as such. The question is
 * put to the strip rather than to the window: tabs in a narrow panel on a wide
 * desktop are still tabs in a narrow panel. The compact scale keeps its own size —
 * the rule below is more specific, so it wins wherever it applies.
 */
@container (min-width: 600px) {
  .wx-tabs__tab {
    font-size: var(--wx-font-size-md);
  }
}

.wx-tabs--sm .wx-tabs__tab {
  padding: var(--wx-space-6) var(--wx-space-10);
  font-size: var(--wx-font-size-xs);
}

.wx-tabs__tab:hover:not(:disabled) {
  color: var(--wx-text-default);
}

.wx-tabs__tab:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-tabs__tab:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-tabs__tab[data-state='active'] {
  color: var(--wx-color-primary);
}

.wx-tabs__icon {
  flex: 0 0 auto;
  font-size: var(--wx-font-size-md);
}

.wx-tabs__badge {
  flex: 0 0 auto;
}

.wx-tabs__arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 24px;
  height: 24px;
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-tabs__arrow:hover:not(:disabled) {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-tabs__arrow:disabled {
  color: var(--wx-text-disabled);
  cursor: default;
}

.wx-tabs__extra {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
  gap: var(--wx-space-6);
  padding-left: var(--wx-space-8);
}

/* The air between the strip and what it switches — the panel's step, like everything else. */
.wx-tabs__panels {
  min-width: 0;
  padding-top: var(--wx-gap, var(--wx-space-16));
}

/*
 * The one panel of a strip built from `items`. A column with the panel's own step, the same
 * as `WxTab` has: what stands in it is cards, and cards carry no margins of their own.
 */
.wx-tabs__panel {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.wx-tabs__panel:focus-visible {
  outline: none;
}

/* Folded up: one control, as wide as the strip it replaced. */
.wx-tabs__switch {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-tabs__switch-trigger {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  width: 100%;
  min-height: 44px;
  padding: var(--wx-space-8) var(--wx-space-12);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-strong);
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
  text-align: start;
  cursor: pointer;
}

.wx-tabs__switch-trigger:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-tabs__switch-chevron {
  flex: 0 0 auto;
  margin-inline-start: auto;
  color: var(--wx-text-muted);
}

/* The rule under the strip belongs to the strip; a single control is not one. */
.wx-tabs.is-folded .wx-tabs__bar {
  border-bottom: none;
}

/* Line — the default: a rule under the strip with the active tab standing on it. */
.wx-tabs--line .wx-tabs__bar {
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-tabs--line .wx-tabs__tab {
  border-radius: var(--wx-radius-xs) var(--wx-radius-xs) 0 0;
  box-shadow: inset 0 -2px 0 transparent;
}

.wx-tabs--line .wx-tabs__tab[data-state='active'] {
  box-shadow: inset 0 -2px 0 var(--wx-color-primary);
}

.wx-tabs--line .wx-tabs__tab:focus-visible {
  box-shadow: var(--wx-ring-focus);
}

/* Pill — a segmented control: the list is the track, the active tab the thumb. */
.wx-tabs--pill .wx-tabs__list {
  gap: var(--wx-space-2);
  padding: var(--wx-space-4);
  background: var(--wx-bg-muted);
  border-radius: var(--wx-radius-sm);
}

.wx-tabs--pill .wx-tabs__tab {
  border-radius: var(--wx-radius-xs);
}

.wx-tabs--pill .wx-tabs__tab[data-state='active'] {
  background: var(--wx-bg-surface);
  color: var(--wx-text-strong);
  box-shadow: var(--wx-shadow-card);
}

/* Card — folder tabs: the active one is a sheet that has risen through the rule. */
.wx-tabs--card .wx-tabs__bar {
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-tabs--card .wx-tabs__list {
  gap: 0;
}

.wx-tabs--card .wx-tabs__tab {
  margin-bottom: -1px;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-xs) var(--wx-radius-xs) 0 0;
}

.wx-tabs--card .wx-tabs__tab[data-state='active'] {
  background: var(--wx-bg-surface);
  border-color: var(--wx-border-default);
  border-bottom-color: var(--wx-bg-surface);
}

/* Alignment only means anything while the tabs still fit. */
.wx-tabs--align-center .wx-tabs__list {
  justify-content: center;
}

.wx-tabs--align-end .wx-tabs__list {
  justify-content: flex-end;
}

.wx-tabs--align-stretch .wx-tabs__list {
  width: 100%;
}

.wx-tabs--align-stretch .wx-tabs__tab {
  flex: 1 1 0;
  justify-content: center;
}

/* A column of tabs beside the panel. */
.wx-tabs--vertical .wx-tabs__layout {
  flex-direction: row;
  align-items: flex-start;
  gap: var(--wx-gap, var(--wx-space-16));
}

.wx-tabs--vertical .wx-tabs__bar {
  flex: 0 0 auto;
  flex-direction: column;
  align-items: stretch;
}

.wx-tabs--vertical .wx-tabs__scroller {
  overflow-x: visible;
}

.wx-tabs--vertical .wx-tabs__list {
  flex-direction: column;
  width: auto;
  min-width: 0;
}

.wx-tabs--vertical .wx-tabs__tab {
  justify-content: flex-start;
}

.wx-tabs--vertical .wx-tabs__panels {
  flex: 1 1 auto;
  padding-top: 0;
}

.wx-tabs--vertical.wx-tabs--line .wx-tabs__bar {
  border-right: 1px solid var(--wx-border-default);
  border-bottom: none;
}

.wx-tabs--vertical.wx-tabs--line .wx-tabs__tab {
  border-radius: var(--wx-radius-xs) 0 0 var(--wx-radius-xs);
  box-shadow: inset -2px 0 0 transparent;
}

.wx-tabs--vertical.wx-tabs--line .wx-tabs__tab[data-state='active'] {
  box-shadow: inset -2px 0 0 var(--wx-color-primary);
}

/*
 * A column of tabs and a panel do not both fit across a phone. Below the width where
 * that stops working the column lies back down into an ordinary scrolling strip and
 * the panel takes the full width. The question is put to the tabs rather than to the
 * window: the same squeeze happens inside a narrow drawer on a wide monitor.
 */
@container (max-width: 480px) {
  .wx-tabs--vertical .wx-tabs__layout {
    flex-direction: column;
    gap: 0;
  }

  .wx-tabs--vertical .wx-tabs__bar {
    flex-direction: row;
    align-items: center;
  }

  .wx-tabs--vertical .wx-tabs__scroller {
    overflow-x: auto;
  }

  .wx-tabs--vertical .wx-tabs__list {
    flex-direction: row;
    width: max-content;
    min-width: 100%;
  }

  .wx-tabs--vertical .wx-tabs__panels {
    padding-top: var(--wx-gap, var(--wx-space-16));
  }

  .wx-tabs--vertical.wx-tabs--line .wx-tabs__bar {
    border-right: none;
    border-bottom: 1px solid var(--wx-border-default);
  }

  .wx-tabs--vertical.wx-tabs--line .wx-tabs__tab {
    border-radius: var(--wx-radius-xs) var(--wx-radius-xs) 0 0;
    box-shadow: inset 0 -2px 0 transparent;
  }

  .wx-tabs--vertical.wx-tabs--line .wx-tabs__tab[data-state='active'] {
    box-shadow: inset 0 -2px 0 var(--wx-color-primary);
  }
}

/* A finger needs a target it can hit, and has no use for the arrows — it swipes. */
@media (pointer: coarse) {
  .wx-tabs__tab {
    min-height: 44px;
  }

  .wx-tabs__arrow {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-tabs__scroller {
    scroll-behavior: auto;
  }

  .wx-tabs__tab {
    transition: none;
  }
}
</style>
