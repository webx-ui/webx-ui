<script setup lang="ts">
import {
  computed,
  nextTick,
  onMounted,
  provide,
  reactive,
  ref,
  useSlots,
  watch,
  type VNode,
} from 'vue'
import WxSubmenu from '../Submenu/Submenu.vue'
import { menuKey, type MenuValue } from '../../composables/useMenu'
import { useElementWidth } from '../../composables/useElementWidth'
import { WxNodes, flattenNodes } from '../../internal/nodes'
import type { MenuEmits, MenuProps } from './types'

defineOptions({ name: 'WxMenu' })

const props = withDefaults(defineProps<MenuProps>(), {
  mode: 'vertical',
  size: 'md',
  overflow: 'menu',
  overflowTitle: 'More',
  collapsed: false,
  accordion: false,
  autoExpand: true,
  label: undefined,
})

const emit = defineEmits<MenuEmits>()

/** The branch the overflow moves into. Reserved: an entry may not use this value. */
const OVERFLOW = '__wx-menu-overflow'

const active = defineModel<MenuValue | undefined>({ default: undefined })
const openKeys = defineModel<MenuValue[]>('open', { default: () => [] })

/* ---------------------------------------------------------------- overflow --- */

/**
 * A bar gets whatever room the header has left over, which is rarely as much as the
 * sections need. Rather than clip them or scroll them out of sight, the entries that
 * do not fit move into a branch at the end of the bar.
 *
 * They *move*: every entry is rendered once, in the bar or in the branch, so it
 * registers its position once and the branch can report that it holds the page you
 * are on. That is why the entries are split as vnodes, rather than rendered twice
 * and hidden with CSS in whichever half is not wanted.
 */
const slots = useSlots()
const list = ref<HTMLElement | null>(null)
const listWidth = useElementWidth(list)

const splits = computed(() => props.mode === 'horizontal' && props.overflow === 'menu')

/** How many entries stand in the bar. All of them, until a measurement says fewer. */
const fits = ref(Number.POSITIVE_INFINITY)

/* While this is on everything is in the bar, and the branch is there to be measured. */
const measuring = ref(false)

/**
 * Whether anything is in the branch. It is read off the last measurement rather than
 * off the entries, because everything that asks the slot for its entries has to do
 * so while rendering — Vue tracks a slot's dependencies only there — and this is
 * wanted outside the template.
 */
const overflowing = ref(false)

/*
 * Only the template reads these. Keep it that way: evaluating them is invoking the
 * slot, and that belongs in a render.
 */
const entries = computed<VNode[]>(() => (splits.value ? flattenNodes(slots.default?.() ?? []) : []))

const inBar = computed(() => entries.value.slice(0, fits.value))
const inBranch = computed(() => entries.value.slice(fits.value))

function lengthOf(value: string) {
  const parsed = Number.parseFloat(value)
  return Number.isFinite(parsed) ? parsed : 0
}

async function measure() {
  if (!splits.value) {
    fits.value = Number.POSITIVE_INFINITY
    overflowing.value = false
    return
  }

  /* Everything back in the bar, at its natural width, before anything is read. */
  measuring.value = true
  fits.value = Number.POSITIVE_INFINITY
  await nextTick()

  const el = list.value
  if (!el) {
    measuring.value = false
    return
  }

  const style = getComputedStyle(el)
  const gap = lengthOf(style.columnGap)
  const room = el.clientWidth - lengthOf(style.paddingLeft) - lengthOf(style.paddingRight)

  /* The branch is the last child; the entries are everything before it. */
  const children = [...el.children] as HTMLElement[]
  const branch = children.at(-1)
  const widths = children.slice(0, -1).map((child) => child.getBoundingClientRect().width)

  const needed = widths.reduce((total, width, index) => total + width + (index ? gap : 0), 0)

  if (needed <= room) {
    fits.value = widths.length
  } else {
    /* Room for the branch comes out of the budget before any entry is counted. */
    const budget = room - (branch?.getBoundingClientRect().width ?? 0) - gap
    let used = 0
    let count = 0

    for (const width of widths) {
      const next = used + width + (count ? gap : 0)
      if (next > budget) break
      used = next
      count += 1
    }

    fits.value = count
  }

  /* Counted off the elements just measured, so the slot is not asked again. */
  overflowing.value = fits.value < widths.length

  await nextTick()
  measuring.value = false
}

watch(
  [listWidth, splits],
  () => {
    /* The bar changing width is what asks for a measurement — and measuring it back
     * to its natural width can change it again, which is not a new question. */
    if (!measuring.value) void measure()
  },
  { immediate: true },
)

onMounted(() => {
  /*
   * And again once the typeface has arrived. The first measurement is taken in the
   * fallback face, which is a few pixels narrower per label — enough, over a row of
   * them, to leave one entry in the bar that the real face has no room for.
   */
  void document.fonts?.ready.then(() => measure())
})

/** Where every entry sits: its value against the submenus above it. */
const positions = reactive(new Map<MenuValue, MenuValue[]>())

/*
 * Where entries have been seen, which is not the same thing. A flyout unmounts what
 * it holds as it closes, so an entry that moved into the overflow branch stops
 * reporting its position the moment the panel shuts — and the bar still has to show
 * which branch the page you are on is in.
 */
