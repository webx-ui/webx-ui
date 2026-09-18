<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Request;
use Throwable;
use WebxUi\Inbox\Models\Form;

/**
 * Whether this looks like a person (§7).
 *
 * Four layers, none of which is trusted on its own. Two of them are free and catch most of
 * what arrives; the captcha is the one that costs the visitor something, so it is off until a
 * form asks for it — a form that cannot be sent because nobody configured a key is a form that
 * is quietly broken, which is what the reference implementation shipped.
 *
 * Throttling is not here: it is the route's own middleware, so a flood is refused before a
 * controller, a model or this class is ever reached.
 */
final class Guard
{
    public function __construct(
        private readonly Config $config,
        private readonly Encrypter $encrypter,
        private readonly Captcha $captcha,
    ) {}

    public function inspect(Form $form, Request $request): Verdict
    {
        if ($this->honeypotFilled($form, $request)) {
            return Verdict::Trap;
        }

        if ($this->tooFast($form, $request)) {
            return Verdict::Reject;
        }

        if (! $this->originAllowed($request)) {
            return Verdict::Reject;
        }

        if (! $this->captcha->passes($form, $request)) {
            return Verdict::Reject;
        }

        return Verdict::Pass;
    }

    /** The name of the field a person never sees, so the form on the site can draw it. */
    public function honeypotField(Form $form): ?string
    {
        $setting = $form->antispam('honeypot');

        if ($setting === false) {
            return null;
        }

        $name = is_string($setting) && $setting !== ''
            ? $setting
            : (string) $this->config->get('webx-inbox.antispam.honeypot', 'webx_hp');

        return $name === '' ? null : $name;
    }

    public function timestampField(): string
    {
        return (string) $this->config->get('webx-inbox.antispam.timestamp', 'webx_ts');
    }

    /** The value that goes in the hidden timestamp of a form being drawn now. */
    public function timestamp(): string
    {
        return $this->encrypter->encrypt((string) time());
    }

    private function honeypotFilled(Form $form, Request $request): bool
    {
        $name = $this->honeypotField($form);

        if ($name === null) {
            return false;
        }

        return trim((string) $request->input($name, '')) !== '';
    }

    /**
     * Nobody fills a form in two seconds, but the mark saying how long it took can be wrong.
     *
     * On a page cached whole the timestamp belongs to the moment the cache was written, not to
     * the moment somebody opened the page — so it reads as hours old for every visitor, and an
     * old mark is therefore not held against anybody. Only a fresh one that is too fresh means
     * anything, and a missing or unreadable one means nothing at all: this is the softest of
     * the four layers on purpose.
     */
    private function tooFast(Form $form, Request $request): bool
    {
        $minimum = (int) $form->antispam('min_seconds');

        if ($minimum <= 0) {
            return false;
        }

        $mark = $request->input($this->timestampField());

        if (! is_string($mark) || $mark === '') {
            return false;
        }

        try {
            $drawn = (int) $this->encrypter->decrypt($mark);
        } catch (Throwable) {
            return false;
        }

        $age = time() - $drawn;
        $stale = (int) $this->config->get('webx-inbox.antispam.stale_after', 86400);

        if ($age < 0 || $age > $stale) {
            return false;
        }

        return $age < $minimum;
    }

    /**
     * A POST from somebody else's page is not this site's form being submitted.
     *
     * A request without an `Origin` and without a `Referer` passes: browsers send one on a
     * cross-origin POST, and what is left without either is mostly `curl` — which the other
     * three layers are the answer to.
     */
    private function originAllowed(Request $request): bool
    {
        $claimed = $request->headers->get('origin') ?: $request->headers->get('referer');

        if (! is_string($claimed) || $claimed === '') {
            return true;
        }

        $host = parse_url($claimed, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return in_array(strtolower($host), $this->allowedHosts($request), true);
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(Request $request): array
    {
        $hosts = [$request->getHost()];

        $configured = (string) $this->config->get('app.url', '');
        $fromAppUrl = parse_url($configured, PHP_URL_HOST);

        if (is_string($fromAppUrl) && $fromAppUrl !== '') {
            $hosts[] = $fromAppUrl;
        }

        foreach ((array) $this->config->get('webx-inbox.antispam.origins', []) as $origin) {
            if (! is_string($origin) || $origin === '') {
                continue;
            }

            // Written either way round — `example.test` or `https://example.test` — because
            // both are what somebody puts in a configuration file.
            $host = parse_url(str_contains($origin, '//') ? $origin : '//'.$origin, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique(array_map('strtolower', $hosts)));
    }
}
