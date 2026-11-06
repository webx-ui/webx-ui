<script setup lang="ts">
import { computed, ref } from 'vue'
import { confirm, toast, WxAlert, WxButton, WxInput, WxText } from '@webx-ui/core'
import { useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { copyAddress } from './clipboard'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'

/**
 * `wx-catalog-feed-address`: where a marketplace reads the file, and «Reissue the key» beside it.
 * The old address stops the moment the key changes, and the new one goes into the marketplace by
 * hand, so the field asks first and reminds after (§11 of the feeds spec).
 *
 * The address carries the key, so whoever holds only `catalog.view` is sent `null` and sees why.
 */
defineOptions({ name: 'WxCatalogFeedAddress' })

const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const feed = computed(() => editor.feed.value)
const reissued = ref(false)
const reissuing = ref(false)

async function reissue(): Promise<void> {
  const row = feed.value

  if (!row) return

  const agreed = await confirm({
    title: t('panel.reissue-title'),
    message: t('panel.reissue-text'),
    confirmText: t('panel.reissue'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  reissuing.value = true

  try {
    editor.took(await editor.api.reissueKey(row.id))
    reissued.value = true
    toast.success(t('panel.reissued'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    reissuing.value = false
  }
}
</script>

<template>
  <div class="wx-catalog-feed-address">
    <wx-text v-if="!feed" size="sm" tone="muted">{{ t('panel.address-after-save') }}</wx-text>

    <wx-text v-else-if="feed.address === null" size="sm" tone="muted">
      {{ t('panel.address-hidden') }}
    </wx-text>

    <template v-else>
      <div class="wx-catalog-feed-address__row">
        <wx-input
          class="wx-catalog-feed-address__value"
          :model-value="feed.address"
          readonly
          :aria-label="t('panel.address')"
        />
        <wx-button
          variant="outline"
          icon="copy"
          @click="copyAddress(feed.address ?? '', t('panel.address-copied'))"
        >
          {{ t('panel.copy') }}
        </wx-button>
        <wx-button
          variant="outline"
          icon="refresh"
          :loading="reissuing"
          :disabled="editor.locked.value"
          @click="reissue"
        >
          {{ t('panel.reissue') }}
        </wx-button>
      </div>

      <wx-text size="sm" tone="muted">
        {{ t('panel.key-issued') }} <wx-date :value="feed.key_issued_at" />
      </wx-text>

      <wx-alert
        v-if="reissued"
        type="warning"
        variant="soft"
        :description="t('panel.reissued-reminder')"
      />
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-feed-address {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-feed-address__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-catalog-feed-address__value {
  flex: 1 1 280px;
  min-width: 0;
}
</style>
