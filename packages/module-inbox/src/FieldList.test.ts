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
