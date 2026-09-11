<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { AvatarEmits, AvatarProps, AvatarTone } from './types'

defineOptions({ name: 'WxAvatar' })

const props = withDefaults(defineProps<AvatarProps>(), {
  src: undefined,
  alt: undefined,
  name: undefined,
  icon: undefined,
  size: 'md',
  shape: 'circle',
  tone: 'auto',
  fit: 'cover',
})

const emit = defineEmits<AvatarEmits>()

/** The tints `auto` chooses between. The order is the answer, so it stays put. */
const TONES: Exclude<AvatarTone, 'auto'>[] = [
  'primary',
  'success',
  'warning',
  'danger',
  'info',
  'neutral',
]

/**
 * Two letters out of a name: the first of the first word and the first of the last.
 * One word gives its first two, so `Nova` is `NO` rather than a lonely `N`.
 */
function initialsOf(name: string) {
  const words = name.trim().split(/\s+/).filter(Boolean)
  if (words.length === 0) return ''
  if (words.length === 1) return [...words[0]].slice(0, 2).join('').toUpperCase()
  return ([...words[0]][0] + [...words[words.length - 1]][0]).toUpperCase()
}

/**
 * A number from the name, so the same person keeps the same colour everywhere —
 * across pages, across reloads, and on somebody else's screen.
 */
function hash(value: string) {
  let total = 0
  for (const character of value) total = (total * 31 + character.codePointAt(0)!) % 1_000_003
  return total
}

const initials = computed(() => (props.name ? initialsOf(props.name) : ''))

const tone = computed<Exclude<AvatarTone, 'auto'>>(() => {
  if (props.tone !== 'auto') return props.tone
  if (!props.name) return 'neutral'
  return TONES[hash(props.name) % TONES.length]
})

const failed = ref(false)

/* A new picture deserves its own chance; the last one's failure is not its fault. */
watch(
  () => props.src,
  () => {
    failed.value = false
  },
)

function onError(event: Event) {
  failed.value = true
  emit('error', event)
}

const classes = computed(() => [
  'wx-avatar',
  `wx-avatar--${props.size}`,
  `wx-avatar--${props.shape}`,
  `wx-avatar--${tone.value}`,
])

/*
 * The fallback is always in the markup and the picture lies on top of it. A picture
 * that is still arriving is a transparent box, so the initials show through and are
 * covered the moment it lands — no flash of one followed by the other, and no timer
 * deciding how long to wait before admitting there is nothing to show.
 */
</script>

<template>
  <span :class="classes">
    <span class="wx-avatar__fallback">
      <slot>
        <wx-icon v-if="icon" :name="icon" />
        <span v-else-if="initials" class="wx-avatar__initials">{{ initials }}</span>
        <wx-icon v-else name="user" />
      </slot>
    </span>

    <img
      v-if="src && !failed"
      class="wx-avatar__image"
      :src="src"
      :alt="alt ?? name ?? ''"
      :style="{ objectFit: fit }"
      @error="onError"
    />
  </span>
</template>

<style scoped>
.wx-avatar {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  flex: 0 0 auto;
  width: var(--wx-avatar-size);
  height: var(--wx-avatar-size);
  overflow: hidden;
  background: var(--wx-avatar-bg);
  color: var(--wx-avatar-fg);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-avatar-text);
  font-weight: var(--wx-font-weight-semibold);
  line-height: 1;
  /* Initials are read, not selected. */
  user-select: none;
  vertical-align: middle;
}

.wx-avatar--xs {
  --wx-avatar-size: 20px;
  --wx-avatar-text: 9px;
}

.wx-avatar--sm {
  --wx-avatar-size: 26px;
  --wx-avatar-text: var(--wx-font-size-xs);
}

.wx-avatar--md {
  --wx-avatar-size: 32px;
  --wx-avatar-text: var(--wx-font-size-xs);
}

.wx-avatar--lg {
  --wx-avatar-size: 40px;
  --wx-avatar-text: var(--wx-font-size-sm);
}

.wx-avatar--xl {
  --wx-avatar-size: 56px;
  --wx-avatar-text: var(--wx-font-size-lg);
}

.wx-avatar--circle {
  border-radius: var(--wx-radius-full);
}

.wx-avatar--square {
  border-radius: var(--wx-radius-xs);
}

.wx-avatar--neutral {
  --wx-avatar-bg: var(--wx-bg-fill);
  --wx-avatar-fg: var(--wx-text-muted);
}

.wx-avatar--primary {
  --wx-avatar-bg: var(--wx-color-primary-soft);
  --wx-avatar-fg: var(--wx-color-primary);
}

.wx-avatar--success {
  --wx-avatar-bg: var(--wx-color-success-soft);
  --wx-avatar-fg: var(--wx-color-success-active);
}

.wx-avatar--warning {
  --wx-avatar-bg: var(--wx-color-warning-soft);
  --wx-avatar-fg: var(--wx-color-warning-active);
}

.wx-avatar--danger {
  --wx-avatar-bg: var(--wx-color-danger-soft);
  --wx-avatar-fg: var(--wx-color-danger);
}

.wx-avatar--info {
  --wx-avatar-bg: var(--wx-color-info-soft);
  --wx-avatar-fg: var(--wx-color-info-active);
}

.wx-avatar__fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.wx-avatar__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  /* Inherits the circle from the box it fills, so a photograph is not a square. */
  border-radius: inherit;
}

/* The glyph is sized against the circle rather than against the initials. */
.wx-avatar__fallback :deep(.wx-icon) {
  width: 60%;
  height: 60%;
}
</style>
