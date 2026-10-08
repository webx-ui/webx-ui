<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxSkeleton,
  WxSpace,
  WxSwitch,
  WxText,
} from '@webx-ui/core'
import { createBlocksApi } from './api'
import { useBlocksMessages } from './i18n'
import { kindOf } from './schema'
import type { BlockPack, BlockType } from './types'

/**
 * "Export" (§17.1): the types to take, ticked, and one file out — the command's documents in a
 * pack. The components the chosen types call are not offered as a choice: the server adds them,
 * because a block without them draws nothing on the site it lands on, and a person exporting a
 * block for another site would only find that out there.
 *
 * Opened from a type's editor, it comes with that type ticked: a block for the catalogue of
 * ready blocks is made one at a time.
 */
const props = defineProps<{ selected?: string[] }>()

const { resolve, dismiss, open } = useModal<true>()

const context = useAdmin()
const api = createBlocksApi(context)
useBlocksMessages()

const t = useTranslate('webx-blocks')
const message = useErrorText()

const types = ref<BlockType[]>([])
const chosen = ref<string[]>([...(props.selected ?? [])])
const drafts = ref(false)
const loading = ref(true)
const exporting = ref(false)

/** Blocks first, then components, each in the list's order — the order the section shows. */
const ordered = computed(() => [
  ...types.value.filter((type) => kindOf(type) === 'block'),
  ...types.value.filter((type) => kindOf(type) === 'component'),
])

const all = computed(
  () => ordered.value.length > 0 && ordered.value.every((type) => chosen.value.includes(type.slug)),
)
const some = computed(() => chosen.value.length > 0 && !all.value)

function toggleAll(on: boolean): void {
  chosen.value = on ? ordered.value.map((type) => type.slug) : []
}

function toggle(slug: string, on: boolean): void {
  chosen.value = on ? [...chosen.value, slug] : chosen.value.filter((one) => one !== slug)
}

/** What a type would give: the published version, or the draft only when drafts are asked for. */
function versionOf(type: BlockType): string {
  const version = drafts.value ? (type.draft ?? type.published) : type.published

  return version === null ? t('page.never-published') : `v${version.number}`
}

/** One type's file is named after it; several are named after the day they left. */
function fileName(): string {
  if (chosen.value.length === 1) return `${chosen.value[0]}.blocks.json`

  return `blocks-${new Date().toISOString().slice(0, 10)}.json`
}

function download(pack: BlockPack, name: string): void {
  const url = URL.createObjectURL(
    new Blob([`${JSON.stringify(pack, null, 2)}\n`], { type: 'application/json' }),
  )
  const link = document.createElement('a')

  link.href = url
  link.download = name
  link.click()
  // After the click has been handled: revoking first would hand the browser a dead address.
  setTimeout(() => URL.revokeObjectURL(url), 0)
}

async function run(): Promise<void> {
  exporting.value = true

  try {
    const { pack, skipped, missing } = await api.exportPack(chosen.value, { draft: drafts.value })

    if (pack.blocks.length === 0) {
      toast.warning(t('exchange.nothing'))

      return
    }

    download(pack, fileName())
    toast.success(t('exchange.exported', { count: pack.blocks.length }))

    if (skipped.length > 0) toast.warning(t('exchange.skipped', { list: skipped.join(', ') }))
    if (missing.length > 0) toast.warning(t('exchange.missing', { list: missing.join(', ') }))

    resolve(true)
  } catch (error) {
    toast.danger(message(error, t('exchange.export-failed')))
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  try {
    types.value = await api.list()
    // A type that was never published has nothing to give but its draft: opened on one, the
    // switch starts on, or the file would come out empty.
    drafts.value = types.value.some(
      (type) => chosen.value.includes(type.slug) && type.published === null,
    )
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('exchange.export-title')" :width="560">
    <div class="wx-block-export">
      <wx-text tone="muted" size="sm">{{ t('exchange.export-help') }}</wx-text>

      <wx-skeleton v-if="loading" :rows="5" />

      <template v-else>
        <wx-checkbox
          :model-value="all"
          :indeterminate="some"
          :label="t('exchange.select-all')"
          class="wx-block-export__all"
          @update:model-value="toggleAll(Boolean($event))"
        />

        <ul class="wx-block-export__list">
          <li v-for="type in ordered" :key="type.id" class="wx-block-export__row">
            <wx-checkbox
              :model-value="chosen.includes(type.slug)"
              @update:model-value="toggle(type.slug, Boolean($event))"
            >
              <span class="wx-block-export__title">{{ type.title }}</span>
              <span class="wx-block-export__meta">
                {{ type.slug }}
                <template v-if="kindOf(type) === 'component'">
                  · {{ t('components.kind-component') }}</template
                >
              </span>
            </wx-checkbox>
            <span class="wx-block-export__version">{{ versionOf(type) }}</span>
          </li>
        </ul>

        <div class="wx-block-export__drafts">
          <wx-switch v-model="drafts" :label="t('exchange.drafts')" />
          <wx-text tone="muted" size="sm">{{ t('exchange.drafts-help') }}</wx-text>
        </div>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button
          type="primary"
          icon="download"
          :loading="exporting"
          :disabled="chosen.length === 0"
          @click="run"
        >
          {{ t('exchange.download') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-block-export {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-block-export__list {
  display: flex;
  flex-direction: column;
  max-height: 320px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.wx-block-export__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  padding: var(--wx-space-8) var(--wx-space-12);
}

.wx-block-export__row + .wx-block-export__row {
  border-top: 1px solid var(--wx-border-default);
}

.wx-block-export__title {
  margin-right: var(--wx-space-6);
}

.wx-block-export__meta,
.wx-block-export__version {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-block-export__version {
  flex-shrink: 0;
}

.wx-block-export__drafts {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}
</style>
