<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * An address that has moved answers before the router is asked.
 *
 * That order is the whole point, and it is why this is global middleware rather than a member
 * of the `web` group: the addresses worth redirecting are the ones the site no longer has a
 * route for, and a request for one of those never reaches a group at all — the router throws
 * first. In a group, a redirect table fires only on pages that still work.
 *
 * Chains are not followed. A redirect to an address that is itself redirected sends the browser
 * twice, which costs one request and keeps this readable; collapsing them means walking a graph
 * that an editor can make circular from two different screens.
 */
final class RedirectRequests
{
    public function __construct(
        private readonly SeoRules $rules,
        private readonly UrlMatcher $matcher,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->config->get('webx-seo.redirects.enabled', true) || ! $request->isMethodCacheable()) {
            return $next($request);
        }

        $url = UrlNormaliser::normalise($request->getRequestUri());

        // The panel's own addresses are off limits. It runs on the `web` group like everything
        // else, so without this a mask an editor wrote for the site could lock them out of the
        // screen they wrote it on — and out of the one where they could take it back.
        if ($this->isPanel($url)) {
            return $next($request);
        }

        $redirect = $this->find($url);

        if ($redirect === null) {
            return $next($request);
        }

        [$row, $target] = $redirect;
        $this->count($row);

        return new RedirectResponse($target, $this->status($row));
    }

    private function isPanel(string $url): bool
    {
        foreach (['webx-admin.path', 'webx-admin.api_path'] as $key) {
            $path = trim((string) $this->config->get($key, ''), '/');

            if ($path !== '' && ($url === "/{$path}" || str_starts_with($url, "/{$path}/"))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first redirect that covers the address and does not send it back to itself.
     *
     * The loop guard skips rather than refuses: a mask redirect is a loop only for some of the
     * addresses it covers, and the rest of them are still worth serving.
     *
     * @return array{array<string, mixed>, string}|null
     */
    private function find(string $url): ?array
    {
        $rows = $this->rules->redirects();

        while ($rows !== []) {
            $row = $this->matcher->match($url, $rows);

            if ($row === null) {
                return null;
            }

            $target = UrlMatcher::target(
                is_string($row['match_type'] ?? null) ? $row['match_type'] : '',
                is_string($row['pattern'] ?? null) ? $row['pattern'] : '',
                is_string($row['target'] ?? null) ? $row['target'] : '',
                $url,
            );

            if ($target !== '' && UrlNormaliser::normalise($target) !== $url) {
                return [$row, $target];
            }

            // Drop the one that would have looped and keep looking behind it.
            $rows = array_values(array_filter(
                $rows,
                static fn (array $candidate): bool => ($candidate['id'] ?? null) !== ($row['id'] ?? null),
            ));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function count(array $row): void
    {
        $id = $row['id'] ?? null;

        if (! is_numeric($id)) {
            return;
        }

        // Without loading the model: a counter is not worth a read, an event and a write, and
        // `saved` on this model would throw the compiled list away on every hit.
        SeoRedirect::query()->whereKey((int) $id)->increment('hits', 1, ['last_hit_at' => Carbon::now()]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function status(array $row): int
    {
        $status = is_numeric($row['status'] ?? null) ? (int) $row['status'] : 301;

        return in_array($status, [301, 302, 303, 307, 308], true) ? $status : 301;
    }
}