const seen = new Map<MenuValue, MenuValue[]>()

const collapsed = computed(() => props.collapsed && props.mode === 'vertical')

const trail = computed(() => {
  if (active.value === undefined) return []

  const here = positions.get(active.value)
  if (here) {
    seen.set(active.value, here)
    return here
  }

  /*
   * Nothing on the page reports holding it. The one place an entry can be and not be
   * on the page is inside a shut overflow panel, so that is where it is — in front of
   * wherever it last said it sat.
   */
  const known = seen.get(active.value) ?? []
  return overflowing.value ? [OVERFLOW, ...known] : known
})

function setOpen(value: MenuValue, open: boolean, ancestors: MenuValue[]) {
  if (!open) {
    openKeys.value = openKeys.value.filter((key) => key !== value)
    return
  }

  /*
   * In accordion mode the branch that opens is the whole answer: its own ancestors
   * stay open, everything else closes. Listing the ancestors beats hunting for
   * siblings, which would mean knowing the depth of every open key.
   */
  openKeys.value = props.accordion
    ? [...ancestors, value]
    : [...openKeys.value.filter((key) => key !== value), value]
}

/* Every open flyout listens for this to change; nothing reads the number itself. */
const closeSignal = ref(0)

provide(menuKey, {
  mode: computed(() => props.mode),
  size: computed(() => props.size),
  collapsed,
  active: computed(() => active.value),
  trail,
  select: (value, event) => {
    active.value = value
    emit('select', value, event)
  },
  isOpen: (value) => openKeys.value.includes(value),
  setOpen,
  closeSignal: computed(() => closeSignal.value),
  closeFlyouts: () => {
    closeSignal.value += 1
  },
  register: (value, ancestors) => positions.set(value, ancestors),
  unregister: (value) => positions.delete(value),
})

/*
 * Following the active entry rather than the click: a sidebar is normally rendered
 * against the current route, so the branch has to open for a value that arrived
 * with the page instead of through a trigger.
 */
watch(
  [trail, () => props.autoExpand],
  ([ancestors, autoExpand]) => {
    if (!autoExpand || ancestors.length === 0) return
    const missing = ancestors.filter((key) => !openKeys.value.includes(key))
    if (missing.length === 0) return
    openKeys.value = props.accordion ? [...ancestors] : [...openKeys.value, ...missing]
  },
  { immediate: true },
)

const classes = computed(() => [
  'wx-menu',
  `wx-menu--${props.mode}`,
  `wx-menu--${props.size}`,
  {
    'wx-menu--collapsed': collapsed.value,
    'wx-menu--overflow': splits.value,
  },
])
</script>

<template>
  <ul ref="list" :class="classes" :aria-label="label">
    <template v-if="splits">
      <wx-nodes :nodes="inBar" />

      <!--
        Rendered whether or not it is needed, because its own width is part of the
        sum that decides how many entries fit beside it.
      -->
      <wx-submenu
        :value="OVERFLOW"
        class="wx-menu__overflow"
        icon="more-horizontal"
        :title="overflowTitle"
        :hidden="inBranch.length === 0 && !measuring"
      >
        <wx-nodes :nodes="inBranch" />
      </wx-submenu>
    </template>

    <slot v-else />
  </ul>
</template>

<style scoped>
/*
 * A list of links in a list of items, not `role="menu"`. That role promises the
 * keyboard model of a desktop application menu — arrow keys, type-ahead, one tab
 * stop — and navigation that is really a list of links is easier to use, and to
 * read out, exactly as what it is.
 */
.wx-menu {
  --wx-menu-row-height: 36px;
  --wx-menu-row-padding: var(--wx-space-10);
  --wx-menu-indent: var(--wx-space-16);
  --wx-menu-gap: 2px;

  display: flex;
  box-sizing: border-box;
  margin: 0;
  padding: var(--wx-space-8);
  list-style: none;
  background: transparent;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-menu--sm {
  --wx-menu-row-height: 30px;
  --wx-menu-row-padding: var(--wx-space-8);
  --wx-menu-indent: var(--wx-space-12);

  font-size: var(--wx-font-size-xs);
}

.wx-menu--vertical {
  flex-direction: column;
  gap: var(--wx-menu-gap);
  width: 100%;
}

.wx-menu--horizontal {
  flex-direction: row;
  align-items: center;
  gap: var(--wx-space-2);
  overflow-x: auto;
  /* A bar that has to scroll should not show a bar of its own doing it. */
  scrollbar-width: none;
}

.wx-menu--horizontal::-webkit-scrollbar {
  display: none;
}

/*
 * A bar that folds its overflow away never scrolls: what does not fit has somewhere
 * to be. It is still clipped, for the frame in which everything is measured.
 */
.wx-menu--overflow {
  overflow-x: hidden;
}

/*
 * The rail is a column of squares, so the padding around it comes down to the gap
 * that keeps them off the edge — the width of the sidebar is doing the spacing.
 */
.wx-menu--collapsed {
  align-items: center;
  padding: var(--wx-space-6) var(--wx-space-4);
}
</style>
