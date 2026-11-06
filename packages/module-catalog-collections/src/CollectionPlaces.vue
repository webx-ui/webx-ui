<script setup lang="ts">
import { computed, ref } from 'vue'
import { toast, WxBadge, WxPopover, WxText } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createCollectionsApi } from './api'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'
import type { CollectionPlace } from './types'

/**
 * «Places: 3» in a row of the list, and the places behind the words (§6.4 of the collections spec).
 * Asked when it opens: the list needs only the number. Its own and small, because
 * `@webx-ui/module-blocks` is not a dependency of this package. The note is here as well as under
 * the list: this is where someone reads the places as the whole answer.
 */
defineOptions({ name: 'WxCatalogCollectionPlaces' })

const props = defineProps<{ id: number; count: number }>()

const admin = useAdmin()
const api = createCollectionsApi(admin)
const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()
useCatalogCollectionsMessages()

const places = ref<CollectionPlace[] | null>(null)
const words = computed(() => t('panel.places', { count: props.count }))

async function load(): Promise<void> {
  try {
    places.value = await api.usage(props.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

/** What the site does not show yet is the thing to say about a place; the first that holds wins. */
function mark(place: CollectionPlace): string | null {
  if (!place.live) return t('panel.place-draft')

  return place.published ? null : t('panel.place-unpublished')
}
</script>

<template>
  <!-- The row opens the collection; these words open something else. -->
  <span class="wx-catalog-collection-places" @click.stop>
    <wx-popover
      v-if="count > 0"
      :title="t('panel.places-title')"
      :width="280"
      align="start"
      @open="load"
    >
      <template #trigger>
        <button type="button" class="wx-catalog-collection-places__open">{{ words }}</button>
      </template>

      <div class="wx-catalog-collection-places__body">
        <wx-text v-if="places === null" size="sm" tone="muted">
          {{ panel('filters.loading') }}
        </wx-text>

        <ul v-else class="wx-catalog-collection-places__list">
          <li
            v-for="place in places"
            :key="`${place.model}:${place.id}`"
            class="wx-catalog-collection-places__place"
          >
            <a
              v-if="place.url"
              class="wx-catalog-collection-places__link"
              :href="place.url"
              target="_blank"
              rel="noopener"
              >{{ place.title ?? `#${place.id}` }}</a
            >
            <span v-else>{{ place.title ?? `#${place.id}` }}</span>
            <wx-badge v-if="mark(place)" type="warning" size="sm">{{ mark(place) }}</wx-badge>
          </li>
        </ul>

        <wx-text size="sm" tone="muted">{{ t('panel.places-note') }}</wx-text>
      </div>
    </wx-popover>

    <wx-text v-else size="sm" tone="muted">{{ words }}</wx-text>
  </span>
</template>

<style scoped>
.wx-catalog-collection-places__open {
  padding: 0;
  border: 0;
  background: none;
  color: var(--wx-text-link);
  font: inherit;
  font-size: var(--wx-font-size-sm);
  cursor: pointer;
}

.wx-catalog-collection-places__open:hover {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.wx-catalog-collection-places__open:focus-visible {
  border-radius: var(--wx-radius-xs);
  outline: none;
  box-shadow: var(--wx-ring-focus);
}
</style>

<!-- The panel of a popover is teleported, so what is inside it cannot be scoped to this component. -->
<style>
.wx-catalog-collection-places__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-catalog-collection-places__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  max-height: 240px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.wx-catalog-collection-places__place {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-collection-places__link {
  color: var(--wx-text-link);
  overflow-wrap: anywhere;
  text-decoration: none;
}

.wx-catalog-collection-places__link:hover {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}
</style>
