<?php

declare(strict_types=1);

namespace WebxUi\Team\Demo;

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
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Services\Models\Service;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Panel\TeamModule;

/**
 * Six people (§5.9), and the team block put where a site would put it.
 *
 * Each person past the first few is there to show one rule: one is not published, one has no
 * Russian text and is shown on the Russian page anyway, only without it (decision 8), three have
 * social links and one of those links is to a network the config does not have — stored, and left
 * off the card (§5.2). Nobody has a photo: the block draws the initials, and the demo is how that
 * gets seen.
 *
 * Then the block, whose type is the one this module offers, installed here when the site has not
 * taken it yet. With `module-pages`, a page `/team` with everybody in a grid — a page of the team
 * is an ordinary page (decision 2). With `module-services`, the people are linked to the demo
 * services, and one of those services gets "who does it": a list of only the people linked to
 * the service whose page it is.
 */
final class TeamDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly BlockOffers $offers,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A team with anybody in it is somebody's team.
        if (Member::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $services = $this->services($ledger);

        // In the order of the file, which is the order of the team.
        foreach ($this->list($document['members'] ?? null) as $input) {
            $this->member($input, $services, $ledger);
        }

        if (! in_array('blocks', $this->requires(), true) || ! $this->blockType($ledger)) {
            return;
        }

        $page = $document['page'] ?? null;

        if (is_array($page) && class_exists(Page::class)) {
            $this->page($page, $ledger);
        }

        $service = $document['service'] ?? null;

        if (is_array($service) && $services !== []) {
            $this->service($service, $services, $ledger);
        }
    }

    /**
     * The modules whose demo has to be there before this one's. Named only when installed — a
     * name `requires()` gives that is not installed skips the whole demo, and the team has
     * something to show without any of them but the library.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        $requires = ['media'];

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
     * Written straight to the model rather than through the form: the link to a network the config
     * does not list is the point of one of them, and the form would refuse to make it.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, int>  $services
     */
    private function member(array $input, array $services, DemoLedger $ledger): void
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $name = $this->words($input['name'] ?? null);

        if ($key === '' || $name === null) {
            return;
        }

        $socials = [];

        foreach ($this->list($input['socials'] ?? null) as $link) {
            if (is_string($link['network'] ?? null) && is_string($link['url'] ?? null)) {
                $socials[] = ['network' => $link['network'], 'url' => $link['url']];
            }
        }

        $member = Member::query()->create([
            'name' => $name,
            'job_title' => $this->words($input['job_title'] ?? null),
            'text' => $this->words($input['text'] ?? null),
            'socials' => $socials === [] ? null : $socials,
            'published' => ($input['published'] ?? true) !== false,
        ]);

        $ledger->created($member, $key);

        $linked = array_values(array_filter(array_map(
            static fn (string $slug): ?int => $services[$slug] ?? null,
            $this->list($input['services'] ?? null, strings: true),
        )));

        if ($linked !== []) {
            $member->syncRelated(Member::SERVICES, 'service', $linked);
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
     * The offered type, installed now if the site has not taken it — `webx:setup` usually has.
     * A type by that slug the site already has is left as it is, whatever it was made into.
     */
    private function blockType(DemoLedger $ledger): bool
    {
        foreach ($this->offers->documents([TeamModule::ID]) as $offer) {
            if ($this->offers->install($offer) !== BlockOffers::PRESENT) {
                $block = Block::query()->where('slug', $offer['slug'])->first();

                if ($block instanceof Block) {
                    $ledger->created($block, 'the '.$offer['slug'].' block type');
                }
            }
        }

        if (! Block::query()->where('slug', 'team')->where('is_enabled', true)->exists()) {
            $ledger->note('the team is in the panel, but no team block was put anywhere: the site has no enabled block type "team".');

            return false;
        }

        return true;
    }

    /**
     * `/team` under the home page: everybody, a grid.
     *
     * @param  array<string, mixed>  $input
     */
    private function page(array $input, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : 'team';
        $home = Page::home();

        if (! $home instanceof Page) {
            return;
        }

        if (app(Reserved::class)->taken($slug) || Page::withTrashed()->whereTranslationLikeAny('slug', $slug)->exists()) {
            $ledger->note("no page of the team was made: /{$slug} is already taken on this site.");

            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];
        $title = $this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => 'Team'];

        $page = new Page([
            'title' => $title,
            // The same slug in every language the page is written in: a page with no slug in a
            // language has no address in it, and the second language is where decision 8 shows.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'blocks' => [[
                'key' => 'team-all',
                'type' => 'team',
                'values' => array_filter([
                    'title' => $this->words($block['title'] ?? null),
                    'team' => ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => false],
                    'layout' => 'grid',
                    'columns' => 3,
                ], static fn (mixed $value): bool => $value !== null),
            ]],
        ]);

        try {
            $page->appendTo($home);
        } catch (Throwable $refused) {
            $ledger->note("no page of the team was made: {$refused->getMessage()}");

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
     * "Who does it" at the end of one demo service: a list of only the people linked to the service
     * whose page it is — the block answers the question itself, so the same block would do on
     * every service.
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

        $ledger->changed($service, ['blocks', 'draft', 'published_at'], "the team block in /{$slug}");

        $before = $service->versions()->pluck('id')->all();

        $service->setAttribute('blocks', [...$service->blocksTree(), [
            'key' => $slug.'-team',
            'type' => 'team',
            'values' => array_filter([
                'title' => $this->words($block['title'] ?? null),
                'team' => [
                    'categories' => [],
                    'limit' => null,
                    'filter' => false,
                    'markup' => false,
                    'related' => ['type' => 'service', 'ids' => [], 'current' => true],
                ],
                'layout' => 'list',
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
     * own default, as the other demos do, rather than nameless people.
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
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/team.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('team.json is not a team.');
        }

        return $document;
    }
}
