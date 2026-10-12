---
'@webx-ui/php': patch
---

A form with an invisible captcha is busy from the press to the answer. Before, a press of Send did nothing visible while the invisible Turnstile or reCAPTCHA (or a reCAPTCHA v3 score) ran, and a second press then started another run. `webx-ui/widgets`: a `webx:form:submit` listener can hold the form with `event.detail.wait(promise)` — `is-sending`, `aria-busy` and the held-back buttons from that moment, further presses ignored; resolved, the form is read again (what the listener put into it goes along) and sent; rejected, it is let go with nothing sent and the listener's word left where it put it. `webx-ui/module-inbox`: `inbox.js` uses it for invisible captchas and v3 instead of cancelling and pressing the button again; a Turnstile that fails, times out, is unsupported or expires before answering lets the form go with its sentence, and a captcha that never answers (a reCAPTCHA challenge closed) does so after two minutes.
