<?php

declare(strict_types=1);

namespace WebxUi\Localization\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Localization\FirstVisit;
use WebxUi\Localization\Locales;

/**
 * Decide which language the public site answers this request in.
 *
 * Only the deciding: the URL is left exactly as it came. A site that puts the language in the
 * path declares its routes inside a `{locale?}` prefix, and this reads the same segment — two
 * views of one decision is better than a middleware that rewrites addresses behind routing's
 * back.
 *
 * The one exception is the language of the first visit (ROUTING §18), off unless a site turns it
 * on: a 302 to the same page in another language, decided here so no site edits its middleware.
 */
class SetLocale
{
    public function __construct(
        private readonly Locales $locales,
        private readonly Config $config,
        private readonly FirstVisit $firstVisit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $strategy = (string) $this->config->get('webx-localization.strategy', 'prefix');

        $wanted = match ($strategy) {
            'prefix' => $request->segment(1),
            'header' => $this->fromHeader($request),
            default => null,
        };

        if ($wanted === null || ! $this->locales->use($wanted)) {
            $this->locales->use($this->locales->defaultCode());
        }

        if (! $this->firstVisit->enabled()) {
            return $next($request);
        }

        $target = $this->firstVisit->target($request);
        $response = $next($request);

        return $target === null ? $response : $this->firstVisit->answer($request, $response, $target);
    }

    /**
     * The first language in Accept-Language this site actually has. Quality values are left to
     * Symfony, which already parses them in order.
     */
    private function fromHeader(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            if ($this->locales->has($language)) {
                return $language;
            }
        }

        return null;
    }
}
