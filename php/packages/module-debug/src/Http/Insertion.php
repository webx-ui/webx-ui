<?php

declare(strict_types=1);

namespace WebxUi\Debug\Http;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebxUi\Admin\SiteBar\PageResponse;
use WebxUi\Debug\Access;
use WebxUi\Debug\Bar;
use WebxUi\Debug\Collector;
use WebxUi\Debug\Mode;
use WebxUi\Debug\ReportBuilder;
use WebxUi\Debug\Reports;
use WebxUi\Localization\Contracts\HasPanelLocale;
use WebxUi\Localization\Locales;

/**
 * Where a collected request is decided (§3.1 and §11.1 of the debug bar spec): kept and shown,
 * or thrown away.
 *
 * Collecting stops first, so nothing this does — asking who is signed in, building the report —
 * is in the report. Then every condition the early start could not check is checked for real:
 * a page of the site, an active administrator with `debug.view` in this session (or the one a
 * redeemed token of `debug_page` names). Any of them false, and the response leaves untouched.
 *
 * Kept, the report is written after the response has gone ({@see Application::terminating()}),
 * and the page gets one script tag with the report's id and summary — never the report itself.
 * An agent's request gets no tag: its page is a visitor's, and its report is written at once,
 * because the tool reads it as soon as the page has answered.
 */
final class Insertion
{
    /**
     * Reports to write once the response has gone. A list emptied by one callback registered
     * once: callbacks of `terminating()` stay for the life of the application, and a worker would
     * write every earlier report again after every later response.
     *
     * @var list<array<string, mixed>>
     */
    private array $pending = [];

    private bool $waiting = false;

    public function __construct(
        private readonly Collector $collector,
        private readonly Access $access,
        private readonly PageResponse $pages,
        private readonly ReportBuilder $builder,
        private readonly Reports $reports,
        private readonly Bar $bar,
        private readonly Config $config,
        private readonly Locales $locales,
        private readonly Translator $translator,
        private readonly Application $app,
        private readonly Mode $mode,
    ) {}

    public function apply(Request $request, Response $response): void
    {
        if (! $this->collector->active) {
            return;
        }

        $this->collector->stop();

        try {
            if ($this->collector->token !== null) {
                $this->forAgent($this->collector->token, $request, $response);
            } else {
                $this->forBrowser($request, $response);
            }
        } catch (Throwable $failure) {
            // Being watched must never cost the page: the report is lost, the response is not.
            report($failure);
        } finally {
            $this->collector->reset();
        }
    }

    private function forBrowser(Request $request, Response $response): void
    {
        // Nothing starts a browser's collecting where only the agent's path is open; and were it
        // to, the page of production still gets no bar.
        if ($this->mode->agentOnly || ! $this->pages->page($request, $response)) {
            return;
        }

        $user = $this->access->administrator();

        if ($user === null || ! $this->access->allows($user)) {
            return;
        }

        $id = (string) Str::ulid();
        $report = $this->builder->build($id, $user, $request, $response);

        $this->later($report);

        $html = (string) $response->getContent();
        $at = (int) strripos($html, '</body>');

        $response->setContent(substr($html, 0, $at).$this->tag($request, $user, $report)."\n".substr($html, $at));

        // An administrator's page, like the one with the panel on the site: a proxy that kept it
        // would hand the summary to the next visitor.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary('Cookie', false);
        $response->headers->remove('Content-Length');
    }

    private function forAgent(string $token, Request $request, Response $response): void
    {
        if (! $this->pages->request($request)) {
            return;
        }

        $redeemed = $this->access->redeem($token);

        if ($redeemed === null) {
            return;
        }

        $this->reports->put($this->builder->build($redeemed['id'], $redeemed['user'], $request, $response));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function later(array $report): void
    {
        $this->pending[] = $report;

        if ($this->waiting) {
            return;
        }

        $this->waiting = true;

        $this->app->terminating(function (): void {
            $pending = $this->pending;
            $this->pending = [];

            foreach ($pending as $report) {
                $this->reports->put($report);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function tag(Request $request, Model $user, array $report): string
    {
        $panel = '/'.trim((string) $this->config->get('webx-admin.path', 'cms'), '/');
        $api = '/'.trim((string) $this->config->get('webx-admin.api_path', 'api/cms'), '/');
        $chosen = $user instanceof HasPanelLocale ? $user->panelLocale() : null;
        $locale = $this->locales->resolvePanel(($chosen ?? '') !== '' ? $chosen : ($request->getPreferredLanguage() ?: null));
        $theme = $user->getAttribute('theme');
        $words = $this->translator->get('webx-debug::bar', [], $locale);

        $data = [
            'id' => $report['id'],
            'api' => $api.'/debug/reports',
            'locale' => $locale,
            // The bar wears the panel's colours rather than the site's: it is the panel's.
            'theme' => is_string($theme) ? $theme : null,
            'early' => $report['early'],
            'summary' => $report['summary'],
            'words' => is_array($words) ? $words : [],
        ];

        return sprintf(
            '<script type="module" src="%s" data-wx-debug="%s"></script>',
            e($panel.'/_debug/bar.'.$this->bar->hash().'.js'),
            e(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
    }
}
