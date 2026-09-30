<script setup lang="ts">
import { computed, onMounted, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'

/**
 * The menu, built from the manifest rather than written out. What the panel offers is what the
 * installation actually has — adding a module on the server and installing its front end is
 * the whole of "adding a section".
 *
 * Sections come first, then the groups the server declared — "System" for what keeps the
 * panel running — each as a branch that opens on its own when a section under it is current.
 *
 * A group names its own icon; the gear is what it falls back to, because that is the picture
 * every group had before groups could carry one. Inside a group, the entries under no caption
 * come first and each caption follows with its own — a caption adds no depth, and on the narrow
 * rail it is a rule.
 */
defineProps<{ collapsed?: boolean }>()

const emit = defineEmits<{ select: [] }>()

const admin = useAdmin()
const t = useTranslate('webx-admin')
const router = useRouter()
const route = useRoute()

const current = computed<string>({
  /*
   * The longest path that contains this one, on a segment boundary: a section may live inside
   * another's path (`/services/categories` under `/services`), and the first match in the order
   * of the menu would light up the outer one.
   */
  get: () => {
    const match = admin.nav.value
      .filter((entry) => route.path === entry.path || route.path.startsWith(`${entry.path}/`))
      .sort((a, b) => b.path.length - a.path.length)[0]

    return match?.id ?? ''
  },
  set: (id) => {
    const entry = admin.nav.value.find((candidate) => candidate.id === id)

    if (entry !== undefined) {
      void router.push(entry.path)
    }
  },
})

/*
 * A long menu scrolls on its own, and a reload puts it back at the top — with the current
 * section, often in a branch near the foot, out of sight. Once, on arrival: the menu comes with
 * the manifest, so that is when there first is a current entry, and its branch opens with a
 * transition, so the row is only where it will stay once that has run. Afterwards the user
 * clicked the entry, so it is already where they are looking.
 */
const root = useTemplateRef<{ $el: Element | null }>('root')

let revealed = false

async function reveal(): Promise<void> {
  const el = root.value?.$el

  if (revealed || current.value === '' || !(el instanceof HTMLElement)) return
  revealed = true
  await Promise.allSettled((el.getAnimations?.({ subtree: true }) ?? []).map((a) => a.finished))
  el.querySelector('.wx-menu-row.is-active')?.scrollIntoView?.({ block: 'center' })
}

onMounted(reveal)
watch(current, reveal, { flush: 'post' })
</script>

<template>
  <wx-menu
    ref="root"
    v-model="current"
    :collapsed="collapsed"
    :label="t('nav.sections')"
    @select="emit('select')"
  >
    <wx-menu-item
      v-for="entry in admin.groups.value.top"
      :key="entry.id"
      :value="entry.id"
      :icon="entry.icon ?? undefined"
      :label="entry.title"
    />

    <wx-submenu
      v-for="group in admin.groups.value.groups"
      :key="group.id"
      :value="`group:${group.id}`"
      :title="group.title"
      :icon="group.icon ?? 'gear'"
    >
      <wx-menu-item
        v-for="entry in group.entries"
        :key="entry.id"
        :value="entry.id"
        :icon="entry.icon ?? undefined"
        :label="entry.title"
      />
      <wx-menu-group v-for="section in group.sections" :key="section.id" :title="section.title">
        <wx-menu-item
          v-for="entry in section.entries"
          :key="entry.id"
          :value="entry.id"
          :icon="entry.icon ?? undefined"
          :label="entry.title"
        />
      </wx-menu-group>
    </wx-submenu>
  </wx-menu>
</template>
