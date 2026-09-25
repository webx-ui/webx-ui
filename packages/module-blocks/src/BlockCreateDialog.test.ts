import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import BlockCreateDialog from './BlockCreateDialog.vue'

function dialog() {
  const post = vi.fn().mockResolvedValue({ data: { id: 3 } })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { post },
    i18n,
    state: { manifest: { modules: [{ id: 'blocks', meta: { groups: ['layout', 'content'] } }] } },
    can: () => true,
  } as unknown as AdminContext

  // Outside a modal host the dialog stands open, teleported into the body.
  const wrapper = mount(BlockCreateDialog, {
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, post }
}

function radios(): HTMLInputElement[] {
  return [...document.body.querySelectorAll<HTMLInputElement>('.wx-block-create input[type=radio]')]
}

function labels(): string[] {
  return [...document.body.querySelectorAll('.wx-block-create .wx-form-item__label')].map(
    (label) => label.textContent?.trim() ?? '',
  )
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('WxBlockCreateDialog', () => {
  it('asks for the kind first, a block by default, each with its sentence', async () => {
    dialog()
    await flushPromises()

    expect(radios().map((radio) => radio.checked)).toEqual([true, false])
    expect(document.body.textContent).toContain('Called from other templates with <x-webx-block>')
    expect(labels()).toContain('Group')
  })

  /* A component is never offered in "Add a block": a group would be a choice about nothing. */
  it('drops the group for a component and sends the kind', async () => {
    const { post } = dialog()
    await flushPromises()

    radios()[1]!.click()
    await flushPromises()

    expect(labels()).not.toContain('Group')

    const name = document.body.querySelector<HTMLInputElement>('.wx-block-create input[type=text]')!
    name.value = 'Price tag'
    name.dispatchEvent(new Event('input'))
    await flushPromises()

    const create = [...document.body.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Create',
    )!
    create.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/blocks', {
      kind: 'component',
      title: 'Price tag',
      slug: 'price-tag',
    })
  })
})
