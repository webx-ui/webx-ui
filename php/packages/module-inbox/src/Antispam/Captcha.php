<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Inbox\Models\Form;

/**
 * reCAPTCHA or Turnstile, if a form asked for one.
 *
 * Which provider is the form's setting; the secret is the site's, because it is one pair of
 * keys for every form and a secret copied into each form's options is a secret in as many
 * places as there are forms (§5).
 *
 * A form that asks for a captcha the site has no key for refuses submissions rather than
 * waving them through — a rubber stamp would be worse than no captcha, because nobody would
 * know. The refusal is written to the log where somebody may see it.
 */
final class Captcha
{
    private const RESPONSE_FIELDS = [
        'recaptcha' => 'g-recaptcha-response',
        'turnstile' => 'cf-turnstile-response',
    ];

    /** The class each provider's own script looks for when it goes hunting for widgets. */
    private const WIDGETS = [
        'recaptcha' => 'g-recaptcha',
        'turnstile' => 'cf-turnstile',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Http $http,
        private readonly LoggerInterface $log,
    ) {}

    public function passes(Form $form, Request $request): bool
    {
        $provider = $this->provider($form);

        if ($provider === null) {
            return true;
        }

        $secret = $this->config->get("webx-inbox.captcha.{$provider}.secret");

        if (! is_string($secret) || $secret === '') {
            $this->log->warning('webx-inbox: the form {form} asks for a {provider} captcha and the site has no secret for it.', [
                'form' => $form->slug,
                'provider' => $provider,
            ]);

            return false;
        }

        $answer = $request->input(self::RESPONSE_FIELDS[$provider]);

        if (! is_string($answer) || $answer === '') {
            return false;
        }

        return $this->verify($provider, $secret, $answer, (string) $request->ip());
    }

    /** The provider a form is configured with, or null when it wants none. */
    public function provider(Form $form): ?string
    {
        $provider = $form->antispam('captcha');

        if (! is_string($provider) || ! array_key_exists($provider, self::RESPONSE_FIELDS)) {
            return null;
        }

        return $provider;
    }

    /** The name of the hidden input that carries the answer, for the form on the site. */
    public function responseField(string $provider): ?string
    {
        return self::RESPONSE_FIELDS[$provider] ?? null;
    }

    /**
     * What the form on the site draws, or nothing at all.
     *
     * Nothing at all covers two cases that look the same on the page and read very differently
     * in the log: a form that asked for no captcha, and a form that asked for one the site has
     * no site key for. The second is a form that renders and then refuses every submission —
     * the verifier has no secret either — so it says so where somebody may see it.
     *
     * @return array{provider: string, key: string, widget: string, field: string}|null
     */
    public function widget(Form $form): ?array
    {
        $provider = $this->provider($form);

        if ($provider === null) {
            return null;
        }

        $key = $this->config->get("webx-inbox.captcha.{$provider}.key");

        if (! is_string($key) || $key === '') {
            $this->log->warning('webx-inbox: the form {form} asks for a {provider} captcha and the site has no site key for it.', [
                'form' => $form->slug,
                'provider' => $provider,
            ]);

            return null;
        }

        return [
            'provider' => $provider,
            'key' => $key,
            'widget' => self::WIDGETS[$provider],
            'field' => self::RESPONSE_FIELDS[$provider],
        ];
    }

    private function verify(string $provider, string $secret, string $answer, string $ip): bool
    {
        $endpoint = (string) $this->config->get("webx-inbox.captcha.{$provider}.verify");

        try {
            $response = $this->http
                ->timeout((int) $this->config->get('webx-inbox.captcha.timeout', 5))
                ->asForm()
                ->post($endpoint, [
                    'secret' => $secret,
                    'response' => $answer,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable $exception) {
            // The verifier being unreachable is the site's problem, not the visitor's: an
            // enquiry lost to somebody else's outage is worse than a robot getting through.
            $this->log->warning('webx-inbox: could not reach the {provider} captcha; the submission was let through.', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            return true;
        }

        return (bool) ($response->json('success') ?? false);
    }
}
