import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { localesKey, WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FieldList from './FieldList.vue'
import type { InboxField, InboxForm } from './types'

const form = { id: 1, slug: 'contact', title: { en: 'Contact' } } as unknown as InboxForm

const field = (id: number, key: string, type: InboxField['type'], options = {}): InboxField => ({
  id,
  form_id: 1,
  name: key,
  key,
  type,
  title: { en: key[0]!.toUpperCase() + key.slice(1) },
  placeholder: {},
  help: {},
  options,
  is_enabled: true,
  is_required: false,
  is_fullsize: true,
  in_table: false,
  position: id,
})

const purpose = field(1, 'purpose', 'radio', {
  choices: [
    { value: 'agency', label: { en: 'Agency meeting' } },
    { value: 'question', label: { en: 'Just a question' } },
  ],
})
const agency = field(2, 'agency', 'text', { when: { purpose: ['agency'] } })
const budget = field(3, 'budget', 'select', { unless: { purpose: ['question', 'tender'] } })

function list(fields: InboxField[], post = vi.fn().mockResolvedValue({ data: null })) {
  const i18n = createI18n()
  const get = vi.fn().mockResolvedValue({ data: fields })
  const admin = {
    apiPath: '/api/cms',
    http: { get, post, put: vi.fn(), delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const wrapper = mount(FieldList, {
    props: { form, canManage: true, modelValue: fields },
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
      },
    },
  })

  return { wrapper, get, post }
}

describe('WxInboxFieldList and conditions', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('marks a field of half the width, and says nothing of one of the whole', async () => {
    list([purpose, { ...agency, is_fullsize: false }])
    await flushPromises()

    const rows = [...document.querySelectorAll('.wx-inbox-field')].map((row) => row.textContent)
    expect(rows[0]).not.toContain('width')
    expect(rows[1]).toContain('Half width')
    expect(rows[1]).not.toContain('Full width')
  })

  it('says under a field when it is asked, in the labels of the answers', async () => {
    list([purpose, agency])
    await flushPromises()

    const rows = [...document.querySelectorAll('.wx-inbox-field')].map((row) => row.textContent)
    expect(rows[0]).not.toContain('if ')
    expect(rows[1]).toContain('if Purpose = Agency meeting')
  })

  it('marks a row whose condition the server would refuse, and says why', async () => {
    list([purpose, agency, budget])
    await flushPromises()

    const row = [...document.querySelectorAll('.wx-inbox-field')][2]!
    expect(row.textContent).toContain('unless Purpose = Just a question, tender')
    expect(row.textContent).toContain('Check the condition')
    expect(row.textContent).toContain('“Purpose” no longer has the answer “tender”.')
  })

  it('puts the order back when the server refuses it', async () => {
    const refused = vi.fn().mockRejectedValue({
      status: 422,
      body: { message: '“agency” counts on “purpose”.', errors: { ids: ['x'] } },
    })
    const { wrapper, get } = list([purpose, agency], refused)
    await flushPromises()

    await wrapper.setProps({ modelValue: [agency, purpose] })
    wrapper.findComponent({ name: 'WxSortableList' }).vm.$emit('move', {})
    await flushPromises()

    expect(refused).toHaveBeenCalledWith('/api/cms/inbox/forms/1/fields/sorting', { ids: [2, 1] })
    expect(get).toHaveBeenCalledWith('/api/cms/inbox/forms/1/fields')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[purpose, agency]])
  })
})

describe('WxInboxFieldList and steps (FORM_STEPS §9.1)', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  const step = (id: number, key: string, options = {}): InboxField => ({
    ...field(id, key, 'step', options),
    title: { en: key === 'meeting' ? 'Choose a time' : 'Your details' },
  })

  it('draws a step row as a numbered heading, says when it is shown and when it is empty', async () => {
    list([
      purpose,
      step(4, 'meeting', { when: { purpose: ['agency'] } }),
      agency,
      step(5, 'details'),
    ])
    await flushPromises()

    const steps = [...document.querySelectorAll('.wx-inbox-field.is-step')]
    expect(steps).toHaveLength(2)
    // The purpose above the first row is step 1, so the rows are 2 and 3.
    expect(steps[0]!.textContent).toContain('Step 2 · Choose a time')
    expect(steps[0]!.textContent).toContain('if Purpose = Agency meeting')
    expect(steps[0]!.textContent).not.toContain('Empty')
    expect(steps[1]!.textContent).toContain('Step 3 · Your details')
    expect(steps[1]!.textContent).toContain('drag a field below it')
    // A step row says nothing of what a question is: no type, no «Required».
    expect(steps[0]!.textContent).not.toContain('Step ·')

    // The field under a row stands in from it; the one above the first row does not.
    const rows = [...document.querySelectorAll('.wx-inbox-field:not(.is-step)')]
    expect(rows[0]!.classList.contains('is-inside')).toBe(false)
    expect(rows[1]!.classList.contains('is-inside')).toBe(true)
  })

  it('offers «+ Step» only to a form that has a field, and not past ten steps', async () => {
    const { wrapper } = list([])
    await flushPromises()

    const add = () => wrapper.findAll('button').find((one) => one.text() === 'Step')!
    expect(add().attributes('disabled')).toBeDefined()

    await wrapper.setProps({ modelValue: [purpose] })
    expect(add().attributes('disabled')).toBeUndefined()

    const nine = Array.from({ length: 9 }, (_, at) => step(10 + at, `s${at}`))
    await wrapper.setProps({ modelValue: [purpose, ...nine] })
    expect(add().attributes('disabled')).toBeDefined()
  })
})
