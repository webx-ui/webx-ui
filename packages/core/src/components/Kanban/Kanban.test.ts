import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxKanban from './Kanban.vue'
import type { KanbanColumn } from './types'

interface Task {
  id: string
  title: string
  [key: string]: unknown
}

function board(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  const columns: KanbanColumn<Task>[] = [
    {
      id: 'todo',
      title: 'To do',
      items: [
        { id: 'a', title: 'Write the brief' },
        { id: 'b', title: 'Collect the assets' },
      ],
    },
    { id: 'doing', title: 'In progress', limit: 2, items: [{ id: 'c', title: 'Draft the page' }] },
    { id: 'done', title: 'Done', tone: 'success', items: [] },
  ]

  return mount(WxKanban, {
    props: { columns, ...props },
    slots,
    attachTo: document.body,
  })
}

function cards(wrapper: ReturnType<typeof board>, column: string) {
  return wrapper
    .get(`[data-column-id="${column}"]`)
    .findAll('.wx-kanban__card')
    .map((card) => card.attributes('data-card-id'))
}

function cardAt(wrapper: ReturnType<typeof board>, id: string) {
  return wrapper.get(`[data-card-id="${id}"]`)
}

describe('WxKanban', () => {
  it('draws a column per entry, with its count', () => {
    const wrapper = board()

    const columns = wrapper.findAll('.wx-kanban__column')
    expect(columns).toHaveLength(3)
    expect(columns[0].get('.wx-kanban__title').text()).toBe('To do')
    expect(columns[0].get('.wx-kanban__count').text()).toBe('2')
    expect(cards(wrapper, 'todo')).toEqual(['a', 'b'])
  })

  it('shows a limit as part of the count and marks the column that reached it', async () => {
    const wrapper = board()

    expect(wrapper.findAll('.wx-kanban__count')[1].text()).toBe('1/2')

    // One more card and the column is full.
    const columns = wrapper.props('columns') as KanbanColumn<Task>[]
    columns[1].items.push({ id: 'd', title: 'Review' })
    await nextTick()

    const count = wrapper.findAll('.wx-kanban__count')[1]
    expect(count.text()).toBe('2/2')
    expect(count.classes()).toContain('wx-badge--danger')
  })

  it('renders cards through the slot, and falls back to their title', () => {
    const plain = board()
    expect(plain.get('[data-card-id="a"]').text()).toContain('Write the brief')

    const custom = board(
      {},
      { card: '<template #card="{ card }"><b>{{ card.title }}</b></template>' },
    )
    expect(custom.get('[data-card-id="a"] b').text()).toBe('Write the brief')
  })

  it('says the column is empty, and offers to add to it', async () => {
    const wrapper = board({ addable: true })

    expect(wrapper.findAll('.wx-kanban__empty')).toHaveLength(1)
    expect(wrapper.get('.wx-kanban__empty').text()).toBe('Nothing here yet')

    await wrapper.findAll('.wx-kanban__add')[0].trigger('click')
    expect((wrapper.emitted('add')?.[0][0] as KanbanColumn<Task>).id).toBe('todo')
  })

  /* The pointer drag belongs to SortableJS, which jsdom cannot run — this is ours. */
  describe('moving a card with the keyboard', () => {
    it('picks a card up, moves it down its column and drops it', async () => {
      const wrapper = board()
      const card = cardAt(wrapper, 'a')

      await card.trigger('keydown', { key: ' ' })
      expect(wrapper.get('[data-card-id="a"]').attributes('aria-pressed')).toBe('true')
      expect(wrapper.get('.wx-kanban__live').text()).toContain('Picked up Write the brief')

      await cardAt(wrapper, 'a').trigger('keydown', { key: 'ArrowDown' })

      expect(cards(wrapper, 'todo')).toEqual(['b', 'a'])
      const move = wrapper.emitted('move')?.at(-1)?.[0] as Record<string, unknown>
      expect(move).toMatchObject({
        from: { column: 'todo', index: 0 },
        to: { column: 'todo', index: 1 },
        via: 'keyboard',
      })

      await cardAt(wrapper, 'a').trigger('keydown', { key: ' ' })
      expect(wrapper.get('[data-card-id="a"]').attributes('aria-pressed')).toBe('false')
      expect(wrapper.get('.wx-kanban__live').text()).toContain('Dropped Write the brief in To do')
    })

    it('carries a card into the next column', async () => {
      const wrapper = board()

      await cardAt(wrapper, 'a').trigger('keydown', { key: ' ' })
      await cardAt(wrapper, 'a').trigger('keydown', { key: 'ArrowRight' })

      expect(cards(wrapper, 'todo')).toEqual(['b'])
      expect(cards(wrapper, 'doing')).toEqual(['a', 'c'])
      expect(wrapper.emitted('move')?.at(-1)?.[0]).toMatchObject({
        to: { column: 'doing', index: 0 },
        via: 'keyboard',
      })
    })

    it('steps over a column that is full or locked', async () => {
      const wrapper = board()
      const columns = wrapper.props('columns') as KanbanColumn<Task>[]
      columns[1].items.push({ id: 'd', title: 'Review' })
      await nextTick()

      await cardAt(wrapper, 'a').trigger('keydown', { key: ' ' })
      await cardAt(wrapper, 'a').trigger('keydown', { key: 'ArrowRight' })

      // "In progress" is at its limit of two, so the card lands in "Done".
      expect(cards(wrapper, 'done')).toEqual(['a'])
    })

    it('puts the card back where it came from on escape', async () => {
      const wrapper = board()

      await cardAt(wrapper, 'a').trigger('keydown', { key: ' ' })
      await cardAt(wrapper, 'a').trigger('keydown', { key: 'ArrowRight' })
      expect(cards(wrapper, 'doing')).toEqual(['a', 'c'])

      await cardAt(wrapper, 'a').trigger('keydown', { key: 'Escape' })

      expect(cards(wrapper, 'todo')).toEqual(['a', 'b'])
      expect(wrapper.get('.wx-kanban__live').text()).toBe('Move cancelled.')
    })

    it('leaves the keys alone until a card is picked up', async () => {
      const wrapper = board()

      await cardAt(wrapper, 'a').trigger('keydown', { key: 'ArrowDown' })

      expect(cards(wrapper, 'todo')).toEqual(['a', 'b'])
      expect(wrapper.emitted('move')).toBeUndefined()
    })

    it('ignores keys pressed on a control inside the card', async () => {
      const wrapper = board({}, { card: '<button class="open">Open</button>' })

      await wrapper.get('.open').trigger('keydown', { key: ' ' })

      expect(wrapper.emitted('move')).toBeUndefined()
      expect(wrapper.get('.wx-kanban__live').text()).toBe('')
    })
  })

  it('takes nothing from a board that is disabled', async () => {
    const wrapper = board({ disabled: true })

    expect(cardAt(wrapper, 'a').attributes('tabindex')).toBe('-1')

    await cardAt(wrapper, 'a').trigger('keydown', { key: ' ' })

    expect(wrapper.get('.wx-kanban__live').text()).toBe('')
    expect(wrapper.emitted('move')).toBeUndefined()
  })

  describe('the column itself', () => {
    it('carries whatever actions the column is given', () => {
      const wrapper = board(
        {},
        {
          'column-actions':
            '<template #column-actions="{ column }"><button :class="column.id">+</button></template>',
        },
      )

      const head = wrapper.findAll('.wx-kanban__head')[0]
      expect(head.get('.wx-kanban__actions .todo').text()).toBe('+')
    })

    it('folds down to a strip and opens again', async () => {
      const wrapper = board({ collapsible: true })

      await wrapper.findAll('.wx-kanban__collapse')[0].trigger('click')

      expect(wrapper.emitted('update:collapsed')?.at(-1)).toEqual([['todo']])

      await wrapper.setProps({ collapsed: ['todo'] })

      const column = wrapper.findAll('.wx-kanban__column')[0]
      expect(column.classes()).toContain('is-collapsed')
      expect(column.find('.wx-kanban__list').exists()).toBe(false)
      expect(column.get('.wx-kanban__title--vertical').text()).toBe('To do')

      await column.get('.wx-kanban__expand').trigger('click')
      expect(wrapper.emitted('update:collapsed')?.at(-1)).toEqual([[]])
    })

    /* Folded, a column has nowhere to put a card, so the keyboard passes it by. */
    it('is passed over by a card while it is folded', async () => {
      const wrapper = board({ collapsible: true, collapsed: ['doing'] })

      await cardAt(wrapper, 'a').trigger('keydown', { key: ' ' })
      await cardAt(wrapper, 'a').trigger('keydown', { key: 'ArrowRight' })

      expect(cards(wrapper, 'done')).toEqual(['a'])
    })

    it('offers to add a column after the last one', async () => {
      const wrapper = board({ columnAddable: true, addColumnLabel: 'Add a status' })

      expect(wrapper.get('.wx-kanban__add-column').text()).toBe('Add a status')

      await wrapper.get('.wx-kanban__add-column').trigger('click')
      expect(wrapper.emitted('add-column')).toHaveLength(1)
    })

    it('moves with the keyboard when the columns may be reordered', async () => {
      const wrapper = board({ reorderColumns: true })
      const heads = () =>
        wrapper.findAll('.wx-kanban__head').map((head) => head.get('.wx-kanban__title').text())

      const first = wrapper.findAll('.wx-kanban__head')[0]
      await first.trigger('keydown', { key: ' ' })
      expect(first.attributes('aria-pressed')).toBe('true')

      await wrapper.get('[data-column-grip="todo"]').trigger('keydown', { key: 'ArrowRight' })

      expect(heads()).toEqual(['In progress', 'To do', 'Done'])
      expect(wrapper.emitted('column-move')?.at(-1)?.[0]).toMatchObject({
        from: 0,
        to: 1,
        via: 'keyboard',
      })

      await wrapper.get('[data-column-grip="todo"]').trigger('keydown', { key: 'Escape' })
      expect(heads()).toEqual(['To do', 'In progress', 'Done'])
    })

    it('leaves the columns alone unless it is told they may move', async () => {
      const wrapper = board()

      const first = wrapper.findAll('.wx-kanban__head')[0]
      expect(first.attributes('tabindex')).toBe('-1')

      await first.trigger('keydown', { key: ' ' })
      expect(wrapper.get('.wx-kanban__live').text()).toBe('')
    })
  })

  it('takes a column width and a size', () => {
    const wrapper = board({ columnWidth: '20rem', size: 'sm' })

    expect(wrapper.classes()).toContain('wx-kanban--sm')
    expect(wrapper.attributes('style')).toContain('--wx-kanban-column-width: 20rem')
  })
})
