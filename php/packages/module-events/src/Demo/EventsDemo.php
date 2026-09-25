<?php

declare(strict_types=1);

namespace WebxUi\Events\Demo;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Support\Moment;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Services\Models\Service;

/**
 * Three categories and six events (§4.12), as omnivitality's formats are: breakfast meetups,
 * cooking classes, private events.
 *
 * Each event is there to show one rule: one next week with its hours and a price as a number, one
 * of three whole days in two categories, one online and free, one with no date and a line of words
 * instead, one that is over — with the photos, its report — and one still a draft.
 *
 * The dates are counted from the moment the demo is seeded, not written down: a demo with fixed
 * dates is a demo of the past a month later, and the lists of events to come would stand empty.
 *
 * What else there is decides the rest, which is why {@see requires()} is worked out rather than
 * written down: with `module-services`, two events are linked to the demo services.
 */
final class EventsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * The modules whose demo has to be there before this one's. Named only when installed — a
     * name `requires()` gives that is not installed skips the whole demo, and events have
     * something to show without any of them but the library.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        $requires = ['media'];

        if ($this->modules->has('services')) {
            $requires[] = 'services';
        }

        return $requires;
    }

    public function seed(DemoLedger $ledger): void
    {
        // Events with anything in them are somebody's events.
        if (Event::withTrashed()->exists() || EventCategory::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $pictures = $this->pictures($ledger);
        $services = $this->services($ledger);

        /** @var array<string, EventCategory> $categories */
        $categories = [];
        $position = 0;

        foreach ($this->list($document['categories'] ?? null) as $input) {
            $category = $this->category($input, $position++, $ledger);

            if ($category !== null) {
                $categories[(string) $input['key']] = $category;
            }
        }

        // One "now" for every date, so the demo's events keep their distances from each other.
        $today = Carbon::now(Moment::zone())->startOfDay();

        foreach ($this->list($document['events'] ?? null) as $input) {
            $this->event($input, $today, $pictures, $categories, $services, $ledger);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function category(array $input, int $position, DemoLedger $ledger): ?EventCategory
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return null;
        }

        $category = EventCategory::query()->create([
            'title' => $title,
            'slug' => array_fill_keys(array_keys($title), $key),
            'lead' => $this->words($input['lead'] ?? null),
            'is_visible' => true,
            'position' => $position,
        ]);

        $ledger->created($category, $key);

        return $category;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<MediaFile>  $pictures
     * @param  array<string, EventCategory>  $categories
     * @param  array<string, int>  $services
     */
    private function event(array $input, Carbon $today, array $pictures, array $categories, array $services, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($slug === '' || $title === null) {
            return;
        }

        $gallery = [];

        foreach ($this->list($input['gallery'] ?? null, numbers: true) as $index) {
            if (isset($pictures[$index])) {
                $gallery[] = ['path' => $pictures[$index]->path];
            }
        }

        $highlights = [];

        foreach ($this->list($input['highlights'] ?? null) as $card) {
            $highlights[] = ['title' => $this->words($card['title'] ?? null) ?? [], 'text' => $this->words($card['text'] ?? null) ?? []];
        }

        $event = new Event([
            'title' => $title,
            // The same slug in every language the event is written in: no slug, no address there.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'lead' => $this->words($input['lead'] ?? null),
            'gallery' => $gallery === [] ? null : $gallery,
            'starts_at' => $this->moment($input['starts'] ?? null, $today),
            'ends_at' => $this->moment($input['ends'] ?? null, $today),
            'all_day' => ($input['all_day'] ?? false) === true,
            'date_note' => $this->words($input['date_note'] ?? null),
            'attendance' => is_string($input['attendance'] ?? null) ? $input['attendance'] : Event::OFFLINE,
            'venue' => $this->words($input['venue'] ?? null),
            'address' => $this->words($input['address'] ?? null),
            'map_url' => is_string($input['map_url'] ?? null) ? $input['map_url'] : null,
            'description' => $this->words($input['description'] ?? null),
            'highlights' => $highlights === [] ? null : $highlights,
            'price' => $this->words($input['price'] ?? null),
            'price_amount' => is_int($input['price_amount'] ?? null) || is_float($input['price_amount'] ?? null) ? $input['price_amount'] : null,
            'booking_url' => is_string($input['booking_url'] ?? null) ? $input['booking_url'] : null,
        ]);
        $event->save();

        $ledger->created($event, $slug);

        // In the order of the file: the first is the main one, and the breadcrumbs go through it.
        $filed = array_values(array_filter(array_map(
            static fn (string $key): ?int => isset($categories[$key]) ? (int) $categories[$key]->getKey() : null,
            $this->list($input['categories'] ?? null, strings: true),
        )));

        if ($filed !== []) {
            $event->syncCategories($filed);
        }

        $linked = array_values(array_filter(array_map(
            static fn (string $one): ?int => $services[$one] ?? null,
            $this->list($input['services'] ?? null, strings: true),
        )));

        if ($linked !== []) {
            $event->syncRelated(Event::SERVICES, 'service', $linked);
        }

        if (($input['draft'] ?? false) === true) {
            return;
        }

        // Published after the categories and the links, so the first version records them.
        $event->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');
        $ledger->createdVersionsOf($event);
    }

    /**
     * `{ "in_days": 7, "at": "10:00" }` as a moment in the site's zone, counted from today; a day
     * with no hour is its midnight, which is what an event of days wants.
     */
    private function moment(mixed $when, Carbon $today): ?Carbon
    {
        if (! is_array($when) || ! is_int($when['in_days'] ?? null)) {
            return null;
        }

        $moment = $today->copy()->addDays($when['in_days']);

        return is_string($when['at'] ?? null) ? $moment->setTimeFromTimeString($when['at']) : $moment;
    }

    /**
     * The demo services by slug — only the ones the services demo made a moment ago: somebody's
     * real service is not the place to hang an example.
     *
     * @return array<string, int>
     */
    private function services(DemoLedger $ledger): array
    {
        if (! in_array('services', $this->requires(), true) || ! class_exists(Service::class)) {
            return [];
        }

        $ids = $ledger->idsOf('services', Service::class);

        if ($ids === []) {
            return [];
        }

        $default = $this->locales->defaultCode();
        $services = [];

        foreach (Service::query()->whereKey($ids)->get() as $service) {
            $slug = $service->getTranslation('slug', $default, false);

            if (is_string($slug) && $slug !== '') {
                $services[$slug] = (int) $service->getKey();
            }
        }

        return $services;
    }

    /**
     * The pictures the library seeded a moment ago: the wide one first, then the square. A site
     * whose library already held those bytes gets events without pictures rather than somebody
     * else's.
     *
     * @return list<MediaFile>
     */
    private function pictures(DemoLedger $ledger): array
    {
        $ids = $ledger->idsOf('media', MediaFile::class);

        if ($ids === []) {
            return [];
        }

        /** @var list<MediaFile> $files */
        $files = MediaFile::query()->whereKey($ids)->orderBy('id')->get()->all();

        return $files;
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do, rather than nameless events.
     *
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        if (! is_array($text)) {
            return null;
        }

        $codes = $this->locales->codes();
        $words = [];

        foreach ($text as $locale => $value) {
            if (is_string($value) && trim($value) !== '' && in_array((string) $locale, $codes, true)) {
                $words[(string) $locale] = trim($value);
            }
        }

        if ($words === [] && is_string($text['en'] ?? null) && trim($text['en']) !== '') {
            return [$this->locales->defaultCode() => trim($text['en'])];
        }

        return $words === [] ? null : $words;
    }

    /**
     * @return ($strings is true ? list<string> : ($numbers is true ? list<int> : list<array<string, mixed>>))
     */
    private function list(mixed $value, bool $strings = false, bool $numbers = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keep = match (true) {
            $strings => is_string(...),
            $numbers => is_int(...),
            default => is_array(...),
        };

        return array_values(array_filter($value, $keep));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/events.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('events.json is not a set of events.');
        }

        return $document;
    }
}
