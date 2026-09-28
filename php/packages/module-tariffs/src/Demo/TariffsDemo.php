<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Services\Models\Service;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Panel\TariffsModule;

/**
 * One group and three tariffs (§5.6) — the sample the spec was written from — and the tariffs
 * block put where a site would put it.
 *
 * Each tariff shows a rule: Starter has a line of "what is included" written only in English, so
 * the Russian list is a line shorter (decision 12); Growth is recommended and linked to a demo
 * service; Enterprise has no number, and its words stand in for one.
 *
 * The buttons point at a page by entity — the pages demo's, else the first page the address
 * registry has (as the banners do, since a live site's pages demo answers "nothing to seed") — so
 * their address comes out of the registry; a site without pages gets no buttons rather than
 * buttons to an address that answers 404.
 *
 * Then the block, whose type is the one this module offers, installed here when the site has not
 * taken it yet. With `module-pages`, a page `/pricing` with every tariff in a slider — a page of
 * prices is an ordinary page (decision 1). With `module-services`, one demo service gets "what it
 * costs": a grid of only the tariffs linked to the service whose page it is.
 */
final class TariffsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly BlockOffers $offers,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A site with any tariff at all has priced its own work.
        if (Tariff::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $services = $this->services($ledger);
        $page = $this->linkedPage($ledger);
        $group = $this->group($document['group'] ?? null, $ledger);

        // In the order of the file, which is the order of the group.
        foreach ($this->list($document['tariffs'] ?? null) as $input) {
            $this->tariff($input, $group, $services, $page, $ledger);
        }

        if ($page === null) {
            $ledger->note('the demo tariffs have no buttons: the site has no page for them to lead to.');
        }

        if (! in_array('blocks', $this->requires(), true) || ! $this->blockType($ledger)) {
            return;
        }

        $pricing = $document['page'] ?? null;

        if (is_array($pricing) && class_exists(Page::class)) {
            $this->page($pricing, $ledger);
        }

        $service = $document['service'] ?? null;

        if (is_array($service) && $services !== []) {
            $this->service($service, $services, $ledger);
        }
    }

    /**
     * The modules whose demo has to be there before this one's. Named only when installed — a
     * name `requires()` gives that is not installed skips the whole demo, and the tariffs have
     * something to show without any of them.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        $requires = [];

        // The page is made of the block, so it takes both.
        if ($this->modules->has('blocks') && $this->modules->has('pages')) {
            $requires[] = 'blocks';
            $requires[] = 'pages';
        }

        if ($this->modules->has('services')) {
            $requires[] = 'services';
        }

        return $requires;
    }

    /**
     * @param  array<string, mixed>|mixed  $input
     */
    private function group(mixed $input, DemoLedger $ledger): ?TariffCategory
    {
        $title = is_array($input) ? $this->words($input['title'] ?? null) : null;

        if ($title === null) {
            return null;
        }

        $group = TariffCategory::query()->create(['title' => $title, 'is_visible' => true]);
        $ledger->created($group, is_string($input['key'] ?? null) ? $input['key'] : 'the group');

        return $group;
    }

    /**
     * Written straight to the model rather than through the form, as the other demos do: the
     * rows and the button are in the file in the shape they are stored in.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, int>  $services
     */
    private function tariff(array $input, ?TariffCategory $group, array $services, ?int $page, DemoLedger $ledger): void
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $name = $this->words($input['name'] ?? null);

        if ($key === '' || $name === null) {
            return;
        }

        $features = [];

        foreach ($this->list($input['features'] ?? null) as $line) {
            $words = $this->words($line);

            if ($words !== null) {
                $features[] = ['text' => $words];
            }
        }

        $button = is_array($input['button'] ?? null) ? $input['button'] : [];
        $label = $page === null ? null : $this->words($button['label'] ?? null);

        $tariff = Tariff::query()->create([
            'name' => $name,
            'badge' => $this->words($input['badge'] ?? null),
            'price' => is_int($input['price'] ?? null) || is_float($input['price'] ?? null) ? $input['price'] : null,
            'currency' => is_string($input['currency'] ?? null) ? $input['currency'] : null,
            'period' => $this->words($input['period'] ?? null),
            'price_text' => $this->words($input['price_text'] ?? null),
            'features' => $features === [] ? null : $features,
            'description' => $this->words($input['description'] ?? null),
            'button_label' => $label,
            'button_link' => $label === null ? null : [
                'target' => 'entity', 'entity_type' => 'page', 'entity_id' => $page,
                'url' => null, 'hash' => null, 'new_tab' => false, 'rel' => [],
            ],
            'button_variant' => $label !== null && is_string($button['variant'] ?? null) ? $button['variant'] : null,
            'featured' => ($input['featured'] ?? false) === true,
            'published' => ($input['published'] ?? true) !== false,
        ]);

        $ledger->created($tariff, $key);

        if ($group instanceof TariffCategory) {
            $tariff->syncCategories([(int) $group->getKey()]);
        }

        $linked = array_values(array_filter(array_map(
            static fn (string $slug): ?int => $services[$slug] ?? null,
            $this->list($input['services'] ?? null, strings: true),
        )));

        if ($linked !== []) {
            $tariff->syncRelated(Tariff::SERVICES, 'service', $linked);
        }
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
     * The page the buttons lead to — not the home page: the pages demo's first, else the first
     * page the address registry has. A site that had its pages before the demo gets none from the
     * pages demo, and pointing a button at one of its own pages changes nothing on that page.
     */
    private function linkedPage(DemoLedger $ledger): ?int
    {
        if (! $this->modules->has('pages') || ! class_exists(Page::class)) {
            return null;
        }

        $ids = $ledger->idsOf('pages', Page::class);
        $id = $ids === [] ? null : Page::query()->whereKey($ids)->whereNotNull('parent_id')->orderBy('lft')->value('id');

        $id ??= Route::query()
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new Page)->getMorphClass())
            ->where('path', '!=', '')
            ->orderBy('id')
            ->value('entity_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The offered type, installed now if the site has not taken it — `webx:setup` usually has.
     * A type by that slug the site already has is left as it is, whatever it was made into.
     */
    private function blockType(DemoLedger $ledger): bool
    {
        foreach ($this->offers->documents([TariffsModule::ID]) as $offer) {
            if ($this->offers->install($offer) !== BlockOffers::PRESENT) {
                $block = Block::query()->where('slug', $offer['slug'])->first();

                if ($block instanceof Block) {
                    $ledger->created($block, 'the '.$offer['slug'].' block type');
                }
            }
        }

        if (! Block::query()->where('slug', 'tariffs')->where('is_enabled', true)->exists()) {
            $ledger->note('the tariffs are in the panel, but no tariffs block was put anywhere: the site has no enabled block type "tariffs".');

            return false;
        }

        return true;
    }

    /**
     * `/pricing` under the home page: every tariff, a slider in three columns.
     *
     * @param  array<string, mixed>  $input
     */
    private function page(array $input, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : 'pricing';
        $home = Page::home();

        if (! $home instanceof Page) {
            return;
        }

        if (app(Reserved::class)->taken($slug) || Page::withTrashed()->whereTranslationLikeAny('slug', $slug)->exists()) {
            $ledger->note("no page of prices was made: /{$slug} is already taken on this site.");

            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];
        $title = $this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => 'Pricing'];

        $page = new Page([
            'title' => $title,
            // The same slug in every language the page is written in: a page with no slug in a
            // language has no address in it, and the second language is where decision 12 shows.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'blocks' => [[
                'key' => 'tariffs-all',
                'type' => 'tariffs',
                'values' => array_filter([
                    'title' => $this->words($block['title'] ?? null),
                    'tariffs' => ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => false],
                    'layout' => 'slider',
                    'columns' => 3,
                    'includes_title' => $this->words($block['includes_title'] ?? null),
                    'featured_label' => $this->words($block['featured_label'] ?? null),
                ], static fn (mixed $value): bool => $value !== null),
            ]],
        ]);

        try {
            $page->appendTo($home);
        } catch (Throwable $refused) {
            $ledger->note("no page of prices was made: {$refused->getMessage()}");

            return;
        }

        $ledger->created($page, $slug);
        $page->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $seo = $input['seo'] ?? null;

        if (is_array($seo)) {
            // A page with an empty card has no <title> at all (CLAUDE.md §4).
            $card = array_filter([
                'title' => $this->words($seo['title'] ?? null),
                'description' => $this->words($seo['description'] ?? null),
            ]);

            if ($card !== []) {
                $page->saveSeo($card);
                $meta = $page->seo()->first();

                if ($meta instanceof SeoMeta) {
                    $ledger->created($meta, "what /{$slug} says about itself");
                }
            }
        }

        $ledger->createdVersionsOf($page);
    }

    /**
     * "What it costs" at the end of one demo service: a grid of only the tariffs linked to the
     * service whose page it is — the block answers the question itself, so the same block would do
     * on every service.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, int>  $services
     */
    private function service(array $input, array $services, DemoLedger $ledger): void
    {
        $slug = (string) ($input['slug'] ?? '');
        $id = $services[$slug] ?? null;
        $service = $id === null ? null : Service::query()->find($id);

        if (! $service instanceof Service || $service->hasDraft()) {
            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];

        $ledger->changed($service, ['blocks', 'draft', 'published_at'], "the tariffs block in /{$slug}");

        $before = $service->versions()->pluck('id')->all();

        $service->setAttribute('blocks', [...$service->blocksTree(), [
            'key' => $slug.'-tariffs',
            'type' => 'tariffs',
            'values' => array_filter([
                'title' => $this->words($block['title'] ?? null),
                'tariffs' => [
                    'categories' => [],
                    'limit' => null,
                    'filter' => false,
                    'markup' => false,
                    'related' => ['type' => 'service', 'ids' => [], 'current' => true],
                ],
                'layout' => 'grid',
            ], static fn (mixed $value): bool => $value !== null),
        ]]);
        $service->save();
        $service->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        foreach ($service->versions()->whereKeyNot($before)->get() as $version) {
            $ledger->created($version, 'Service history');
        }
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do.
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
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/tariffs.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('tariffs.json is not a price list.');
        }

        return $document;
    }
}
