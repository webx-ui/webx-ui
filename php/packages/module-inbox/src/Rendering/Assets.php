<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use WebxUi\Widgets\Captcha;

/**
 * The scripts a printed form needs, each of them at most once per page.
 *
 * A page may carry three forms, and three copies of the same `<script>` is three downloads on
 * a cold cache and three sets of handlers on a warm one. This is `scoped`, so the flags belong
 * to the response being built and not to the worker building it.
 *
 * Sending is the widgets' form engine (WIDGETS §17), and what is left to the inbox's own script
 * is the captcha — so a form prints it only when it draws one. A view the site published before
 * W6.4 asks for the old script, the one that sent the form itself (`script(legacy: true)`, an
 * alias period).
 *
 * Both are served from the package rather than published into a site's build, because the
 * alternative is a site whose form quietly stops working after a release nobody rebuilt for.
 * Publishing is still there for a site that would rather bundle them (`webx-inbox-assets`, and
 * then `webx-inbox.script` goes false).
 */
final class Assets
{
    public const array SCRIPTS = [
        'webx.inbox.script' => __DIR__.'/../../resources/js/inbox.js',
        'webx.inbox.script.legacy' => __DIR__.'/../../resources/js/inbox-legacy.js',
    ];

    /** @var array<string, bool> */
    private array $script = [];

    /** @var array<string, string> */
    private static array $versions = [];

    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $url,
        private readonly Captcha $captcha,
    ) {}

    /**
     * The inbox's script — the captcha's half of sending — the first time a form on this page
     * asks for it; with `legacy`, the old one that sends a view of before W6.4 itself.
     */
    public function script(bool $legacy = false): ?string
    {
        $route = $legacy ? 'webx.inbox.script.legacy' : 'webx.inbox.script';

        if (($this->script[$route] ?? false) || ! (bool) $this->config->get('webx-inbox.script', true)) {
            return null;
        }

        $this->script[$route] = true;

        // The version is the content, so the answer may be cached forever and a release that
        // changes the file changes the address it is asked for.
        return $this->url->route($route, ['v' => self::version($route)]);
    }

    /**
     * The captcha provider's own script, the first time a form on this page asks for it.
     *
     * Printed by the package because the block is: a widget with nothing to draw it is an
     * empty div and a form that cannot be submitted, and finding out why is a bad afternoon.
     * A site that loads the provider itself turns this off in the configuration.
     *
     * `$render` is a reCAPTCHA v3 site key: v3 is loaded with it, `api.js?render=<key>`, and
     * without it `grecaptcha.execute(key, …)` has nothing to run.
     */
    public function captchaScript(string $provider, ?string $render = null): ?string
    {
        // The site's captcha keeps the count: a form of the checkout on the same page loads it too.
        return $this->captcha->script($provider, $render);
    }

    /** What is served, read once per process — it cannot change under a running worker. */
    public static function contents(string $route = 'webx.inbox.script'): string
    {
        return (string) file_get_contents(self::SCRIPTS[$route]);
    }

    public static function version(string $route = 'webx.inbox.script'): string
    {
        return self::$versions[$route] ??= substr(hash('xxh128', self::contents($route)), 0, 16);
    }
}
