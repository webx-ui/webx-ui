<?php

declare(strict_types=1);

namespace WebxUi\Events\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Panel\Duplicate;
use WebxUi\Events\Panel\EventForm;
use WebxUi\Events\Panel\EventList;
use WebxUi\Events\Panel\Revision;
use WebxUi\Events\Rendering\When;
use WebxUi\Events\Support\Moment;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * What an agent can do with the events (§4.11).
 *
 * The same doors the panel uses: the list is {@see EventList}, the values go through
 * {@see EventForm} — so the screen checks them, a project's field lands in `extra`, and the
 * categories and the services wait in the draft with the text until somebody publishes. A copy is
 * {@see Duplicate}, the code behind the panel's "Duplicate".
 *
 * Creating is the row and its values in one transaction, and so is a save: a value the screen
 * refuses must not leave a bare event behind with its address already taken (the lesson of
 * `services_create`). A copy is one transaction of its own.
 *
 * The dates are the part an agent gets wrong without being told, so every tool that writes them
 * says how they are read: ISO 8601, with an offset or without one — and without one, in the
 * site's own timezone, named in the description rather than left to be guessed.
 */
final class EventTools
{
    /** How many events one page of `events_list` holds: the past ones pile up for years. */
    public const PER_PAGE = 50;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $event = [
            'type' => ['integer', 'string'],
            'description' => 'The event: its id, or the address it answers at — "/events/spring-cooking-class".',
        ];
        $category = [
            'type' => ['integer', 'string'],
            'description' => 'A category: its id or its slug. event_categories_list and events://catalog have both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string (the default language), or every language as { "en": "…", "ru": "…" }.',
        ];
        $dates = sprintf(
            'Dates are ISO 8601: "2026-10-12T10:00:00+03:00". A date without an offset is read in the '
            .'site\'s timezone, %s. starts_at is optional — an event without one is "dates to be announced" '
            .'(say it in date_note) and always stands first among the events to come; ends_at needs a '
            .'starts_at and cannot be before it; without ends_at the event is over the moment it starts. '
            .'With all_day true the times are dropped and the event covers whole days: send the first and '
            .'the last day, e.g. "2026-10-12" and "2026-10-14". date_note, when written, is printed instead '
            .'of the date, but the order, "over" and the calendar still go by the date.',
            Moment::zone(),
        );
        $fields = 'attendance is "offline", "online" or "mixed" — the venue, address and map_url are shown '
            .'only when people come in person; price is the words the page prints ("HK$480 per person"), '
            .'price_amount an optional number only for search engines (0 means free); booking_url is where '
            .'"Book" leads. highlights ("What to expect") is a list of { "title": …, "text": … }, each a '
            .'string or a map of languages; description is HTML. categories — ids or slugs, the main one '
            .'first; services — ids or addresses ("/services/nutrition-plan"). Both wait in the draft like '
            .'the text.';

