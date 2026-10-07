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
 * places as there are forms (§5). So is the kind of reCAPTCHA key — a checkbox, Invisible or
 * v3 — because it is a property of the keys and not of the form: drawn as the wrong kind, the
 * widget says "Invalid key type" and nothing else.
 *
 * A form that asks for a captcha the site has no key for refuses submissions rather than
 * waving them through — a rubber stamp would be worse than no captcha, because nobody would
 * know. The refusal is written to the log where somebody may see it.
 */
final class Captcha
{
    public const CHECKBOX = 'checkbox';

    public const INVISIBLE = 'invisible';

    public const V3 = 'v3';

    public const RECAPTCHA_TYPES = [self::CHECKBOX, self::INVISIBLE, self::V3];

    private const RESPONSE_FIELDS = [
        'recaptcha' => 'g-recaptcha-response',
        'turnstile' => 'cf-turnstile-response',
    ];

    /**
     * The class each provider's own script looks for when it goes hunting for widgets.
     *
     * Only for the widgets that script draws by itself. An invisible widget of either provider
     * drawn that way would want a global function to call with its token, so it gets a class
     * nobody hunts for and the form's script draws it on submit; v3 has no widget at all.
     */
    private const WIDGETS = [
        'recaptcha' => 'g-recaptcha',
        'turnstile' => 'cf-turnstile',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Http $http,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * Null when the form wants no captcha or the answer is good, otherwise the refusal.
     */
    public function check(Form $form, Request $request): ?Inspection
    {
        $provider = $this->provider($form);

        if ($provider === null) {
            return null;
        }

        $type = $this->type($provider);
        $secret = $this->config->get("webx-inbox.captcha.{$provider}.secret");

        if (! is_string($secret) || $secret === '') {
            $this->log->warning('webx-inbox: the form {form} asks for a {provider} captcha and the site has no secret for it.', [
                'form' => $form->slug,
                'provider' => $provider,
            ]);

            return Inspection::reject(Inspection::CAPTCHA_UNCONFIGURED, ['provider' => $provider, 'type' => $type]);
        }

        $answer = $request->input(self::RESPONSE_FIELDS[$provider]);

        if (! is_string($answer) || $answer === '') {
            return Inspection::reject(Inspection::CAPTCHA_MISSING, ['provider' => $provider, 'type' => $type]);
        }

        $failure = $this->verify($form, $provider, $type, $secret, $answer, (string) $request->ip());

        return $failure === null
            ? null
            : Inspection::reject(Inspection::CAPTCHA_FAILED, ['provider' => $provider, 'type' => $type, ...$failure]);
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

    /**
     * How the widget runs: one of {@see RECAPTCHA_TYPES} for reCAPTCHA, which is the kind of key
     * the site has; for Turnstile `invisible` when its `mode` says so — run on submit — and
     * otherwise the checkbox, drawn when the page opens.
     *
     * A value nobody recognises is the checkbox, because it is what the widget used to be.
     */
    public function type(string $provider): string
    {
        if ($provider === 'turnstile') {
            return $this->config->get('webx-inbox.captcha.turnstile.mode') === self::INVISIBLE ? self::INVISIBLE : self::CHECKBOX;
        }

        $type = $this->config->get('webx-inbox.captcha.recaptcha.type');

        return is_string($type) && in_array($type, self::RECAPTCHA_TYPES, true) ? $type : self::CHECKBOX;
    }

    /** Whether the site holds both halves of a provider's keys. */
    public function configured(string $provider): bool
    {
        foreach (['key', 'secret'] as $half) {
            $value = $this->config->get("webx-inbox.captcha.{$provider}.{$half}");

            if (! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }

    /** The name of the hidden input that carries the answer, for the form on the site. */
    public function responseField(string $provider): ?string
    {
        return self::RESPONSE_FIELDS[$provider] ?? null;
    }

    /**
     * The v3 action a form's token is asked for and checked against.
     *
     * reCAPTCHA takes letters, digits, slashes and underscores, and a slug has hyphens.
     */
    public function action(Form $form): string
    {
        return 'webx_form_'.(string) preg_replace('/[^A-Za-z0-9_\/]/', '_', $form->slug);
    }

    /**
     * What the form on the site draws, or nothing at all.
     *
     * Nothing at all covers two cases that look the same on the page and read very differently
     * in the log: a form that asked for no captcha, and a form that asked for one the site has
     * no site key for. The second is a form that renders and then refuses every submission —
     * the verifier has no secret either — so it says so where somebody may see it.
     *
     * @return array{provider: string, type: string, key: string, widget: string, field: string, action: string}|null
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

        $type = $this->type($provider);

        return [
            'provider' => $provider,
            'type' => $type,
            'key' => $key,
            'widget' => $type === self::CHECKBOX ? self::WIDGETS[$provider] : 'wx-form__captcha-widget',
            'field' => self::RESPONSE_FIELDS[$provider],
            'action' => $this->action($form),
        ];
    }

    /**
     * Null when the provider vouched for the answer, otherwise what it said instead.
     *
     * @return array<string, mixed>|null
     */
    private function verify(Form $form, string $provider, string $type, string $secret, string $answer, string $ip): ?array
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

            return null;
        }

        $said = array_filter([
            'error-codes' => $response->json('error-codes'),
            'score' => $response->json('score'),
            'action' => $response->json('action'),
            'hostname' => $response->json('hostname'),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if (($response->json('success') ?? false) !== true) {
            return $said;
        }

        if ($type !== self::V3) {
            return null;
        }

        // A v3 answer is never a no, only a number: what makes it a robot is the site's line.
        $score = $response->json('score');
        $minimum = (float) $this->config->get('webx-inbox.captcha.recaptcha.min_score', 0.5);

        if (! is_numeric($score) || (float) $score < $minimum) {
            return [...$said, 'min_score' => $minimum];
        }

        // A token minted for another form, or for a login, is a token for something else.
        $action = $response->json('action');

        if (is_string($action) && $action !== '' && $action !== $this->action($form)) {
            return [...$said, 'expected_action' => $this->action($form)];
        }

        return null;
    }
}
