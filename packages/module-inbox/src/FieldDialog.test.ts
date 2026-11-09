import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { localesKey, WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FieldDialog from './FieldDialog.vue'
import type { InboxField, InboxForm } from './types'

const form = { id: 1, slug: 'contact', title: { en: 'Contact' } } as unknown as InboxForm

const phone: InboxField = {
  id: 5,
  form_id: 1,
  name: 'phone',
  key: 'phone',
  type: 'tel',
  title: { en: 'Phone' },
  placeholder: {},
  help: {},
  options: {},
  is_enabled: true,
  is_required: false,
  is_fullsize: true,
  in_table: false,
  position: 0,
}

function dialog(field: InboxField | null, taken: string[] = [], fields: InboxField[] = []) {
  const post = vi
    .fn()
    .mockImplementation((_url: string, body: object) =>
      Promise.resolve({ data: { ...phone, ...body } }),
    )
  const put = vi.fn().mockResolvedValue({ data: phone })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get: vi.fn(), post, put },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const wrapper = mount(FieldDialog, {
    props: { field, form, taken, fields },
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'ru' }, { code: 'en' }]), active: ref('ru') },
      },
    },
  })

  /** The control inside the form item with this label. */
  const control = (label: string) => {
    const item = wrapper
      .findAllComponents({ name: 'WxFormItem' })
      .find((one) => one.props('label') === label)

    if (item === undefined) throw new Error(`No field labelled ${label}`)

    return item.findComponent({ name: 'WxInput' })
  }

  const labels = () =>
    wrapper
      .findAllComponents({ name: 'WxFormItem' })
      .map((one) => one.props('label'))
      .filter((one): one is string => typeof one === 'string')

  return { wrapper, post, put, control, labels }
}

