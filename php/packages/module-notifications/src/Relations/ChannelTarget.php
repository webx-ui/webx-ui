<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Providers;

/**
 * The channels a consumer may choose for one of its events (§15.2): the channels of that event and
 * the ones for any event, never the bin — `wx-notifications-channels` and the field «Notify
 * through» of a form.
 *
 * One target per event, registered with the event: the address of a target is `[a-z0-9-]+`, so
 * `inbox.submission` is `notify-inbox-submission`. No permission: the picker answers with a name,
 * a provider and whether the channel is on — never recipients or secrets (§11), so choosing a
 * channel is the consumer's right, not the module's.
 */
final class ChannelTarget extends RelationTarget
{
    public const PREFIX = 'notify-';

    public function __construct(
        public readonly string $event,
        private readonly Providers $providers,
    ) {
        parent::__construct(
            key: self::keyFor($event),
            model: Channel::class,
            permission: null,
            label: 'webx-notifications::module.channel',
        );
    }

    /** `inbox.submission` → `notify-inbox-submission`. */
    public static function keyFor(string $event): string
    {
        return self::PREFIX.str_replace(['.', '_'], '-', $event);
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $event = $this->event;

        /** @var Builder<Model> $query */
        $query = Channel::query()->where(static function (Builder $where) use ($event): void {
            $where->whereNull('event')->orWhere('event', $event);
        });

        return $query;
    }

    /**
     * The row, with the channel's provider and state under their own names: the field draws an
     * icon by the one and a mark by the other. `mark` is the word for a channel that is off — the
     * field's own «not on the site» is about pages, and a channel is not on any site.
     *
     * @return array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool, provider?: string, enabled?: bool, mark?: string|null}
     */
    public function row(Model $record, string $locale): array
    {
        $row = parent::row($record, $locale);

        if ($record instanceof Channel) {
            $row['provider'] = $record->provider;
            $row['enabled'] = $record->enabled;
            $row['mark'] = $record->enabled ? null : (string) __('webx-notifications::panel.off');
        }

        return $row;
    }

    /** A switched-off channel stays chosen and sends nothing — marked in the picker. */
    public function visible(Model $record, ?string $locale = null): bool
    {
        return $record instanceof Channel && ! $record->trashed() && $record->enabled;
    }

    protected function title(Model $record, string $locale): string
    {
        return $record instanceof Channel && trim($record->name) !== '' ? $record->name : '#'.$record->getKey();
    }

    /** The provider, and «any event» for a channel that serves every consumer. */
    protected function subtitle(Model $record, string $locale): ?string
    {
        if (! $record instanceof Channel) {
            return null;
        }

        $provider = $this->providers->find($record->provider);
        $parts = [$provider === null ? $record->provider : (string) __($provider->label())];

        if ($record->event === null) {
            $parts[] = (string) __('webx-notifications::screen.any-event');
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $query->where($query->getModel()->qualifyColumn('name'), 'like', $like);
    }
}
