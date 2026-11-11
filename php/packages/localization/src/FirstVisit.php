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
 * The cookie means "sent once already", not "this is the language": with it, whatever its value,
 * nobody is sent anywhere again — not from the default language either, so a switcher's plain link
 * back to it simply works. It is written in one answer only, the redirect.
 *
 * Later, with the cookie and on a page of another language, the switcher may offer the target
 * language instead (`offer()`, ROUTING §18.6): a suggestion, never a redirect, until declined.
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
     * The language to send this request to, or `null` when it is to be left alone — everything
     * that can be told before the page is drawn (§18.4, 1–6).
     */
    public function target(Request $request): ?string
    {
        if (! $this->wantsPage($request) || $this->settled($request) || $this->isRobot($request) || $this->excluded($request)) {
            return null;
        }

        $target = $this->configured();

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

    /** Whether somebody was sent once already: the value of the cookie is not looked at. */
    public function settled(Request $request): bool
    {
        return $request->cookies->has($this->cookieName());
    }

    public function cookieName(): string
    {
        return (string) $this->setting('cookie', 'webx_lang');
    }

    /** The cookie of a declined offer (§18.6): set by the offer's script, read here. */
    public function offerCookieName(): string
    {
        return (string) $this->setting('offer.cookie', 'webx_lang_offer');
    }

    /** The language configured, where it is one of the site's — what `webx:doctor` checks. */
    public function configured(): ?string
    {
        $wanted = $this->setting('locale');

        return is_string($wanted) && $wanted !== '' && $this->locales->has($wanted) ? LocaleCatalogue::normalise($wanted) : null;
    }

    /**
     * The language the switcher offers on this page (ROUTING §18.6), or `null`: only to somebody
     * who was sent once already (the cookie) and has not declined, on a page of another language —
     * never to a robot, never in the panel's preview. Whether the page exists in that language is
     * the caller's question: it alone knows the address.
     */
    public function offer(Request $request): ?string
    {
        if (! $this->enabled() || ! (bool) $this->setting('offer.enabled', false)) {
            return null;
        }

        if (! $this->settled($request) || $request->cookies->has($this->offerCookieName()) || $this->isRobot($request) || $this->excluded($request)) {
            return null;
        }

        $target = $this->configured();

        return $target === null || $target === $this->locales->current() ? null : $target;
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

    /** The query the visitor came with — somebody's `?page=2`, an advert's marks — minus ours. */
    private function withQuery(Request $request, string $url): string
    {
        $query = $request->query->all();
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
