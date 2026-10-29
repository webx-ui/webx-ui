<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

use Illuminate\Http\Request;
use WebxUi\Inbox\Models\Form;
use WebxUi\Widgets\Captcha as SiteCaptcha;

/**
 * reCAPTCHA or Turnstile, if a form asked for one — on the site's captcha (`WebxUi\Widgets\Captcha`,
 * ORDERS §28.1): the keys, how they run and the check are the site's, one pair for every form.
 *
 * What a form keeps is its own `antispam.captcha`: `off`, `on` — the site's provider — or the name
 * of a provider, which is what forms said before the captcha was the site's. A named provider
 * still means "on", and it is the one the form draws: a form whose provider is not the site's goes
 * on working with its own (the audit's `inbox.captcha_provider_mismatch` suggests changing it), and
 * on a site that chose no provider — the migration has not run, or there is no `module-settings` —
 * every form works exactly as it did, with the keys of `webx-inbox.captcha.*`.
 *
 * A form that asks for a captcha the site has no key for refuses submissions rather than waving
 * them through; the refusal is written to the log where somebody may see it.
 */
final class Captcha
{
    public const CHECKBOX = SiteCaptcha::CHECKBOX;

    public const INVISIBLE = SiteCaptcha::INVISIBLE;

    public const V3 = SiteCaptcha::V3;

    public const RECAPTCHA_TYPES = SiteCaptcha::RECAPTCHA_TYPES;

    /** A form's `antispam.captcha` that asks for the site's provider, whichever it is. */
    public const ON = 'on';

    public function __construct(private readonly SiteCaptcha $site) {}

    /**
     * Null when the form wants no captcha or the answer is good, otherwise the refusal.
     */
    public function check(Form $form, Request $request): ?Inspection
    {
        $provider = $this->provider($form);

        if ($provider === null) {
            return $this->wantsSite($form) ? $this->refuse($form, $request) : null;
        }

        $refusal = $this->site->check($request, $this->action($form), $provider, $form->slug);

        if ($refusal === null) {
            return null;
        }

        $reason = match ($refusal['reason']) {
            SiteCaptcha::UNCONFIGURED => Inspection::CAPTCHA_UNCONFIGURED,
            SiteCaptcha::MISSING => Inspection::CAPTCHA_MISSING,
            default => Inspection::CAPTCHA_FAILED,
        };

        unset($refusal['reason']);

        return Inspection::reject($reason, $refusal);
    }

    /**
     * The provider a form draws, or null when it wants none: its own when it names one, the site's
     * when it says `on`.
     */
    public function provider(Form $form): ?string
    {
        $own = $form->antispam('captcha');

        if ($own === self::ON) {
            return $this->site->provider();
        }

        return is_string($own) && in_array($own, SiteCaptcha::PROVIDERS, true) ? $own : null;
    }

    /** Whether a form keeps a provider of its own that is not the one the site chose. */
    public function mismatch(Form $form): bool
    {
        $own = $form->antispam('captcha');
        $site = $this->site->provider();

        return $site !== null && is_string($own) && in_array($own, SiteCaptcha::PROVIDERS, true) && $own !== $site;
    }

    /** The site's provider, or null when it chose none. */
    public function siteProvider(): ?string
    {
        return $this->site->provider();
    }

    public function type(string $provider): string
    {
        return $this->site->type($provider);
    }

    public function configured(string $provider): bool
    {
        return $this->site->configured($provider);
    }

    /**
     * @return list<'key'|'secret'>
     */
    public function missing(string $provider): array
    {
        return $this->site->missing($provider);
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return $this->site->available();
    }

    public function responseField(string $provider): ?string
    {
        return $this->site->responseField($provider);
    }

    /** The v3 action a form's token is asked for and checked against. */
    public function action(Form $form): string
    {
        return SiteCaptcha::action('webx_form_'.$form->slug);
    }

    /**
     * What the form on the site draws, or nothing at all (see `WebxUi\Widgets\Captcha::widget()`).
     *
     * @return array{provider: string, type: string, key: string, widget: string, field: string, action: string}|null
     */
    public function widget(Form $form): ?array
    {
        $provider = $this->provider($form);

        return $provider === null ? null : $this->site->widget($this->action($form), $provider, $form->slug, 'webx-inbox-form__captcha-widget');
    }

    /** `on` on a site that chose no provider: the form asked for a captcha nobody can draw. */
    private function wantsSite(Form $form): bool
    {
        return $form->antispam('captcha') === self::ON;
    }

    private function refuse(Form $form, Request $request): Inspection
    {
        $refusal = $this->site->check($request, $this->action($form), null, $form->slug) ?? [];
        unset($refusal['reason']);

        return Inspection::reject(Inspection::CAPTCHA_UNCONFIGURED, $refusal);
    }
}
