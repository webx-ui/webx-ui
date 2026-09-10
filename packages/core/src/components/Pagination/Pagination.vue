<script setup lang="ts">
import { computed } from 'vue'
import type { PaginationEmits, PaginationProps } from './types'

defineOptions({ name: 'WxPagination' })

const props = withDefaults(defineProps<PaginationProps>(), {
  paginator: null,
  total: undefined,
  lastPage: undefined,
  siblings: 1,
  perPageOptions: () => [],
  showTotal: true,
  disabled: false,
  size: 'md',
  ariaLabel: 'Pagination',
})

const emit = defineEmits<PaginationEmits>()

/**
 * Both models fall back to the paginator, so `:paginator="page"` alone is a working
 * component and `v-model:page` is there for state the caller would rather own.
 */
const page = defineModel<number | undefined>('page', { default: undefined })
const perPage = defineModel<number | undefined>('perPage', { default: undefined })

const currentPerPage = computed(() => perPage.value ?? props.paginator?.per_page ?? 15)
const total = computed(() => props.total ?? props.paginator?.total ?? 0)

const lastPage = computed(() => {
  if (props.lastPage !== undefined) return Math.max(1, props.lastPage)
  if (props.paginator) return Math.max(1, props.paginator.last_page)
  return Math.max(1, Math.ceil(total.value / currentPerPage.value))
})

/**
 * Clamped to what exists. A remembered page can outlive the result it belonged to —
 * a filter narrows eleven pages down to two — and a pagination with no page marked
 * current tells the reader they are nowhere.
 */
const currentPage = computed(() => {
  const asked = page.value ?? props.paginator?.current_page ?? 1
  return Math.min(Math.max(1, asked), lastPage.value)
})

/** Laravel counts these for us; without it they follow from the page and its size. */
const from = computed(() => {
  if (props.paginator) return props.paginator.from
  return total.value === 0 ? null : (currentPage.value - 1) * currentPerPage.value + 1
})

const to = computed(() => {
  if (props.paginator) return props.paginator.to
  return total.value === 0 ? null : Math.min(total.value, currentPage.value * currentPerPage.value)
})

type PageItem = { type: 'page'; value: number } | { type: 'gap'; value: string }

/**
 * First and last are always reachable, the current page keeps `siblings` neighbours,
 * and what is skipped becomes an ellipsis. A gap of exactly one page is spelled out
 * instead — an ellipsis hiding a single number helps nobody.
 */
const items = computed<PageItem[]>(() => {
  const last = lastPage.value
  const current = currentPage.value
  const span = props.siblings

  const wanted = new Set<number>([1, last])
  for (let i = current - span; i <= current + span; i++) {
    if (i >= 1 && i <= last) wanted.add(i)
  }

  const numbers = [...wanted].sort((a, b) => a - b)
  const result: PageItem[] = []
  let previous = 0

  for (const value of numbers) {
    const distance = value - previous
    if (previous && distance === 2) result.push({ type: 'page', value: value - 1 })
    else if (previous && distance > 2) result.push({ type: 'gap', value: `gap-${value}` })
    result.push({ type: 'page', value })
    previous = value
  }

  return result
})

const classes = computed(() => [
  'wx-pagination',
  `wx-pagination--${props.size}`,
  { 'is-disabled': props.disabled },
])

function go(next: number) {
  const target = Math.min(Math.max(1, next), lastPage.value)
  if (props.disabled || target === currentPage.value) return
  page.value = target
  emit('change', { page: target, perPage: currentPerPage.value })
}

/**
 * A bigger page can put the current position past the end, so the size change also
 * moves back to the first page — the alternative is asking for a page that is not there.
 */
function setPerPage(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  if (!Number.isFinite(value) || value === currentPerPage.value) return
  perPage.value = value
  page.value = 1
  emit('change', { page: 1, perPage: value })
}
</script>

