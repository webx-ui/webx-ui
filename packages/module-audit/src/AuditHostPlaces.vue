<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxLink, WxSkeleton, WxText } from '@webx-ui/core'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditHosts } from './types'

/**
 * Where one host stands, under its row on «Outgoing»: the pages that link to it with the anchor
 * and what it answered, and the fields of the database that hold it, with «Open in the editor».
 * Fifty of each — enough to see the pattern, and the findings list the rest.
 */
const props = defineProps<{ host: string }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()
const router = useRouter()

const places = ref<AuditHosts | null>(null)
const failure = ref<string | null>(null)

onMounted(async () => {
  try {
    places.value = await api.hosts({ host: props.host })
  } catch (error) {
    failure.value = message(error)
  }
})
</script>

<template>
  <div class="wx-audit-places">
    <wx-text v-if="failure" size="sm" tone="danger">{{ failure }}</wx-text>
    <wx-skeleton v-else-if="!places" :rows="2" />

    <template v-else>
      <section v-if="places.pages?.length" class="wx-audit-places__group">
        <wx-text size="sm" weight="semibold">{{ t('page.host-pages') }}</wx-text>
        <div v-for="(place, index) in places.pages" :key="index" class="wx-audit-places__row">
          <wx-link :href="place.page" target="_blank" class="wx-audit-places__url">{{
            place.page
          }}</wx-link>
          <wx-text size="sm" tone="muted" class="wx-audit-places__url"
            >{{ place.kind }} → {{ place.url }}</wx-text
          >
          <wx-text v-if="place.anchor" size="sm">«{{ place.anchor }}»</wx-text>
          <wx-badge v-if="place.status && place.status >= 400" type="danger" size="sm">{{
            place.status
          }}</wx-badge>
        </div>
      </section>

      <section v-if="places.fields?.length" class="wx-audit-places__group">
        <wx-text size="sm" weight="semibold">{{ t('page.host-fields') }}</wx-text>
        <div v-for="(field, index) in places.fields" :key="index" class="wx-audit-places__row">
          <wx-text size="sm">{{ field.record_label }}</wx-text>
          <wx-text size="sm" tone="muted"
            >{{ field.source }} · {{ field.field
            }}{{ field.locale ? ` · ${field.locale}` : '' }}</wx-text
          >
          <wx-badge v-if="!field.published" size="sm">{{ t('page.unpublished') }}</wx-badge>
          <wx-text size="sm" tone="muted" class="wx-audit-places__url">{{ field.url }}</wx-text>
          <wx-button
            v-if="field.edit_url"
            size="sm"
            variant="text"
            icon="edit"
            @click="router.push(field.edit_url)"
            >{{ t('page.open-editor') }}</wx-button
          >
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.wx-audit-places {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-audit-places__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-places__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}

.wx-audit-places__url {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
