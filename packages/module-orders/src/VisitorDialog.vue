<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxBadge,
  WxButton,
  WxDialog,
  WxEmpty,
  WxInput,
  WxSkeleton,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrderCardData, OrderDetail, VisitorChoice, VisitorLinkPreview } from './types'

/**
 * «Link to a visitor» (spec §13.3): the customers found by a part of the name, the e-mail or the
 * phone — the order's own address is asked first, and the account with that address comes first —
 * and, for the one picked, what the service's dry run says: whose «My orders» the order goes to,
 * whose it leaves. Only the link changes; the contacts typed by the buyer stay in the order.
 */
const props = defineProps<{
  order: OrderCardData
  /** The buyer's address as the card holds it: the first thing searched. */
  email?: string | null
}>()

const { resolve, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const search = ref(props.email ?? '')
const found = ref<VisitorChoice[]>([])
const searching = ref(false)
const searched = ref(false)
const picked = ref<VisitorChoice | null>(null)
const plan = ref<VisitorLinkPreview | null>(null)
const applying = ref(false)
const error = ref('')

const title = computed(() =>
  t('panel.visitor-link-title', { number: props.order.number ?? `#${props.order.id}` }),
)

let timer: ReturnType<typeof setTimeout> | undefined
let asked = 0

async function find(): Promise<void> {
  const term = search.value.trim()
  const mine = ++asked

  if (term === '') {
    found.value = []
    searched.value = false

    return
  }

  searching.value = true

  try {
    const rows = await api.searchVisitors(props.order.id, term)

    if (mine === asked) {
      found.value = rows
      searched.value = true
    }
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    if (mine === asked) searching.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => void find(), 300)
})

onMounted(() => void find())

async function pick(choice: VisitorChoice): Promise<void> {
  picked.value = choice
  plan.value = null
  error.value = ''

  try {
    plan.value = await api.previewLink(props.order.id, choice.id)
  } catch (failure) {
    error.value = message(failure)
  }
}

const previous = computed(() => {
  const before = plan.value?.previous

  return before ? (before.name ?? before.email ?? `#${before.id}`) : null
})

async function apply(): Promise<void> {
  if (!picked.value || applying.value) return

  applying.value = true

  try {
    resolve(await api.link(props.order.id, picked.value.id))
  } catch (failure) {
    error.value = message(failure)
  } finally {
    applying.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="560">
    <div class="wx-orders-visitor-dialog">
      <wx-input
        v-model="search"
        :placeholder="t('panel.visitor-search')"
        clearable
        :aria-label="t('panel.visitor-search')"
      />

      <wx-skeleton v-if="searching && found.length === 0" :rows="2" />
      <wx-empty
        v-else-if="searched && found.length === 0"
        :description="t('panel.visitor-search-empty')"
      />
      <ul v-else-if="found.length > 0" class="wx-orders-visitor-dialog__list">
        <li v-for="choice in found" :key="choice.id">
          <button
            type="button"
            class="wx-orders-visitor-dialog__choice"
            :class="{ 'is-picked': picked?.id === choice.id }"
            :aria-pressed="picked?.id === choice.id"
            :disabled="choice.current"
            @click="pick(choice)"
          >
            <span class="wx-orders-visitor-dialog__name">{{ choice.name }}</span>
            <wx-text size="sm" tone="muted" class="wx-orders-visitor-dialog__meta">
              {{ [choice.email, choice.phone].filter(Boolean).join(' · ') }}
            </wx-text>
            <wx-space :size="4" wrap class="wx-orders-visitor-dialog__tags">
              <wx-badge v-if="choice.same_email" size="sm" type="success">
                {{ t('panel.visitor-same-email') }}
              </wx-badge>
              <wx-badge v-if="choice.current" size="sm">{{ t('panel.visitor-current') }}</wx-badge>
            </wx-space>
          </button>
        </li>
      </ul>

      <wx-alert v-if="error" type="danger" :description="error" />
      <wx-alert
        v-else-if="plan && plan.visitor"
        type="info"
        :description="
          [
            t('panel.visitor-link-preview', {
              name: plan.visitor.name ?? plan.visitor.email ?? '',
            }),
            previous ? t('panel.visitor-relink-preview', { name: previous }) : '',
            t('panel.visitor-no-letter'),
          ]
            .filter(Boolean)
            .join(' ')
        "
      />
      <!-- The preview's place is taken before a pick: the dialog is centred, and a preview that
           grew it would move the list from under the pointer that just picked a row. -->
      <wx-alert
        v-else-if="found.length > 0"
        type="info"
        :description="t('panel.visitor-pick-hint')"
      />
    </div>

    <template #footer>
      <wx-button @click="open = false">{{ t('panel.dismiss') }}</wx-button>
      <wx-button
        type="primary"
        :disabled="!plan || plan.outcome !== 'linked'"
        :loading="applying"
        @click="apply"
      >
        {{ t('panel.visitor-link') }}
      </wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-visitor-dialog {
  display: grid;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-visitor-dialog__list {
  display: grid;
  gap: var(--wx-space-6);
  max-height: 18rem;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.wx-orders-visitor-dialog__choice {
  display: grid;
  gap: var(--wx-space-2);
  width: 100%;
  padding: var(--wx-space-8) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-orders-visitor-dialog__choice:hover:not(:disabled) {
  border-color: var(--wx-color-primary);
}

.wx-orders-visitor-dialog__choice.is-picked {
  border-color: var(--wx-color-primary);
  box-shadow: inset 0 0 0 1px var(--wx-color-primary);
}

.wx-orders-visitor-dialog__choice:disabled {
  cursor: default;
  opacity: 0.7;
}

.wx-orders-visitor-dialog__name {
  font-weight: var(--wx-font-weight-semibold);
  overflow-wrap: anywhere;
}

.wx-orders-visitor-dialog__meta {
  overflow-wrap: anywhere;
}

.wx-orders-visitor-dialog__tags:empty {
  display: none;
}
</style>
