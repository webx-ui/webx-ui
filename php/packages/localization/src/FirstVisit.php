<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Localization\Contracts\PageInLanguage;

/**
 * The language of the first visit (ROUTING §18): somebody who comes for the first time to a page
 * in a language other than the chosen one is sent to the same page in it, once.
 *
 * The cookie means "the language is settled", not "this is the language": with it, whatever its
 * value, nobody is sent anywhere again — not from the default language either. It is written in
 * exactly two answers, the redirect and the switcher's choice, never in an ordinary page.
 *
 * Called by `SetLocale`, so a site changes no list of middleware. The decision falls after the
 * page was answered, because which entity the page is only becomes known in the controller; the
 * cheap reasons to leave a request alone are all looked at before it.
 */
final class FirstVisit
{
    public function __construct(
        private readonly Locales $locales,
        private readonly Config $config,
        private readonly PageInLanguage $pages,
    ) {}

    /** Switched on, under a strategy with an address per language, on a site with two of them. */
    public function enabled(): bool
    {
        $wanted = $this->setting('locale');

        return is_string($wanted) && $wanted !== ''
            && (string) $this->config->get('webx-localization.strategy', 'prefix') === 'prefix'
            && count($this->locales->codes()) > 1;
    }

    /**
     * The switcher's explicit choice, `?webx_lang=<code>`: the cookie for a language the site has,
     * and in either case a 302 to the same address without the parameter — a forwarded link
     * carries no choice. For everybody, robots too: they only lose the parameter.
     */
    public function choice(Request $request): ?Response
    {
        $parameter = $this->parameter();

        if (! $request->isMethodSafe() || ! $request->query->has($parameter)) {
            return null;
        }

        $code = $request->query->get($parameter);
        $response = $this->redirect($this->withQuery($request, $request->url()));

        if (is_string($code) && $this->locales->has($code)) {
            $response->headers->setCookie($this->cookie(LocaleCatalogue::normalise($code)));
        }

        return $response;
    }

    /**
     * The language to send this request to, or `null` when it is to be left alone — everything
     * that can be told before the page is drawn (§18.4, 1–6).
     */
    public function target(Request $request): ?string
    {
        if (! $this->wantsPage($request) || $this->settled($request) || $this->isRobot($request) || $this->excluded($request)) {
            return null;
        }

        $target = $this->targetLocale($request);

        return $target === null || $target === $this->locales->current() ? null : $target;
    }

    /**
     * The answer to a first visit, once the page was drawn: a 302 to the same page in the target
     * language, or the page itself where there is none (§18.4, 7–8).
     */
    public function answer(Request $request, Response $response, string $target): Response
    {
        if ($response->getStatusCode() !== 200 || ! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) {
            return $response;
        }

        $url = $this->pages->url($request, $target);

        if ($url === null || $url === '') {
            return $response;
        }

        $redirect = $this->redirect($this->withQuery($request, $url));
        $redirect->headers->setCookie($this->cookie($target));

        return $redirect;
    }

    /** Whether somebody has followed a link of the switcher, or was sent once already. */
    public function settled(Request $request): bool
    {
        return $request->cookies->has($this->cookieName());
    }

    public function cookieName(): string
    {
        return (string) $this->setting('cookie', 'webx_lang');
    }

    public function parameter(): string
    {
        return (string) $this->setting('parameter', 'webx_lang');
    }

    /**
     * The language configured, where it is one of the site's — what `webx:doctor` checks; `header`
     * is not a language and answers `null`.
     */
    public function configured(): ?string
    {
        $wanted = $this->setting('locale');

        return is_string($wanted) && $wanted !== '' && $wanted !== 'header' ? LocaleCatalogue::normalise($wanted) : null;
    }

    public function isRobot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        if (trim($agent) === '') {
            return true;
        }

        foreach ((array) $this->setting('bots', []) as $sign) {
            if (is_string($sign) && $sign !== '' && stripos($agent, $sign) !== false) {
                return true;
            }
        }

        return false;
    }

    /** A page somebody opens: not a form sent, not JSON, not a prefetch nobody looked at. */
    private function wantsPage(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        if (! str_contains(strtolower((string) $request->headers->get('Accept')), 'text/html')) {
            return false;
        }

        $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose')));

        return ! str_contains($purpose, 'prefetch');
    }

    private function excluded(Request $request): bool
    {
        foreach ((array) $this->setting('skip', []) as $parameter) {
            if (is_string($parameter) && $request->query->has($parameter)) {
                return true;
            }
        }

        $patterns = array_values(array_filter((array) $this->setting('except', []), 'is_string'));
        $panel = trim((string) $this->config->get('webx-admin.path', ''), '/');

        if ($panel !== '') {
            $patterns[] = $panel;
            $patterns[] = $panel.'/*';
        }

        return $patterns !== [] && $request->is(...$patterns);
    }

    private function targetLocale(Request $request): ?string
    {
        $wanted = (string) $this->setting('locale');

        if ($wanted !== 'header') {
            return $this->locales->has($wanted) ? LocaleCatalogue::normalise($wanted) : null;
        }

        foreach ($request->getLanguages() as $language) {
            foreach ([$language, explode('_', $language)[0]] as $code) {
                if ($this->locales->has($code)) {
                    return LocaleCatalogue::normalise($code);
                }
            }
        }

        return null;
    }

    /** The query the visitor came with — somebody's `?page=2`, an advert's marks — minus ours. */
    private function withQuery(Request $request, string $url): string
    {
        $query = $request->query->all();
        unset($query[$this->parameter()]);

        foreach ((array) $this->setting('skip', []) as $parameter) {
            unset($query[$parameter]);
        }

        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** A decision for one person, not a move of the address: never 301, never kept by a cache. */
    private function redirect(string $url): RedirectResponse
    {
        $response = new RedirectResponse($url, 302);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * Not encrypted (the provider says so to `EncryptCookies`): the value is no secret, and a
     * proxy in front of the site has to be able to read it.
     */
    private function cookie(string $code): Cookie
    {
        $secure = $this->config->get('session.secure');
        $domain = $this->config->get('session.domain');

        return Cookie::create(
            $this->cookieName(),
            $code,
            time() + 60 * (int) $this->setting('lifetime', 60 * 24 * 365),
            '/',
            is_string($domain) && $domain !== '' ? $domain : null,
            is_bool($secure) ? $secure : null,
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->get('webx-localization.first_visit.'.$key, $default);
    }
}