<template>
  <nav :class="classes" :aria-label="ariaLabel">
    <p v-if="showTotal" class="wx-pagination__total">
      <slot name="total" :from="from" :to="to" :total="total">
        <template v-if="from === null">Nothing to show</template>
        <template v-else>{{ from }}&ndash;{{ to }} of {{ total }}</template>
      </slot>
    </p>

    <div class="wx-pagination__controls">
      <label v-if="perPageOptions.length" class="wx-pagination__per-page">
        <span class="wx-pagination__per-page-label">Per page</span>
        <select
          class="wx-pagination__select"
          :value="currentPerPage"
          :disabled="disabled"
          @change="setPerPage"
        >
          <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </label>

      <ul class="wx-pagination__list">
        <li>
          <button
            class="wx-pagination__button wx-pagination__button--arrow"
            type="button"
            :disabled="disabled || currentPage <= 1"
            aria-label="Previous page"
            @click="go(currentPage - 1)"
          >
            <svg viewBox="0 0 8 12" aria-hidden="true"><path d="M6.5 1 1.5 6l5 5" /></svg>
          </button>
        </li>

        <li v-for="item in items" :key="item.value">
          <span v-if="item.type === 'gap'" class="wx-pagination__gap" aria-hidden="true"
            >&hellip;</span
          >
          <button
            v-else
            class="wx-pagination__button"
            type="button"
            :class="{ 'is-current': item.value === currentPage }"
            :disabled="disabled"
            :aria-current="item.value === currentPage ? 'page' : undefined"
            :aria-label="`Page ${item.value}`"
            @click="go(item.value)"
          >
            {{ item.value }}
          </button>
        </li>

        <li>
          <button
            class="wx-pagination__button wx-pagination__button--arrow"
            type="button"
            :disabled="disabled || currentPage >= lastPage"
            aria-label="Next page"
            @click="go(currentPage + 1)"
          >
            <svg viewBox="0 0 8 12" aria-hidden="true"><path d="M1.5 1 6.5 6l-5 5" /></svg>
          </button>
        </li>
      </ul>
    </div>
  </nav>
</template>

<style scoped>
.wx-pagination {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-pagination__total {
  margin: 0;
  padding: 0;
}

.wx-pagination__controls {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  margin-left: auto;
}

.wx-pagination__per-page {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-pagination__select {
  height: var(--wx-pagination-size, 34px);
  padding: 0 var(--wx-space-8);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font: inherit;
  cursor: pointer;
}

.wx-pagination__select:focus-visible {
  outline: none;
  border-color: var(--wx-border-focus);
  box-shadow: var(--wx-ring-focus);
}

/*
 * The same explicit resets the table needs. A host stylesheet that puts space between
 * consecutive list items — VitePress adds 8px, and every CSS framework has a rule of
 * its own — lands inside the row of buttons and steps them down one by one, because
 * the margin arrives on all but the first item.
 */
.wx-pagination__list {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  border: 0;
  list-style: none;
}

.wx-pagination__list li {
  display: flex;
  align-items: center;
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-pagination__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: var(--wx-pagination-size, 34px);
  height: var(--wx-pagination-size, 34px);
  padding: 0 var(--wx-space-8);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font: inherit;
  cursor: pointer;
  transition:
    background var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-pagination--sm {
  --wx-pagination-size: 28px;
}

.wx-pagination--lg {
  --wx-pagination-size: 42px;
}

.wx-pagination__button:hover:not(:disabled):not(.is-current) {
  border-color: var(--wx-border-strong);
  background: var(--wx-bg-fill);
}

.wx-pagination__button:focus-visible {
  outline: none;
  border-color: var(--wx-border-focus);
  box-shadow: var(--wx-ring-focus);
}

.wx-pagination__button.is-current {
  background: var(--wx-color-primary);
  border-color: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
  cursor: default;
}

.wx-pagination__button:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-pagination__button--arrow svg {
  width: 8px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.wx-pagination__gap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: var(--wx-space-24);
  color: var(--wx-text-muted);
}
</style>
