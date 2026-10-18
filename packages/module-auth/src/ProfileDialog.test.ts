import { describe, expect, it, vi } from 'vitest'
import { computed, reactive } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { WebxUI } from '@webx-ui/core'
import {
  adminKey,
  createI18n,
  createTheme,
  i18nKey,
  themeKey,
  type AdminContext,
  type AdminUser,
} from '@webx-ui/module-admin'
import ProfileDialog from './ProfileDialog.vue'
import { authKey, type AuthSession } from './session'

function panel(user: AdminUser): AdminContext {
  const i18n = createI18n({ locale: 'en' })

  return {
    http: {} as never,
    basePath: '/cms',
    apiPath: '/api/cms',
    state: reactive({ status: 'ready', manifest: null, user, error: null }),
    i18n,
    modules: [],
    nav: computed(() => []),
    groups: computed(() => ({ top: [], groups: [] })),
    siteUrl: computed(() => null),
    types: {},
    pickImage: null,
    assetUrls: null,
    loadScreen: () => Promise.resolve([]),
    screenPatch: () => [],
    reload: () => Promise.resolve(),
    refreshManifest: () => Promise.resolve(),
    setLocale: () => Promise.resolve(),
    setUser() {},
    useSessionLoader() {},
    can: () => true,
  } as unknown as AdminContext
}

function session(overrides: Partial<AuthSession> = {}): AuthSession {
  return {
    me: () => Promise.resolve(null),
    login: () => Promise.reject(new Error('not stubbed')),
    logout: () => Promise.resolve(),
    setLocale: () => Promise.resolve(),
    setTheme: () => Promise.resolve(),
    setSiteBar: () => Promise.resolve(),
    updateProfile: vi.fn((profile) => Promise.resolve({ ...editor, name: profile.name })),
    requestEmailChange: vi.fn((email: string) => Promise.resolve({ email, minutes: 60 })),
    ...overrides,
  }
}

/* An editor: no `admins.*`, which is exactly who this dialog is for. */
const editor: AdminUser = {
  id: 2,
  name: 'Editor',
  email: 'editor@example.test',
  isSuper: false,
  permissions: ['pages.view', 'pages.manage'],
  avatar: null,
  siteBar: true,
  emailChange: true,
}

function draw(user: AdminUser, auth: AuthSession) {
  const admin = panel(user)

  return mount(ProfileDialog, {
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: admin.i18n,
        [authKey as symbol]: auth,
        [themeKey as symbol]: createTheme({ storageKey: null }),
      },
    },
  })
}

type Vm = {
  section: string
  form: { name: string; password: string; current_password: string }
  email: { email: string; current_password: string }
  footer: string
  save: () => Promise<void>
  sendLink: () => Promise<void>
}

describe('WxProfileDialog', () => {
  it('sends the profile fields and nothing else, and the password only when one is typed', async () => {
    const auth = session()
    const wrapper = draw(editor, auth)
    const vm = wrapper.vm as unknown as Vm

    vm.form.name = 'Renamed'
    vm.form.current_password = 'typed but no new one'
    await vm.save()

    expect(auth.updateProfile).toHaveBeenLastCalledWith({ name: 'Renamed', avatar: null })

    vm.form.password = 'a-much-longer-secret'
    vm.form.current_password = 'correct-horse-battery'
    await vm.save()

    expect(auth.updateProfile).toHaveBeenLastCalledWith({
      name: 'Renamed',
      avatar: null,
      password: 'a-much-longer-secret',
      current_password: 'correct-horse-battery',
    })

    wrapper.unmount()
  })

  it('opens the password section when the server refuses the current password', async () => {
    const auth = session({
      updateProfile: () =>
        Promise.reject({ body: { errors: { current_password: ['That is not it.'] } } }),
    })
    const wrapper = draw(editor, auth)
    const vm = wrapper.vm as unknown as Vm

    vm.form.password = 'a-much-longer-secret'
    await vm.save()
    await flushPromises()

    expect(vm.section).toBe('password')
    expect(document.body.textContent).toContain('That is not it.')

    wrapper.unmount()
  })

  it('asks for a new address with a letter, and says where it went', async () => {
    const auth = session()
    const wrapper = draw(editor, auth)
    const vm = wrapper.vm as unknown as Vm

    vm.section = 'email'
    await flushPromises()

    expect(vm.footer).toBe('send')

    vm.email.email = 'new.address@example.test'
    vm.email.current_password = 'correct-horse-battery'
    await vm.sendLink()
    await flushPromises()

    expect(auth.requestEmailChange).toHaveBeenCalledWith(
      'new.address@example.test',
      'correct-horse-battery',
    )
    expect(document.body.textContent).toContain('new.address@example.test')
    // The address itself has not changed: only the link does that.
    expect(auth.updateProfile).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('offers no new address where the site sends no mail, and says who changes it', async () => {
    const auth = session()
    const wrapper = draw({ ...editor, emailChange: false }, auth)
    const vm = wrapper.vm as unknown as Vm

    vm.section = 'email'
    await flushPromises()

    expect(vm.footer).toBe('close')
    expect(document.body.textContent).toContain('whoever manages administrators')
    expect(document.querySelector('[name="new_email"]')).toBeNull()

    wrapper.unmount()
  })
})
