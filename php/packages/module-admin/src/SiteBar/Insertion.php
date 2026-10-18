<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Localization\Contracts\HasPanelLocale;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Resolution;

/**
 * Whether this response gets the panel on the site, and putting it there (§3).
 *
 * The server decides, never the script: only the server knows who is signed in, and a visitor
 * has to get the page byte for byte as it was without the bar — no script, no attribute, no
 * header. So every condition is a reason to leave the response alone, and the cheap ones go
 * first: most responses are a visitor's, and they should cost a string search and no query.
 */
final class Insertion
{
    /** Where the decision about markers is kept on the request: asked once per block, made once. */
    public const MARKS = 'webx.site-bar.marks';

    /** The markers the renderer prints for the bar (§8): blocks, the record, the regions. */
    private const MARKERS = '/<!--\/?wx(?:-entity|-region)?:[^>]*?-->/';

    public function __construct(
        private readonly Config $config,
        private readonly AuthFactory $auth,
        private readonly SiteBar $bar,
        private readonly Loader $loader,
        private readonly Locales $locales,
        private readonly Translator $translator,
        private readonly PageResponse $pages,
    ) {}

    public function apply(Request $request, Response $response): void
    {
        if (! $this->isPage($request, $response) || ($user = $this->administrator()) === null || $this->sections($request, $user) === []) {
            $this->unmark($request, $response);

            return;
        }

        $html = (string) $response->getContent();
        $at = (int) strripos($html, '</body>');

        $response->setContent(substr($html, 0, $at).$this->tag($request, $user)."\n".substr($html, $at));

        // A page with the bar is this administrator's page: a proxy or a CDN that kept it would
        // hand the script to the next visitor. The opposite — a cached visitor's page reaching
        // an administrator — only loses the bar, so the visitor's response keeps its headers.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary('Cookie', false);
        $response->headers->remove('Content-Length');
    }

    /**
     * Whether the blocks on this page are printed with their markers (§8) — the same decision as
     * putting the bar on the page, taken before the page is rendered rather than after it, since
     * the markers are needed while the renderer prints.
     *
     * Made the first time the renderer asks — by then the address is resolved and the record
     * behind it known — and kept on the request, so a page of forty blocks asks once. A visitor
     * costs the same as for the bar itself: a few checks of the request and no query. What the
     * response turns out to be (not a 200, not HTML) is known only afterwards; {@see apply()}
     * takes the markers back out of such a response.
     */
    public function marks(Request $request): bool
    {
        $known = $request->attributes->get(self::MARKS);

        if (is_bool($known)) {
            return $known;
        }

        $user = $this->wanted($request) ? $this->administrator() : null;
        $marks = $user !== null && $this->sections($request, $user) !== [];

        $request->attributes->set(self::MARKS, $marks);

        return $marks;
    }

    /**
     * Markers printed for a bar that is not put on the page after all: a response that is not a
     * page, or one nobody may edit. They say only keys and slugs, but they say them to nobody.
     */
    private function unmark(Request $request, Response $response): void
    {
        if ($request->attributes->get(self::MARKS) !== true) {
            return;
        }

        $content = $response->getContent();

        if (is_string($content) && str_contains($content, '<!--')) {
            $response->setContent((string) preg_replace(self::MARKERS, '', $content));
        }
    }

    /** The request and the response, before anybody is asked who is signed in. */
    private function isPage(Request $request, Response $response): bool
    {
        return $this->wanted($request) && $this->pages->response($response);
    }

    /** The half of the decision the request alone answers: known before anything is rendered. */
    private function wanted(Request $request): bool
    {
        return (bool) $this->config->get('webx-admin.site_bar.enabled', true) && $this->pages->request($request);
    }

    /**
     * Who is signed in to the panel, if anybody — under the panel's own guard, by name.
     *
     * The default guard on a site's page is the site's (its customers'), and only `cms.auth`
     * switches it; out here no such middleware has run. A site without `module-auth` has no
     * guard to name and so no administrator: a panel nobody signs in to has nobody to show it to.
     *
     * @return (Model&HasPermissions)|null
     */
    private function administrator(): ?Model
    {
        $guard = $this->config->get('webx-auth.guard');

        if (! is_string($guard) || $guard === '' || $this->config->get("auth.guards.{$guard}") === null) {
            return null;
        }

        $user = $this->auth->guard($guard)->user();

        if (! $user instanceof Model || ! $user instanceof HasPermissions) {
            return null;
        }

        // A switched-off account keeps its session until it next asks the panel for something;
        // it does not keep a door into it from the site meanwhile.
        if ($user->getAttribute('is_active') === false || $user->getAttribute('is_active') === 0) {
            return null;
        }

        // Null is «never said», and the bar is on until somebody turns it off.
        $wanted = $user->getAttribute('site_bar');

        if ($wanted !== null && ! (bool) $wanted) {
            return null;
        }

        return $user;
    }

    /**
     * The tabs this administrator would have on this page.
     *
     * @return list<string>
     */
    private function sections(Request $request, HasPermissions $user): array
    {
        $resolution = class_exists(Resolution::class) ? Resolution::of($request) : null;

        return $this->bar->sections($user, $resolution?->entity, $resolution?->type?->type);
    }

    private function tag(Request $request, Model $user): string
    {
        $panel = '/'.trim((string) $this->config->get('webx-admin.path', 'cms'), '/');
        $chosen = $user instanceof HasPanelLocale ? $user->panelLocale() : null;
        $locale = $this->locales->resolvePanel(($chosen ?? '') !== '' ? $chosen : ($request->getPreferredLanguage() ?: null));
        $theme = $user->getAttribute('theme');

        $data = [
            'src' => $panel.'/_bar',
            'locale' => $locale,
            // The bar wears the panel's colours rather than the site's: it is the panel.
            'theme' => is_string($theme) ? $theme : null,
            'words' => [
                'open' => $this->word('open', $locale),
                'close' => $this->word('close', $locale),
                'title' => $this->word('title', $locale),
                'draft' => $this->word('draft', $locale),
                'temporary' => $this->word('temporary', $locale),
                'add' => $this->word('add', $locale),
            ],
        ];

        return sprintf(
            '<script type="module" src="%s" data-wx-bar="%s"></script>',
            e($panel.'/_bar/loader.'.$this->loader->hash().'.js'),
            e(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
    }

    private function word(string $key, string $locale): string
    {
        return (string) $this->translator->get("webx-admin::site-bar.loader-{$key}", [], $locale);
    }
}
