<?php

declare(strict_types=1);

namespace WebxUi\Events\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Rendering\When;

/**
 * Events, for whatever points at them — reviews and questions of a later day (§7). The picker
 * tells two events of a series apart by their date and their cover.
 */
final class EventTarget extends RelationTarget
{
    public function __construct()
    {
        parent::__construct(
            key: Event::TYPE,
            model: Event::class,
            permission: 'events.view',
            label: 'webx-events::relations.event',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Event::query();

        return $query;
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        if (! $record instanceof Event) {
            return null;
        }

        $when = When::of($record, $locale);

        return $when === '' ? null : $when;
    }

    protected function thumb(Model $record): ?string
    {
        $cover = $record instanceof Event ? $record->cover() : null;
        $thumb = $cover['thumb'] ?? $cover['url'] ?? null;

        return is_string($thumb) ? $thumb : null;
    }
}
