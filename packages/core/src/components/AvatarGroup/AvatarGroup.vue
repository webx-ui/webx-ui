<script setup lang="ts">
import { computed, useSlots, type VNode } from 'vue'
import WxAvatar from '../Avatar/Avatar.vue'
import { flattenNodes, WxNodes } from '../../internal/nodes'
import type { AvatarGroupProps } from './types'

defineOptions({ name: 'WxAvatarGroup' })

const props = withDefaults(defineProps<AvatarGroupProps>(), {
  max: 0,
  size: 'md',
  shape: 'circle',
  overlap: 0.3,
})

const slots = useSlots()

/*
 * The avatars are split rather than hidden, for the same reason the menu bar splits
 * its entries: what is over the limit is not rendered at all, so eight hundred people
 * in a room cost three avatars and a number.
 */
const avatars = computed<VNode[]>(() => flattenNodes(slots.default?.() ?? []))

const shown = computed(() => (props.max > 0 ? avatars.value.slice(0, props.max) : avatars.value))

const hidden = computed(() => Math.max(0, avatars.value.length - shown.value.length))

const style = computed(() => ({
  '--wx-avatar-group-overlap': `${-props.overlap}em`,
}))
</script>

<template>
  <div class="wx-avatar-group" :class="`wx-avatar-group--${size}`" :style="style">
    <wx-nodes :nodes="shown" />

    <wx-avatar
      v-if="hidden > 0"
      class="wx-avatar-group__rest"
      :size="size"
      :shape="shape"
      tone="neutral"
      :name="`+${hidden}`"
    >
      +{{ hidden }}
    </wx-avatar>
  </div>
</template>

<style scoped>
.wx-avatar-group {
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
}

/*
 * The overlap is stated in `em` against the avatar's own text size, so a row of
 * large avatars overlaps proportionally rather than by a number chosen for small
 * ones. The ring is the page behind them, which is what makes the stack read as a
 * stack rather than as one wide smudge.
 */
.wx-avatar-group :deep(.wx-avatar) {
  box-shadow: 0 0 0 2px var(--wx-bg-surface);
}

.wx-avatar-group :deep(.wx-avatar:not(:first-child)) {
  margin-inline-start: var(--wx-avatar-group-overlap, -0.3em);
}

.wx-avatar-group--xs {
  font-size: 20px;
}

.wx-avatar-group--sm {
  font-size: 26px;
}

.wx-avatar-group--md {
  font-size: 32px;
}

.wx-avatar-group--lg {
  font-size: 40px;
}

.wx-avatar-group--xl {
  font-size: 56px;
}
</style>
