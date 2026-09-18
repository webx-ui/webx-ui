<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Localization\Locales;

/**
 * Answer in the language the page was printed in.
 *
 * The intake writes its own middleware stack (§6), and a stack written by hand is a stack
 * without whatever the site put in its `web` group — the language, first of all. So the door
 * of a Russian site answered in the application's default: the thank-you, the refusals under
 * the fields and the language recorded on the submission were all English on a page nobody
 * had seen a word of English on.
 *
 * Guessing it here is not possible and not necessary. The site puts the language in the path,
 * or in a cookie, or in the session, or nowhere at all — the strategy is the site's — and the
 * one thing that is always true is that the form knows what language it was printed in. So it
 * says so in a hidden field, exactly as the panel tells the server in `X-Webx-Locale` which
 * language it is currently drawn in, and this believes it only as far as a language the site
 * actually has.
 */
final class SpeakTheLanguageOfThePage
{
    public const FIELD = 'webx_locale';

    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $wanted = $request->input(self::FIELD);

        if (is_string($wanted) && $wanted !== '') {
            $this->locales->use($wanted);
        }

        return $next($request);
    }
}
