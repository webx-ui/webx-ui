<script setup lang="ts">
import { computed } from 'vue'
import { useLocales, WxAlert, WxInput } from '@webx-ui/core'
import { useRecordAddress } from './address'

/**
 * The address of a record: the part that is edited, with the module's prefix in front of it.
 *
 * `implants` on its own says nothing about whether the catalogue lives under `/services/` or at
 * the root of the site, which is what somebody checking an address wants to see — so the prefix
 * stands inside the control, where the address is read and written in one place. The editor
 * hosting the screen hands it over with {@link provideRecordAddress}.
 *
 * The line under it appears only once there is something to lose: a record on the site whose
 * address is being changed leaves a redirect behind, and that is the fact that decides whether
 * this is safe to do at all. Said before the save rather than in a toast afterwards.
 *
 * Nothing is declared as a prop on purpose: a wrapper that declared what `WxInput` takes would
 * hand it a `false` where the screen asked for nothing (CLAUDE.md §4).
 */
defineOptions({ name: 'WxSlugField', inheritAttrs: false })

const address = useRecordAddress()
const locales = useLocales()

/** `/services/`, or `/` for a module that lives at the root of the site. */
const prefix = computed(() => {
  const head = address?.prefix.value ?? ''

  return head === '' ? '/' : `/${head}/`
})

/**
 * The segment as it stands in the field, in the language being edited and no other: falling
 * back on a language that has words would print an address the site does not answer at.
 */
const slug = computed(() => {
  const written = address?.values.value.slug

  if (typeof written === 'string') return written

  return (written as Record<string, string> | null | undefined)?.[locales.active.value] ?? ''
})

/** On the site at an address that is about to become a different one. */
const moving = computed(() => {
  const current = address?.path.value

  return (
    typeof current === 'string' &&
    slug.value !== '' &&
    `${prefix.value}${slug.value}` !== `/${current}`
  )
})
</script>

<template>
  <div class="wx-slug">
    <wx-input v-bind="$attrs">
      <template #prefix>
        <span class="wx-slug__prefix">{{ prefix }}</span>
      </template>
    </wx-input>

    <wx-alert v-if="moving && address" type="info" variant="soft" :description="address.moving()" />
  </div>
</template>

<style scoped>
.wx-slug {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

/*
 * One address in one face. The prefix and what is typed after it are one string; two faces on
 * one line do not sit on one line, and only the muting should say which part cannot be typed
 * over.
 */
.wx-slug__prefix,
.wx-slug :deep(.wx-input__inner) {
  font-family: var(--wx-font-family-mono);
}

.wx-slug__prefix {
  color: var(--wx-text-muted);
}
</style>
