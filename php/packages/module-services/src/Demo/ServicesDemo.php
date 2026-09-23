<?php

declare(strict_types=1);

namespace WebxUi\Services\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * Three categories and eight services (§4.12) — enough for the two orders to be visible.
 *
 * One service is filed under two categories and stands in a different place in each: last among
 * the websites, first in support. That is decision 5 of the spec as something a new site can see,
 * and it is the case that breaks first when somebody "simplifies" the order back into one column.
 *
 * The covers come out of the journal, as the blog's does: the library seeded its two pictures a
 * moment ago, and a site whose library already held those bytes gets services without covers
 * rather than somebody else's pictures on them.
 */
final class ServicesDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A catalogue with anything in it is somebody's catalogue.
        if (Service::withTrashed()->exists() || ServiceCategory::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $covers = $this->covers($ledger);

        /** @var array<string, Service> $services */
        $services = [];

        // In the order of the file, which is the order of the whole list: every new service goes
        // to the end of it.
        foreach ($this->list($document['services'] ?? null) as $input) {
            $service = $this->service($input, $covers, $this->list($document['blocks'] ?? null), $ledger);

            if ($service !== null) {
                $services[(string) $input['slug']] = $service;
            }
        }

        $position = 0;

        foreach ($this->list($document['categories'] ?? null) as $input) {
            $this->category($input, $position++, $services, $ledger);
        }

        foreach ($services as $service) {
            // Published after the categories, so the first version records them.
            $service->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');
            $ledger->createdVersionsOf($service);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<MediaFile>  $covers
     * @param  list<array<string, mixed>>  $blocks
     */
    private function service(array $input, array $covers, array $blocks, DemoLedger $ledger): ?Service
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';

        if ($slug === '') {
            return null;
        }

        $cover = is_int($input['cover'] ?? null) ? ($covers[$input['cover']] ?? null) : null;

        $service = Service::query()->create([
            'title' => $this->words($input['title'] ?? $slug),
            'slug' => $this->words($slug),
            'lead' => $this->words($input['lead'] ?? null),
            'cover_id' => $cover?->getKey(),
            // The keys of a block are unique within the entity it lives in, not across the site,
            // but a prefix keeps them readable in blocks_get_content all the same.
            'blocks' => array_map(
                static fn (array $block): array => ['key' => $slug.'-'.$block['key']] + $block,
                $blocks,
            ),
        ]);

        $ledger->created($service, $slug);

        return $service;
    }

    /**
     * A category, and its services in the order the file lists them — which is its own order,
     * not the order of the whole list.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, Service>  $services
     */
    private function category(array $input, int $position, array $services, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';

        if ($slug === '') {
            return;
        }

        $category = ServiceCategory::query()->create([
            'title' => $this->words($input['title'] ?? $slug),
            'slug' => $this->words($slug),
            'lead' => $this->words($input['lead'] ?? null),
            'is_visible' => true,
            'position' => $position,
        ]);

        $ledger->created($category, $slug);

        $members = [];

        foreach ($this->list($input['services'] ?? null, strings: true) as $name) {
            if (isset($services[$name])) {
                $members[] = $services[$name];
            }
        }

        foreach ($members as $service) {
            // Appended, so a category named second stays second: the first one a service is
            // filed under is its main one.
            $ids = $service->categories()->pluck('id')->map(intval(...))->all();
            $service->syncCategories([...$ids, (int) $category->getKey()]);
        }

        Ordering::move(
            Service::class,
            array_map(static fn (Service $service): int => (int) $service->getKey(), $members),
            (int) $category->getKey(),
        );
    }

    /**
     * The pictures the library seeded a moment ago: the wide one first, then the square.
     *
     * @return list<MediaFile>
     */
    private function covers(DemoLedger $ledger): array
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
     * @return ($strings is true ? list<string> : list<array<string, mixed>>)
     */
    private function list(mixed $value, bool $strings = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, $strings ? is_string(...) : is_array(...)));
    }

    /**
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        $text = is_string($text) ? trim($text) : '';

        return $text === '' ? null : [$this->locales->defaultCode() => $text];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/services.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('services.json is not a catalogue.');
        }

        return $document;
    }
}
