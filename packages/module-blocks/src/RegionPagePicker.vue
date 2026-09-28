<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  createLinksApi,
  emptyLink,
  useAdmin,
  useI18n,
  useTranslate,
  WxLinkPicker,
  type LinkValue,
} from '@webx-ui/module-admin'
import { WxButton, WxIcon, WxPopover } from '@webx-ui/core'
import { useBlocksMessages } from './i18n'
import { sitePath } from './region'

/**
 * Which page of the site the region is looked at on.
 *
 * A header is judged against the page under it, so the preview is a real address of the site
 * and this picks which one. The choice goes through the panel's own link picker — the same
 * "what can be pointed at" every link field uses — and what comes out is a path: the preview
 * needs where the page is, not which entity it is.
 */
defineOptions({ name: 'WxRegionPagePicker' })

export interface RegionPlace {
  /** The site path the preview draws, `/` for the home page. */
  path: string
  /** What was chosen, so reopening the picker shows it chosen. `null` — the home page. */
  link: LinkValue | null
}

const place = defineModel<RegionPlace>({ required: true })

const admin = useAdmin()
const i18n = useI18n()
const links = createLinksApi(admin)
useBlocksMessages()

const t = useTranslate('webx-blocks')

const open = ref(false)

/** Starts on an address rather than a section: "/" is the one place worth typing. */
const link = computed<LinkValue>(() => place.value.link ?? { ...emptyLink(), target: 'url' })

const label = computed(() => (place.value.path === '/' ? t('region.home') : place.value.path))

/**
 * The link as the picker changes it, turned into a path once there is one.
 *
 * Half-made values are kept but not applied — a section picked and nothing searched yet — so
 * the preview does not jump to nowhere while somebody is still choosing.
 */
async function change(value: LinkValue | null): Promise<void> {
  if (value === null) return

  if (value.target === 'url') {
    place.value = { path: sitePath(value.url) ?? place.value.path, link: value }

    return
  }

  if (value.target === 'entity' && value.entity_type !== null && value.entity_id !== null) {
    const [resolved] = await links.resolve(
      [{ type: value.entity_type, id: value.entity_id }],
      i18n.state.locale,
    )

    place.value = { path: sitePath(resolved?.url ?? null) ?? place.value.path, link: value }

    return
  }

  place.value = { ...place.value, link: value }
}

function home(): void {
  place.value = { path: '/', link: null }
  open.value = false
}
</script>

<template>
  <wx-popover
    v-model:open="open"
    :title="t('region.choose-page')"
    :width="380"
    side="bottom"
    align="end"
    teleport
  >
    <template #trigger>
      <wx-button size="sm" variant="outline" class="wx-region-page-picker__trigger">
        <wx-icon name="eye" />
        <span class="wx-region-page-picker__words">
          {{ t('region.shown-on') }}:
          <strong>{{ label }}</strong>
        </span>
        <wx-icon name="chevron-down" />
      </wx-button>
    </template>

    <div class="wx-region-page-picker">
      <wx-link-picker
        :model-value="link"
        :allow-none="false"
        :attributes="false"
        @update:model-value="change"
      />
      <wx-button size="sm" variant="text" :disabled="place.path === '/'" @click="home">
        <wx-icon name="home" />
        {{ t('region.back-home') }}
      </wx-button>
    </div>
  </wx-popover>
</template>

<style scoped>
.wx-region-page-picker {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: var(--wx-space-12);
}

.wx-region-page-picker > :deep(.wx-button) {
  align-self: flex-start;
}

/* The path can be long; the button stays one line and the path is what gets cut. */
.wx-region-page-picker__words {
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
