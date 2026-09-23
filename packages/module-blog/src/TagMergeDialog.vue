<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  useModal,
  WxAlert,
  WxBadge,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { TagMerged, TagRow } from './types'

/**
 * Several tags into one (§6).
 *
 * The dialog asks the one question the selection cannot answer — which of them stays — and says
 * the two things about the operation that an editor has to know before pressing it: how many
 * articles change hands, and that nothing here can be undone. The pivot rows that go are gone,
 * and putting a tag back would not put back which articles carried it.
 *
 * The checkbox is the other half. The aliases of `webx-ui/routing` die with the tag — they are
 * rows keyed to the entity — so an address that is to survive the merge has to survive as
 * something that is not tied to it, which is a redirect. That is a permanent fact about the
 * site, and a tag made by mistake this morning does not deserve one, so it is asked rather than
 * done quietly.
 */
const props = defineProps<{ tags: TagRow[] }>()

const { open, resolve, dismiss } = useModal<TagMerged>()

const context = useAdmin()
const api = createBlogApi(context)
useBlogMessages()

const t = useTranslate('webx-blog')
const message = useErrorText()

/* The most used one by default: merging the big tag into the typo is the mistake this dialog
   exists to make hard, and the default is where that is decided. */
const keep = ref<number>(
  [...props.tags].sort((one, other) => other.articles_count - one.articles_count)[0]?.id ?? 0,
)

const working = ref(false)
const redirect = ref(true)
const error = ref<string | null>(null)

const options = computed(() =>
  props.tags.map((tag) => ({ value: tag.id, label: `${tag.title} · ${tag.articles_count}` })),
)

const survivor = computed(() => props.tags.find((tag) => tag.id === keep.value) ?? null)

const going = computed(() => props.tags.filter((tag) => tag.id !== keep.value))

/**
 * How many articles the merge touches, at worst.
 *
 * At worst because an article carrying two of the tags counts once after the merge and twice
 * here — the pivot's unique index is what makes it one — and a number that cannot be wrong in
 * the other direction is the honest one to show before pressing anything.
 */
const affected = computed(() => going.value.reduce((sum, tag) => sum + tag.articles_count, 0))

const addresses = computed(() => going.value.map((tag) => tag.path).filter(Boolean) as string[])

async function submit(): Promise<void> {
  if (survivor.value === null || going.value.length === 0 || working.value) return

  working.value = true
  error.value = null

  try {
    resolve(
      await api.mergeTags(
        going.value.map((tag) => tag.id),
        keep.value,
        redirect.value,
      ),
    )
  } catch (failure) {
    error.value = message(failure)
  } finally {
    working.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('tag.merge-title')" :width="520">
    <div class="wx-tag-merge">
      <!-- What is going where, before anything is asked: the chips are the selection as the
           screen had it, and the one on the right is the word that survives. -->
      <div class="wx-tag-merge__chips">
        <wx-badge v-for="tag in going" :key="tag.id" size="sm" round>
          {{ tag.title }} · {{ tag.articles_count }}
        </wx-badge>
        <wx-icon name="arrow-right" size="sm" />
        <wx-badge v-if="survivor" type="primary" size="sm" round>
          {{ survivor.title }} · {{ survivor.articles_count }}
        </wx-badge>
      </div>

      <wx-form-item :label="t('tag.merge-keep')">
        <wx-select v-model="keep" :options="options" :aria-label="t('tag.merge-keep')" />
      </wx-form-item>

      <div class="wx-tag-merge__redirect">
        <wx-checkbox v-model="redirect" :label="t('tag.merge-redirect')" />
        <wx-text size="sm" tone="muted">
          {{
            addresses.length === 0
              ? t('tag.merge-redirect-none')
              : t('tag.merge-redirect-help', { addresses: addresses.join(', ') })
          }}
        </wx-text>
      </div>

      <wx-alert
        type="warning"
        variant="soft"
        :description="
          t('tag.merge-warning', {
            count: affected,
            tag: survivor?.title ?? '',
            merged: going.length,
          })
        "
      />

      <wx-alert v-if="error" type="danger" variant="soft" :description="error" />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('tag.cancel') }}</wx-button>
        <wx-button type="primary" :loading="working" :disabled="going.length === 0" @click="submit">
          {{ t('tag.merge-confirm') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-tag-merge {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-tag-merge__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-tag-merge__redirect {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}
</style>
