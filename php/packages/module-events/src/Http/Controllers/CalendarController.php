<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Events\EventsServiceProvider;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Rendering\Calendar;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * `{prefix}/{slug}.ics` — "Add to calendar" (§4.3).
 *
 * The address without `.ics` is looked up in the registry, so the file answers exactly where the
 * page does, an old address included. It has to be an event a reader may see, and one with a date:
 * a calendar has nowhere to put "every Saturday". Anything else is a 404.
 */
class CalendarController
{
    public function __construct(
        private readonly Calendar $calendar,
        private readonly Locales $locales,
    ) {}

    /**
     * By name rather than by position: where the language is in the path, it is the first
     * parameter of the route.
     */
    public function __invoke(Request $request): Response
    {
        $path = (string) $request->route('path');
        $locale = $this->locales->current();
        $prefix = EventsServiceProvider::prefix(config());
        $key = UrlNormaliser::key($prefix.'/'.$path);

        $row = Route::query()
            ->where('locale', $locale)
            ->where('path', $key)
            ->orderByRaw('case when kind = ? then 0 else 1 end', [Route::CANONICAL])
            ->first();

        $event = $row?->entity;

        if (! $event instanceof Event || ! $event->isVisible($locale) || $event->starts_at === null) {
            throw new NotFoundHttpException;
        }

        return response($this->calendar->of($event, $locale), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->calendar->filename($event, $locale).'"',
        ]);
    }
}
