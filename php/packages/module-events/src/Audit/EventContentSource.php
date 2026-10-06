<?php

declare(strict_types=1);

namespace WebxUi\Events\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Content\ModelContentSource;
use WebxUi\Events\Models\Event;

/**
 * The events' text for the site audit: the description and the lines around it, the venue, the
 * map and booking links, the gallery — published and in the draft.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class EventContentSource extends ModelContentSource
{
    public function id(): string
    {
        return 'events';
    }

    protected function models(): array
    {
        return ['' => Event::class];
    }

    protected function columns(Model $model): array
    {
        return ['lead', 'description', 'highlights', 'date_note', 'venue', 'address', 'price', 'map_url', 'booking_url', 'gallery'];
    }

    protected function editUrl(Model $model): string
    {
        return '/events/'.$model->getKey();
    }
}
