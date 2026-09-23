<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Seo\Sitemap\Sitemap;
use WebxUi\Settings\Settings;

/**
 * `/robots.txt` from the `seo.robots-txt` setting.
 *
 * Not the same thing as the `robots` field on a rule, which is the page's own meta directives —
 * the words look alike and mean different things, and that is worth saying out loud wherever
 * either of them is documented.
 *
 * Empty means 404 rather than an empty file: an application that keeps a real `public/robots.txt`
 * should keep serving it. In fact it always will — the web server hands that file over before
 * PHP is asked — so this route only ever answers when there is no file.
 */
final class RobotsController
{
    public function __construct(private readonly Container $container) {}

    public function __invoke(): Response
    {
        $body = $this->body();

        if ($body === null) {
            return new Response('', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response($this->withSitemap($body)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function body(): ?string
    {
        if (! $this->container->bound(Settings::class)) {
            return null;
        }

        /** @var Settings $settings */
        $settings = $this->container->make(Settings::class);
        $value = $settings->get('seo.robots-txt');

        if (! is_string($value)) {
            return null;
        }

        $body = trim($value);

        return $body === '' ? null : $body;
    }

    /**
     * The `Sitemap:` line, unless somebody already wrote one (§17.3).
     *
     * Added here rather than written into the setting, so that the address follows the site to
     * another domain and the line goes when the map is turned off. Any `Sitemap:` line counts as
     * written — one pointing at a map the site keeps elsewhere is a decision, not a gap to fill.
     */
    private function withSitemap(string $body): string
    {
        $sitemap = $this->container->make(Sitemap::class);

        if (! $sitemap->enabled() || preg_match('/^\s*sitemap\s*:/im', $body) === 1) {
            return $body;
        }

        return $body."\n\nSitemap: ".$sitemap->url();
    }
}
