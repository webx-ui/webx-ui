<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { WxIcon } from '@webx-ui/core'
import { useTranslate } from './i18n'

/**
 * Whether the work is saved, said in the smallest way that says it.
 *
 * A word in the bar — "Saved" — is a word that is right nearly all of the time and therefore
 * reads as furniture: it is on screen when nothing is happening, which is exactly when nobody
 * is asking. What anyone actually wants to know is whether *this* save landed, and only for
 * as long as it takes to land. So: a wheel while it is in flight, a tick when it lands, and
 * nothing a couple of seconds later.
 *
 * Nothing at all for unsaved work either. That is not a small decision, and it holds because
 * the screens that use this say so twice over: the head carries a badge beside the name, and
 * the save button is enabled — a button offering to do something is the clearest statement
 * that there is something to do.
 *
 * The words stay, for whoever is not looking at the bar: the element is a live region and the
 * icon carries the sentence, so a screen reader hears "Saving…" and then "Saved".
 */
const props = withDefaults(
  defineProps<{
    /** What the screen is doing. Anything but `saving` and `saved` shows nothing. */
    state: 'saving' | 'unsaved' | 'saved' | string
    /** How long the tick stays after a save lands, in milliseconds. */
    linger?: number
  }>(),
  { linger: 2000 },
)

const t = useTranslate('webx-admin')

/*
 * The tick is shown for a save that happened, not for a screen that opens on saved work —
 * which is every screen, every time. So it is the *change* out of `saving` that lights it,
 * and never the value on its own.
 */
const landed = ref(false)
let fade: number | null = null

watch(
  () => props.state,
  (now, before) => {
    if (fade !== null) clearTimeout(fade)

    landed.value = before === 'saving' && now === 'saved'

    if (!landed.value) return

    fade = window.setTimeout(() => {
      landed.value = false
      fade = null
    }, props.linger)
  },
)

const showing = computed(() =>
  props.state === 'saving' ? 'saving' : landed.value ? 'saved' : null,
)

onBeforeUnmount(() => {
  if (fade !== null) clearTimeout(fade)
})
</script>

<template>
  <span class="wx-save-state" role="status" aria-live="polite">
    <wx-icon
      v-if="showing"
      class="wx-save-state__mark"
      :class="{ 'is-saved': showing === 'saved' }"
      :name="showing === 'saving' ? 'loader' : 'check'"
      :spin="showing === 'saving'"
      :label="t(`editor.${showing}`)"
    />
  </span>
</template>

<style scoped>
/*
 * It keeps its place while it is empty. The bar lays the state out beside the buttons, and a
 * box that appears and disappears would shift them by its width twice per save.
 */
.wx-save-state {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: none;
  width: var(--wx-size-control-sm);
  height: var(--wx-size-control-sm);
}

.wx-save-state__mark {
  font-size: var(--wx-font-size-lg);
  color: var(--wx-text-muted);
}

.wx-save-state__mark.is-saved {
  color: var(--wx-color-success);
}
</style>
