<?php

declare(strict_types=1);

namespace WebxUi\Users;

use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Localization\Locales;

/**
 * The addresses of the module's pages in the language of the page (spec §6.1), and the one rule
 * for where a visitor is sent back to: a path of this site, never an address somebody else wrote
 * into a link.
 */
final readonly class Urls
{
    /** The query parameter a page that sent a guest to sign in comes back by. */
    public const string BACK = 'back';

    public function __construct(
        private UrlGenerator $url,
        private Router $router,
        private Config $config,
        private Locales $locales,
    ) {}

    public static function guard(): string
    {
        $guard = config('webx-users.guard');

        return is_string($guard) && $guard !== '' ? $guard : 'site';
    }

    /**
     * `sign-in`, `register`, `forgot`, … — with the language prefix where the page has one.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function to(string $page, ?string $back = null, array $parameters = []): string
    {
        [$name, $parameters] = $this->route('webx-users.'.$page, $parameters);
        $back = $back === null ? null : $this->local($back);

        if ($back !== null) {
            $parameters[self::BACK] = $back;
        }

        return $this->url->route($name, $parameters);
    }

    /**
     * A link for a letter: signed, so nobody makes one up, and alive until `$expires` (spec §8).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function signed(string $page, array $parameters, DateTimeInterface $expires): string
    {
        [$name, $parameters] = $this->route('webx-users.'.$page, $parameters);

        /** @var UrlGenerator&\Illuminate\Routing\UrlGenerator $url */
        $url = $this->url;

        return $url->temporarySignedRoute($name, $expires, $parameters);
    }

    /**
     * A route of any module by its full name — a section of the account (§11.2) — in the
     * language of the page: its copy behind the prefix where the page has one.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function named(string $name, array $parameters = []): string
    {
        [$name, $parameters] = $this->route($name, $parameters);

        return $this->url->route($name, $parameters);
    }

    /**
     * The return address of a social network (§9.4): one for every language — it is written into
     * the provider's console — and absolute.
     */
    public function callback(string $provider): string
    {
        return $this->url->route('webx-users.social.callback', ['provider' => $provider]);
    }

    /** Where a visitor who signed in goes when the page they came from is not known. */
    public function home(): string
    {
        $locale = $this->prefix();

        return $this->url->to($locale === null ? '/' : '/'.$locale);
    }

    /**
     * A path of this site, query and all, or null: an address of another site, `//host`, a
     * scheme of its own, a backslash a browser reads as a slash — anything else is no way back.
     */
    public function local(mixed $back): ?string
    {
        if (! is_string($back) || ($back = trim($back)) === '') {
            return null;
        }

        $root = rtrim($this->url->to('/'), '/');

        if (str_starts_with($back, $root.'/') || $back === $root) {
            $back = substr($back, strlen($root)) ?: '/';
        }

        if (! str_starts_with($back, '/') || str_starts_with($back, '//') || str_contains($back, '\\') || preg_match('/[\x00-\x1F]/', $back) === 1) {
            return null;
        }

        return $back;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{string, array<string, mixed>}
     */
    private function route(string $name, array $parameters): array
    {
        $locale = $this->prefix();

        if ($locale !== null && $this->router->has($name.'.localised')) {
            $name .= '.localised';
            $parameters = [OneSpellingPerAddress::PARAMETER => $locale, ...$parameters];
        }

        return [$name, $parameters];
    }

    /** The language prefix of the current page, or null where the address carries none. */
    private function prefix(): ?string
    {
        if ((string) $this->config->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return null;
        }

        $current = $this->locales->current();

        if (! $this->locales->has($current)) {
            return null;
        }

        return $current === $this->locales->defaultCode() && ! (bool) $this->config->get('webx-localization.prefix_default', false)
            ? null
            : $current;
    }
}
