<?php

declare(strict_types=1);

namespace WebxUi\Localization\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Localization\Contracts\HasPanelLocale;
use WebxUi\Localization\Locales;

/**
 * Answer the panel in the language of whoever is signed in.
 *
 * It matters for more than menus: a 422 carries validation messages, and those are written by
 * the server. Without this an English-speaking administrator gets an English interface and
 * Ukrainian error messages under the fields.
 *
 * Before anybody is signed in there is no preference to read, so an explicit ask from the
 * sign-in screen is honoured, and failing that the browser's own language — both narrowed to
 * what the panel is actually translated into.
 */
class SetPanelLocale
{
    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
        private readonly Locales $locales,
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $this->app->setLocale($this->locales->resolvePanel($this->wanted($request, $guard)));

        return $next($request);
    }

    private function wanted(Request $request, ?string $guard): ?string
    {
        $guard ??= (string) $this->config->get('webx-auth.guard', 'web');
        $user = $this->auth->guard($guard)->user();

        if ($user instanceof HasPanelLocale) {
            $chosen = $user->panelLocale();

            if ($chosen !== null && $chosen !== '') {
                return $chosen;
            }
        }

        $header = $request->header('X-Webx-Locale');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return $request->getPreferredLanguage() ?: null;
    }
}
