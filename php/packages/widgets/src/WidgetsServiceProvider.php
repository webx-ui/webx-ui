<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\HeadPart;
use WebxUi\Widgets\View\Components\Dialog;
use WebxUi\Widgets\View\Components\Tabs;
use WebxUi\Widgets\View\Components\TabsPanel;

/**
 * The widgets as the bottom layer of the theme chain (spec §3, THEMES §7.1, §11):
 *
 *     views    webx-widgets::components.<name>, overridden in <layer>/views/vendor/webx-widgets/
 *     words    webx-widgets::widgets.<name>.*
 *     files    dist/, published by webx:theme:sync next to the themes'
 *     head     first in the cascade, before any theme's stylesheet
 */
class WidgetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: what one request claimed must not load on the next one of a long-lived worker.
        $this->app->scoped(Widgets::class);
        $this->app->tag([Widgets::class], HeadPart::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(Widgets::path().'/resources/views', 'webx-widgets');
        $this->loadTranslationsFrom(Widgets::path().'/lang', 'webx-widgets');

        $this->app->make(BottomLayers::class)->add(Widgets::NAME, Widgets::path());

        Blade::component('webx-dialog', Dialog::class);
        Blade::component('webx-tabs', Tabs::class);
        Blade::component('webx-tabs.panel', TabsPanel::class);

        // The whole page has rendered, the header and the footer too: every claim is known.
        $this->app->make(Dispatcher::class)->listen(RequestHandled::class, function (RequestHandled $event): void {
            $widgets = $this->app->make(Widgets::class);
            $response = $event->response;

            if ($response instanceof Response && self::isPage($response)) {
                $response->setContent($widgets->finish((string) $response->getContent()));
            }

            $widgets->flush();
        });
    }

    /** HTML with the marker: JSON could carry a rendered page inside a string, and must stay JSON. */
    private static function isPage(Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse || $response instanceof JsonResponse) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        $content = $response->getContent();

        return ($type === '' || str_contains($type, 'html')) && is_string($content) && str_contains($content, Widgets::MARKER);
    }
}
