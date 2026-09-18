<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The scripts a printed form needs, each of them at most once per page.
 *
 * A page may carry three forms, and three copies of the same `<script>` is three downloads on
 * a cold cache and three sets of submit handlers on a warm one. This is `scoped`, so the flags
 * belong to the response being built and not to the worker building it.
 *
 * The enhancement script is served from the package rather than published into a site's build,
 * because the alternative is a site whose form quietly stops working after a release nobody
 * rebuilt for. Publishing it is still there for a site that would rather bundle it
 * (`webx-inbox-assets`, and then `webx-inbox.script` goes false).
 */
final class Assets
{
    private const SCRIPT = __DIR__.'/../../resources/js/inbox.js';

    private bool $script = false;

    /** @var array<string, bool> */
    private array $captcha = [];

    private static ?string $version = null;

    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $url,
    ) {}

    /**
     * The enhancement script, the first time a form on this page asks for it.
     */
    public function script(): ?string
    {
        if ($this->script || ! (bool) $this->config->get('webx-inbox.script', true)) {
            return null;
        }

        $this->script = true;

        // The version is the content, so the answer may be cached forever and a release that
        // changes the file changes the address it is asked for.
        return $this->url->route('webx.inbox.script', ['v' => self::version()]);
    }

    /**
     * The captcha provider's own script, the first time a form on this page asks for it.
     *
     * Printed by the package because the block is: a widget with nothing to draw it is an
     * empty div and a form that cannot be submitted, and finding out why is a bad afternoon.
     * A site that loads the provider itself turns this off in the configuration.
     */
    public function captchaScript(string $provider): ?string
    {
        $src = $this->config->get("webx-inbox.captcha.{$provider}.script");

        if (! is_string($src) || $src === '' || ($this->captcha[$provider] ?? false)) {
            return null;
        }

        $this->captcha[$provider] = true;

        return $src;
    }

    /** What is served, read once per process — it cannot change under a running worker. */
    public static function contents(): string
    {
        return (string) file_get_contents(self::SCRIPT);
    }

    public static function version(): string
    {
        return self::$version ??= substr(hash('xxh128', self::contents()), 0, 16);
    }
}