describe('WxInboxFieldDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('asks for the type, then the label, and keeps the name for last, under “Advanced”', async () => {
    const { labels } = dialog(null)
    await flushPromises()

    const order = labels()
    expect(order.slice(0, 2)).toEqual(['Type', 'Label'])
    expect(order.at(-1)).toBe('Name')
    expect(document.body.textContent).toContain('Advanced')
    // The name is explained by what it is for, not by the row number a nameless field answers to.
    expect(document.body.textContent).not.toMatch(/\bf\d+\b/)
  })

  it('names a new field after its label until somebody writes a name', async () => {
    const { control, post, wrapper } = dialog(null, ['vash_telefon'])
    await flushPromises()

    control('Label').vm.$emit('update:modelValue', { ru: 'Ваш телефон' })
    await flushPromises()

    // Taken by another field of the form: the next one along.
    expect(control('Name').props('modelValue')).toBe('vash_telefon_2')
    expect(document.body.textContent).toContain('{{field.vash_telefon_2}}')

    // Transliterated by the label's language, as the server does it: Russian «Щука» is `schuka`,
    // not the `shchuka` of a table that knows no language.
    control('Label').vm.$emit('update:modelValue', { ru: 'Щука' })
    await flushPromises()

    expect(control('Name').props('modelValue')).toBe('schuka')

    control('Name').vm.$emit('update:modelValue', 'mobile')
    control('Label').vm.$emit('update:modelValue', { ru: 'Мобильный' })
    await flushPromises()

    expect(control('Name').props('modelValue')).toBe('mobile')

    await wrapper
      .findAllComponents({ name: 'WxButton' })
      .find((one) => one.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/1/fields',
      expect.objectContaining({ name: 'mobile' }),
    )
  })

  it('leaves the name of a field that has one, and warns before it is changed', async () => {
    const { control } = dialog(phone)
    await flushPromises()

    control('Label').vm.$emit('update:modelValue', { en: 'Your phone' })
    await flushPromises()
    expect(control('Name').props('modelValue')).toBe('phone')
    expect(document.querySelector('.wx-alert')).toBeNull()

    control('Name').vm.$emit('update:modelValue', 'mobile')
    await flushPromises()

    const warning = document.querySelector('.wx-alert')?.textContent ?? ''
    expect(warning).toContain('{{field.phone}}')
    expect(warning).toContain('{{field.mobile}}')
  })

  describe('Show (FORM_STEPS §9.2)', () => {
    const make = (patch: Partial<InboxField> & Pick<InboxField, 'id' | 'key' | 'type'>) =>
      ({ ...phone, name: patch.key, title: { en: patch.key }, options: {}, ...patch }) as InboxField

    const purpose = make({
      id: 1,
      key: 'purpose',
      type: 'radio',
      title: { en: 'Purpose' },
      options: {
        choices: [
          { value: 'agency', label: { en: 'Agency meeting' } },
          { value: 'question', label: { en: 'Just a question' } },
        ],
      },
    })
    const note = make({ id: 2, key: 'note', type: 'textarea' })
    const agent = make({ id: 3, key: 'agent', type: 'consent', title: { en: 'Has an agent' } })
    const agency = make({ id: 4, key: 'agency', type: 'text', title: { en: 'Agency' } })
    const later = make({ id: 5, key: 'later', type: 'select', options: { choices: [] } })
    const all = [purpose, note, agent, agency, later]

    const section = () => document.querySelector('.wx-inbox-condition')
    const selects = (wrapper: ReturnType<typeof dialog>['wrapper']) =>
      wrapper.findComponent({ name: 'FieldCondition' }).findAllComponents({ name: 'WxSelect' })
    const segmented = (wrapper: ReturnType<typeof dialog>['wrapper']) =>
      wrapper.findComponent({ name: 'FieldCondition' }).findComponent({ name: 'WxSegmented' })
    const save = async (wrapper: ReturnType<typeof dialog>['wrapper']) => {
      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Save')!
        .trigger('click')
      await flushPromises()
    }

    it('offers only the choice fields above, and writes “if” as options.when', async () => {
      const { wrapper, put } = dialog(agency, [], all)
      await flushPromises()

      expect(section()?.textContent).toContain('Show')
      segmented(wrapper).vm.$emit('update:modelValue', 'when')
      await flushPromises()

      // A text above and a choice below are not offered: one cannot count on them.
      const [field, values] = selects(wrapper)
      expect(field!.props('options')).toEqual([
        { value: 'purpose', label: 'Purpose' },
        { value: 'agent', label: 'Has an agent' },
      ])
      expect(field!.props('modelValue')).toBe('purpose')
      expect(values!.props('options')).toEqual([
        { value: 'agency', label: 'Agency meeting' },
        { value: 'question', label: 'Just a question' },
      ])

      values!.vm.$emit('update:modelValue', ['agency'])
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({ options: { when: { purpose: ['agency'] } } }),
      )
    })

    it('“and” adds the next field; a consent counts on its tick; “unless” and “always” rewrite it', async () => {
      const { wrapper, put } = dialog(agency, [], all)
      await flushPromises()

      segmented(wrapper).vm.$emit('update:modelValue', 'unless')
      await flushPromises()
      selects(wrapper)[1]!.vm.$emit('update:modelValue', ['question'])
      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'One more condition')!
        .trigger('click')
      await flushPromises()

      const tick = selects(wrapper)[3]!
      expect(selects(wrapper)[2]!.props('modelValue')).toBe('agent')
      expect(tick.props('options')).toEqual([{ value: '1', label: 'Ticked' }])
      tick.vm.$emit('update:modelValue', ['1'])
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({
          options: { unless: { purpose: ['question'], agent: ['1'] } },
        }),
      )

      segmented(wrapper).vm.$emit('update:modelValue', 'always')
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({ options: {} }),
      )
    })

    it('shows an answer the field above no longer has in red, and keeps it until it is closed', async () => {
      const broken = { ...agency, options: { when: { purpose: ['agency', 'tender'] } } }
      const { wrapper, put } = dialog(broken, [], all)
      await flushPromises()

      expect(selects(wrapper)[1]!.props('modelValue')).toEqual(['agency'])
      const badge = wrapper
        .findAllComponents({ name: 'WxBadge' })
        .find((one) => one.text().includes('tender'))
      expect(badge?.props('type')).toBe('danger')

      // Picking another answer does not drop it quietly: the server is the one to say no.
      selects(wrapper)[1]!.vm.$emit('update:modelValue', ['agency', 'question'])
      await flushPromises()
      await save(wrapper)
      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({
          options: { when: { purpose: ['tender', 'agency', 'question'] } },
        }),
      )

      badge!.vm.$emit('close', new MouseEvent('click'))
      await flushPromises()
      await save(wrapper)
      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({ options: { when: { purpose: ['agency', 'question'] } } }),
      )
    })

    it('marks a field that is not above any more, and says what the server refused', async () => {
      const moved = { ...agency, options: { when: { later: ['x'] } } }
      const { wrapper, put } = dialog(moved, [], all)
      put.mockRejectedValueOnce({
        status: 422,
        body: { errors: { 'options.when': ['The condition names “later”, which stands below.'] } },
      })
      await flushPromises()

      expect(selects(wrapper)[0]!.props('status')).toBe('error')
      expect(section()?.textContent).toContain('no longer a choice field above')

      await save(wrapper)
      expect(section()?.querySelector('[role=alert]')?.textContent).toContain('stands below')
    })

    it('keeps the other key of a field saved with both, and says it', async () => {
      const both = {
        ...agency,
        options: { when: { purpose: ['agency'] }, unless: { agent: ['1'] } },
      }
      const { wrapper, put } = dialog(both, [], all)
      await flushPromises()

      expect(section()?.textContent).toContain('Also hidden if Has an agent = Ticked')
      await save(wrapper)
      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({
          options: { when: { purpose: ['agency'] }, unless: { agent: ['1'] } },
        }),
      )
    })

    it('says there is nothing to count on for the first field, and has no section for a hidden one', async () => {
      const { wrapper } = dialog(purpose, [], all)
      await flushPromises()

      expect(section()?.textContent).toContain('there is none yet')

      wrapper.findComponent({ name: 'WxSelect' }).vm.$emit('update:modelValue', 'hidden')
      await flushPromises()
      expect(section()).toBeNull()
    })
  })
})