        return [
            Tool::read(
                'list',
                'The events, a page at a time, in the order the site shows them: the ones to come by default — '
                .'those without a date first, then from the nearest — or the past ones from the last, or all. '
                .'Each with what it is called in every language, the address it answers at, whether it is on '
                .'the site, when it is as the site prints it, and its categories — the first the main one. '
                .'Read this (or events://catalog) first: an event is named by its address, and a second one '
                .'on the same date is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'when' => ['type' => 'string', 'enum' => ['upcoming', 'past', 'all'], 'description' => 'The events to come (the default), the ones that are over, or every one.'],
                    'search' => ['type' => 'string', 'description' => 'Events whose title or address contains this, in any language the site has.'],
                    'status' => ['type' => 'string', 'enum' => [
                        Event::STATUS_DRAFT,
                        Event::STATUS_PUBLISHED,
                        Event::STATUS_MODIFIED,
                        Event::STATUS_UNPUBLISHED,
                    ], 'description' => 'Never published · on the site (edits waiting or not) · on the site with edits waiting · taken off it.'],
                    'category' => $category + ['description' => 'Only the events in this category: its id or its slug.'],
                    'service' => ['type' => ['integer', 'string'], 'description' => 'Only the events linked to this service: its id or its address.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first. An event in the bin has no address.'],
                    'page' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Which page, from 1. The answer says how many there are.'],
                ]],
                permission: ['events.view', 'events.manage'],
            ),

            Tool::read(
                'get',
                'One event in full: the values of its editor — title, address, lead, the dates, the place, the '
                .'price and the booking link, the description and "What to expect" in every language, the gallery '
                .'(the first picture is the cover), the categories, the linked services, the SEO card, any field '
                .'the project added — the revision those values are, and a link to the draft as the site would '
                .'print it.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => ['event' => $event], 'required' => ['event']],
                permission: ['events.view', 'events.manage'],
            ),

            Tool::mutating(
                'create',
                'Start an event. It is a draft: nothing is on the site until somebody publishes it. The address '
                .'is made from the title when you do not write one, and an address a category, a page or another '
                .'event already answers at is refused rather than given a suffix — events and their categories '
                .'share one level. For the next date of an event that already exists, use events_duplicate '
                .'instead. '.$dates,
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text,
                    'slug' => $text + ['description' => 'The last segment of the address, per language; made from the title when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The rest of the editor\'s fields as events_get returns them — lead, starts_at, ends_at, all_day, date_note, attendance, venue, address, map_url, price, price_amount, booking_url, gallery, description, highlights, categories, services, the SEO card, the project\'s fields. '.$fields],
                ], 'required' => ['title']],
                permission: 'events.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of an event into its draft. A field left out keeps what it had; a localized '
                .'field sent as { "en": "…" } changes that language only. Send the revision events_get gave you '
                .'and the write is refused if somebody saved in between. Everything — the categories and the '
                .'services included — reaches the site when the event is published. '.$dates,
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'event' => $event,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as events_get returns them. '.$fields],
                    'revision' => ['type' => 'string', 'description' => 'The revision events_get returned. Left out, the write goes in over whatever happened since.'],
                ], 'required' => ['event', 'values']],
                permission: 'events.manage',
            ),

            Tool::mutating(
                'duplicate',
                'Copy an event into a new draft — the way the next date of a repeated event is made: every field, '
                .'its categories, services and SEO card, the same title, and an address with the next free suffix '
                .'("-2", "-3") in every language. The copy has no history and is not on the site. Change its dates '
                .'with events_update, then publish it.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->duplicate($arguments, $user)),
                ['properties' => ['event' => $event], 'required' => ['event']],
                permission: 'events.manage',
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site: its values, categories and services become the event and a version is '
                .'written. Ask a person first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => ['event' => $event], 'required' => ['event']],
                permission: 'events.manage',
            ),

            Tool::mutating(
                'unpublish',
                'Take an event off the site. It answers 404 from then on and leaves the lists; its address stays '
                .'reserved and whatever was being prepared is still there. An event that is simply over does not '
                .'need this: it leaves the lists of events to come by itself and its page stays as a report.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['event' => $event], 'required' => ['event']],
                permission: 'events.manage',
            ),

            Tool::mutating(
                'delete',
                'Put an event in the bin. Its address is released, so afterwards it can only be named by its id. '
                .'Nothing is destroyed: the bin in the panel puts it back, as long as nobody has taken its address '
                .'in the meantime.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['event' => $event], 'required' => ['event']],
                permission: 'events.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $when = (string) ($arguments['when'] ?? 'upcoming');

        if (! in_array($when, ['upcoming', 'past', 'all'], true)) {
            throw new ToolFailure('`when` is "upcoming", "past" or "all".');
        }

        $query = [
            'when' => $when,
            'q' => (string) ($arguments['search'] ?? ''),
            'status' => (string) ($arguments['status'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            $query['category'] = (string) $category->getKey();
        }

        if (($arguments['service'] ?? null) !== null && $arguments['service'] !== '') {
            $query['service'] = (string) $this->service($arguments['service']);
        }

        $page = max(1, (int) ($arguments['page'] ?? 1));

        /** @var LengthAwarePaginator<int, Event> $events */
        $events = $this->container->make(EventList::class)
            ->build(Request::create('/', 'GET', $query))
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'timezone' => Moment::zone(),
            'when' => $query['trashed'] === '1' ? 'bin' : $when,
            'page' => $events->currentPage(),
            'pages' => $events->lastPage(),
            'total' => $events->total(),
            'events' => array_map(fn (Event $event): array => $this->summary($event), $events->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user = null): array
    {
        $event = $this->event($arguments['event'] ?? null);

        return [
            'event' => $this->summary($event),
            'values' => $this->form()->values($event),
            // Send it back with events_update, and a write over somebody else's is refused.
            'revision' => Revision::of($event),
            'preview_url' => $this->preview($event, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $title = $this->text($arguments['title'] ?? null, 'title');
        $slug = isset($arguments['slug']) ? $this->text($arguments['slug'], 'slug') : $this->slugFrom($title);
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => ['title' => $title, 'slug' => $slug, 'fields' => array_keys($values)],
                'would_answer_at' => $this->addresses($slug),
            ];
        }

        $event = new Event;
        $event->setTranslations('title', $title);
        $event->setTranslations('slug', $slug);

        // One transaction for the row and its values: the routing observer writes the address on
        // `created`, inside this same transaction, so a refused value takes the address with it.
        $event->getConnection()->transaction(function () use ($event, $values, $user): void {
            $event->save();

            if ($values !== []) {
                $this->form()->save($event, $values, $this->can($user), $this->authorId($user));
            }
        });

        return $this->get(['event' => $event->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $event = $this->event($arguments['event'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. events_get says what the fields are.');
        }

        if ($event->trashed()) {
            throw new ToolFailure("Event #{$event->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        $values = $this->prepare($values);
        $this->sameRevision($arguments, $event);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_write' => 'draft',
                'fields' => array_keys($values),
                'event' => $this->reference($event),
            ];
        }

        $event->getConnection()->transaction(
            fn () => $this->form()->save($event, $values, $this->can($user), $this->authorId($user)),
        );

        return $this->get(['event' => $event->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function duplicate(array $arguments, ?Authenticatable $user): array
    {
        $event = $this->event($arguments['event'] ?? null);

        if ($event->trashed()) {
            throw new ToolFailure("Event #{$event->getKey()} is in the bin. Bring it back in the panel before copying it.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_copy' => $this->reference($event), 'as' => 'draft'];
        }

        $copy = $this->container->make(Duplicate::class)->of($event);

        return ['copied_from' => (int) $event->getKey(), ...$this->get(['event' => $copy->getKey()], $user)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $event = $this->event($arguments['event'] ?? null);

        if ($event->trashed()) {
            throw new ToolFailure("Event #{$event->getKey()} is in the bin. Bring it back in the panel before publishing it.");
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->reference($event),
                'status' => $event->status(),
                'has_waiting_edits' => $event->hasDraft(),
            ];
        }

        $event->publish($this->authorId($user), EntityVersion::SOURCE_MCP);

        return ['event' => $this->summary($event->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $event = $this->event($arguments['event'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->reference($event), 'status' => $event->status()];
        }

        $event->unpublish();

        return ['event' => $this->summary($event->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $event = $this->event($arguments['event'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($event), 'status' => $event->status()];
        }

        $event->delete();

        return ['trashed' => true, 'id' => (int) $event->getKey()];
    }

    /**
     * The values as the screen wants them. A plain string in a translated field is the default
     * language, as in events_create — not the language of a request that never chose one; the
     * same inside each card of "What to expect". The categories may be named by slug and the
     * services by address; they reach the screen as ids.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values): array
    {
        if (array_key_exists('blocks', $values)) {
            throw new ToolFailure('An event has no blocks: its page is drawn by the module\'s view. Write its fields instead — events_get lists them.');
        }

        $default = $this->locales()->defaultCode();
        $inDefault = static fn (mixed $value): mixed => is_string($value) ? [$default => $value] : $value;

        foreach ($values as $field => $value) {
            if (in_array($field, Event::TRANSLATED, true)) {
                $values[$field] = $inDefault($value);
            }
        }

        if (array_key_exists('highlights', $values)) {
            if (! is_array($values['highlights'])) {
                throw new ToolFailure('`highlights` is a list of { "title": …, "text": … }. An empty list clears it.');
            }

            $values['highlights'] = array_values(array_map(static function (mixed $card) use ($inDefault): array {
                if (! is_array($card)) {
                    throw new ToolFailure('Each card of `highlights` is { "title": …, "text": … }.');
                }

                return ['title' => $inDefault($card['title'] ?? null) ?? [], 'text' => $inDefault($card['text'] ?? null) ?? []];
            }, $values['highlights']));
        }

        if (array_key_exists('attendance', $values) && ! in_array($values['attendance'], Event::ATTENDANCE, true)) {
            throw new ToolFailure('`attendance` is "offline", "online" or "mixed".');
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $values) && $values[$field] !== null && $values[$field] !== '') {
                if (! is_string($values[$field]) || Moment::tryFrom($values[$field]) === null) {
                    throw new ToolFailure("`{$field}` is a date in ISO 8601 — \"2026-10-12T10:00:00+03:00\" — or null.");
                }
            }
        }

        // Named as an agent reads them off the catalogue: a category by its slug. The first
        // category stays the main one, so the order is kept.
        if (array_key_exists('categories', $values)) {
            if (! is_array($values['categories'])) {
                throw new ToolFailure('`categories` is a list of ids or slugs, the main one first. An empty list clears it.');
            }

            $values['categories'] = array_values(array_map(
                fn (mixed $one): int => (int) $this->category($one)?->getKey(),
                $values['categories'],
            ));
        }

        if (array_key_exists(Event::SERVICES, $values)) {
            if (! is_array($values[Event::SERVICES])) {
                throw new ToolFailure('`services` is a list of ids or addresses, first to last. An empty list clears it.');
            }

            $values[Event::SERVICES] = array_values(array_map(fn (mixed $one): int => $this->service($one), $values[Event::SERVICES]));
        }

        return $values;
    }

    /**
     * A service by id or by the address it answers at — the address is what an agent reads off
     * `services://catalog`.
     */
    private function service(mixed $reference): int
    {
        $target = $this->container->make(RelationTargets::class)->find('service');

        if (! $target instanceof RelationTarget) {
            throw new ToolFailure('This site has no services module, so an event cannot be linked to a service.');
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $exists = ($target->model)::query()->whereKey((int) $reference)->exists();

            return $exists ? (int) $reference : throw new ToolFailure("No service has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A service is an id, or the address it answers at.');
        }

        $id = $this->answeringAt($reference, new ($target->model));

        return $id ?? throw new ToolFailure('No service answers at [/'.UrlNormaliser::key($reference).'].');
    }

    /**
     * An event by id or by address.
     */
    private function event(mixed $reference): Event
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $event = Event::withTrashed()->find((int) $reference);

            return $event instanceof Event
                ? $event
                : throw new ToolFailure("No event has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('An event is an id, or an address like "/events/spring-cooking-class".');
        }

        $id = $this->answeringAt($reference, new Event);
        $event = $id === null ? null : Event::query()->find($id);

        return $event instanceof Event
            ? $event
            : throw new ToolFailure(
                'No event answers at [/'.UrlNormaliser::key($reference).']. events_list has the addresses; an event in the bin has none.'
            );
    }

    /** The id of the record of this model the registry has at this address, in any language. */
    private function answeringAt(string $address, Model $model): ?int
    {
        $id = Route::query()
            ->where('path', UrlNormaliser::key($address))
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', $model->getMorphClass())
            ->value('entity_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * A category by id or by slug in any language — the slug is what an agent read off an address.
     */
    private function category(mixed $reference): ?EventCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $category = EventCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            // The last segment: `events/cooking-classes` and `cooking-classes` name the same category.
            $slug = Str::afterLast(trim(UrlNormaliser::key($reference), '/'), '/');
            $category = EventCategory::query()->whereTranslationLikeAny('slug', $slug)->first();
        } else {
            throw new ToolFailure('`category` is an id or a slug.');
        }

        return $category instanceof EventCategory
            ? $category
            : throw new ToolFailure('No such category. event_categories_list says what there is.');
    }

    /**
     * One event as an agent needs it — every language at once, the draft's title and dates where
     * there is a draft, and the registry's addresses, which are what the site answers at right now.
     *
     * @return array<string, mixed>
     */
    private function summary(Event $event): array
    {
        $event->loadMissing(['routes', 'categories']);
        $shown = $event->hasDraft() ? $event->withDraft() : $event;
        $locale = $this->locales()->current();

        $summary = [
            'id' => (int) $event->getKey(),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'urls' => $this->urls($event),
            'status' => $event->status(),
            'has_draft' => $event->hasDraft(),
            'starts_at' => $shown->starts_at?->toAtomString(),
            'ends_at' => $shown->ends_at?->toAtomString(),
            'all_day' => (bool) $shown->all_day,
            // As the site prints it — `date_note` over the dates.
            'when' => When::of($shown, $locale),
            'past' => $shown->isPast(),
            'attendance' => $shown->attendance,
            'updated_at' => $event->updated_at?->toAtomString(),
            // The first is the main one: the breadcrumbs go through it.
            'categories' => $event->categories
                ->map(static fn (EventCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->getTranslations('title'),
                    'slug' => $category->getTranslations('slug'),
                ])
                ->values()
                ->all(),
        ];

        if ($event->trashed()) {
            $summary['deleted_at'] = $event->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * @return array<string, array{path: string, url: string}>
     */
    private function urls(Event $event): array
    {
        $urls = [];

        foreach ($event->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = ['path' => '/'.$route->path, 'url' => $event->url($route->locale)];
            }
        }

        return $urls;
    }

    private function reference(Event $event): string
    {
        $urls = $this->urls($event);
        $address = $urls[$this->locales()->defaultCode()] ?? reset($urls);

        return sprintf('#%s (%s)', $event->getKey(), is_array($address) ? $address['path'] : 'no address');
    }

    /**
     * Where an event with these slugs would answer, before it exists — what a dry run reports.
     *
     * @param  array<string, string>  $slug
     * @return array<string, string>
     */
    private function addresses(array $slug): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-events.prefix', 'events'));

        return array_map(
            static fn (string $one): string => '/'.UrlNormaliser::join($prefix, $one),
            $slug,
        );
    }

    /**
     * Run a change, and turn a refusal into something the agent can read: the screen refusing a
     * value and the registry refusing an address are both a `ValidationException`.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (CategoryException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sameRevision(array $arguments, Event $event): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = Revision::of($event);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The event changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with events_get and redo the edit on what is there now.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * @param  array<string, string>  $title
     * @return array<string, string>
     */
    private function slugFrom(array $title): array
    {
        return array_filter(array_map(static fn (string $text): string => Str::slug($text), $title));
    }

    /** Only with `module-blocks`, which draws previews; an event it cannot sign is still worth reading. */
    private function preview(Event $event, ?Authenticatable $user): ?string
    {
        if (! class_exists(Preview::class)) {
            return null;
        }

        try {
            return Preview::url($event, $this->authorId($user));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a card its administrator could not.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function form(): EventForm
    {
        return $this->container->make(EventForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
