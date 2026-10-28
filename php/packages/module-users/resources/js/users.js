// The visitor on a page cached for everybody (spec §8.3, §11.6). The page prints the visitor's
// parts hidden and the same for everybody; the cookie `webx_visitor` — `1` signed in, `u` signed
// in with the address not confirmed, none for a guest — says what to show. A guest asks nothing:
// `/account/state` is fetched only behind the cookie, and only for the masked address.

const DISMISSED = 'webx-users:verify-closed'

/** What the cookie says: '1', 'u' or null. */
export function visitor(cookie = document.cookie) {
  const match = /(?:^|;\s*)webx_visitor=([^;]*)/.exec(cookie)
  return match ? decodeURIComponent(match[1]) : null
}

function closed() {
  try {
    return sessionStorage.getItem(DISMISSED) === '1'
  } catch {
    return false
  }
}

function close(banner) {
  banner.hidden = true
  try {
    sessionStorage.setItem(DISMISSED, '1')
  } catch {
    // A browser without storage closes it for this page only.
  }
}

// The letter once more: «Sent» on the button and the button held for the minute of the limit.
function wire(banner) {
  banner.querySelector('[data-webx-users-close]')?.addEventListener('click', () => close(banner))
  const form = banner.querySelector('form')
  form?.addEventListener('webx:form:success', () => {
    const button = form.querySelector('button[type="submit"]')
    if (!button) return
    const label = button.textContent
    button.textContent = form.getAttribute('data-webx-users-sent') || label
    button.disabled = true
    setTimeout(() => {
      button.disabled = false
      button.textContent = label
    }, 60000)
  })
}

export function start(root = document, cookie = document.cookie) {
  const state = visitor(cookie)
  if (state === null) return

  for (const el of root.querySelectorAll('[data-webx-users-guest]')) el.hidden = true
  for (const el of root.querySelectorAll('[data-webx-users-signed-in]')) el.hidden = false

  const banners = [...root.querySelectorAll('[data-webx-users-verify]')]
  if (banners.length === 0) return

  if (closed()) {
    for (const banner of banners) banner.hidden = true
    return
  }

  for (const banner of banners) wire(banner)

  const hidden = banners.filter((banner) => banner.hidden)
  if (state !== 'u' || hidden.length === 0) return

  for (const banner of hidden) banner.hidden = false

  const url = hidden[0].getAttribute('data-webx-users-state')
  if (!url) return

  return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then((response) => (response.ok ? response.json() : null))
    .then((data) => {
      if (!data || !data.signedIn || data.verified) {
        for (const banner of hidden) banner.hidden = true
        return
      }
      for (const banner of hidden) {
        const email = banner.querySelector('[data-webx-users-email]')
        if (email) email.textContent = data.email || ''
      }
    })
    .catch(() => {})
}

if (typeof document !== 'undefined') start()
