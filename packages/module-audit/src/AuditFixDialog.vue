<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxDialog, WxSkeleton, WxText, toast } from '@webx-ui/core'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditFixOffer, AuditIssue } from './types'

/**
 * The fix button's dialog (§8, decision 4): the fixes that can close one finding, each with what
 * it would change — the records and fields with their counts, or a setting before and after —
 * and "Apply" under each. Nothing changes until it is pressed; the finding then waits for the
 * next run, which is the only thing that says it is gone.
 */
const props = defineProps<{ run: number; issue: AuditIssue }>()
const emit = defineEmits<{ fixed: [fix: string] }>()
const open = defineModel<boolean>('open', { default: false })

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()
const router = useRouter()

const offers = ref<AuditFixOffer[] | null>(null)
const failure = ref<string | null>(null)
const applying = ref<string | null>(null)

async function load(): Promise<void> {
  offers.value = null
  failure.value = null

  try {
    offers.value = await api.fixes(props.run, props.issue.id)
  } catch (error) {
    failure.value = message(error)
  }
}

async function apply(offer: AuditFixOffer): Promise<void> {
  applying.value = offer.id

  try {
    await api.fix(props.run, props.issue.id, offer.id)
    toast.success(t('page.fix-applied'))
    emit('fixed', offer.id)
    open.value = false
  } catch (error) {
    toast.danger(message(error))
  } finally {
    applying.value = null
  }
}

function edit(url: string): void {
  open.value = false
  void router.push(url)
}

// Immediate: the list mounts the dialog already open, the first time a button is pressed.
watch(
  [open, () => props.issue.id],
  ([now]) => {
    if (now) void load()
  },
  { immediate: true },
)
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.fix-button')" :width="640">
    <div class="wx-audit-fix">
      <wx-text v-if="failure" size="sm" tone="danger">{{ failure }}</wx-text>
      <wx-skeleton v-else-if="!offers" :rows="3" />
      <wx-text v-else-if="offers.length === 0" size="sm" tone="muted">{{
        t('page.fix-none')
      }}</wx-text>

      <section v-for="offer in offers ?? []" :key="offer.id" class="wx-audit-fix__offer">
        <wx-text weight="semibold">{{ offer.title }}</wx-text>
        <wx-text v-if="offer.description" size="sm" tone="muted">{{ offer.description }}</wx-text>

        <table class="wx-audit-fix__changes">
          <thead>
            <tr>
              <th>{{ t('page.fix-changes') }}</th>
              <th>{{ t('page.fix-before') }}</th>
              <th>{{ t('page.fix-after') }}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(change, index) in offer.changes" :key="index">
              <td>
                <div>{{ change.label }}</div>
                <div v-if="change.field" class="wx-audit-fix__field">{{ change.field }}</div>
              </td>
              <td class="wx-audit-fix__value">
                {{ change.before ?? '' }}
                <span v-if="change.count" class="wx-audit-fix__count">× {{ change.count }}</span>
              </td>
              <td class="wx-audit-fix__value">{{ change.after ?? '' }}</td>
              <td>
                <wx-button
                  v-if="change.edit_url"
                  size="sm"
                  variant="text"
                  icon="edit"
                  :aria-label="t('page.open-editor')"
                  @click="edit(change.edit_url)"
                />
              </td>
            </tr>
          </tbody>
        </table>

        <wx-text v-if="offer.note" size="sm" tone="muted">{{ offer.note }}</wx-text>

        <div class="wx-audit-fix__actions">
          <wx-button
            type="primary"
            size="sm"
            :loading="applying === offer.id"
            :disabled="applying !== null"
            @click="apply(offer)"
            >{{ t('page.fix-apply') }}</wx-button
          >
        </div>
      </section>
    </div>
  </wx-dialog>
</template>

<style scoped>
.wx-audit-fix {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-audit-fix__offer {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-fix__changes {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-fix__changes th {
  padding: var(--wx-space-4) var(--wx-space-8);
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-medium);
  text-align: start;
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-audit-fix__changes td {
  padding: var(--wx-space-6) var(--wx-space-8);
  vertical-align: top;
  border-bottom: 1px solid var(--wx-border-default);
  overflow-wrap: anywhere;
}

.wx-audit-fix__field,
.wx-audit-fix__count {
  color: var(--wx-text-muted);
}

.wx-audit-fix__value {
  white-space: pre-line;
}

.wx-audit-fix__actions {
  display: flex;
  justify-content: flex-end;
}
</style>
