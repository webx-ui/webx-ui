<?php

declare(strict_types=1);

namespace WebxUi\Faq\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Faq\Panel\QuestionsModule;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Services\Models\Service;

/**
 * Three categories and ten questions (§4.8), and the FAQ block put where a site would put it.
 *
 * Each question is there to show one rule: one is filed under two categories and stands in a
 * different place in each (decision 4), one is not published, one is written in one language and
 * so is seen in no other (decision 9). The questions are written in the two languages of the demo,
 * as far as the site has them — the one without a translation only shows anything on a site that
 * has a second language to leave it out of.
 *
 * Then the block. Its type is the one this module offers, installed here when the site has not
 * taken it yet. With `module-pages`, a page `/faq` with every category and the filter — the site's
 * FAQ page is an ordinary page (decision 6). With `module-services`, "Questions about payment" in
 * one of the demo services, with its markup left to the default — which is off for a block with
 * categories: `/faq` carries `FAQPage` and the service does not (decision 8).
 */
final class FaqDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly BlockOffers $offers,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A FAQ with anything in it is somebody's FAQ.
        if (Question::withTrashed()->exists() || FaqCategory::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();

        /** @var array<string, Question> $questions */
        $questions = [];

        // In the order of the file, which is the order of the whole list.
        foreach ($this->list($document['questions'] ?? null) as $input) {
            $question = $this->question($input, $ledger);

            if ($question !== null) {
                $questions[(string) $input['key']] = $question;
            }
        }

        /** @var array<string, FaqCategory> $categories */
        $categories = [];
        $position = 0;

        foreach ($this->list($document['categories'] ?? null) as $input) {
            $category = $this->category($input, $position++, $questions, $ledger);

            if ($category !== null) {
                $categories[(string) $input['key']] = $category;
            }
        }

        if (! $this->blockType($ledger)) {
            return;
        }

        $page = $document['page'] ?? null;

        if (is_array($page) && $this->modules->has('pages') && class_exists(Page::class)) {
            $this->page($page, $ledger);
        }

        $service = $document['service'] ?? null;

        if (is_array($service) && $this->modules->has('services') && class_exists(Service::class)) {
            $this->service($service, $categories, $ledger);
        }
    }

    /**
     * The modules whose demo has to be there before this one's: the page and the service the
     * block goes into. Named only when installed — a name `requires()` gives that is not
     * installed skips the whole demo, and a FAQ has something to show without either.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [
            'blocks',
            ...array_values(array_filter(['pages', 'services'], $this->modules->has(...))),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function question(array $input, DemoLedger $ledger): ?Question
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $text = $this->words($input['question'] ?? null);

        if ($key === '' || $text === null) {
            return null;
        }

        $question = Question::query()->create([
            'question' => $text,
            'answer' => $this->words($input['answer'] ?? null),
            'published' => ($input['published'] ?? true) !== false,
        ]);

        $ledger->created($question, $key);

        return $question;
    }

    /**
     * A category, and its questions in the order the file lists them — its own order, not the
     * order of the whole list.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, Question>  $questions
     */
    private function category(array $input, int $position, array $questions, DemoLedger $ledger): ?FaqCategory
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return null;
        }

        $category = FaqCategory::query()->create([
            'title' => $title,
            'is_visible' => true,
            'position' => $position,
        ]);

        $ledger->created($category, $key);

        $members = [];

        foreach ($this->list($input['questions'] ?? null, strings: true) as $name) {
            if (isset($questions[$name])) {
                $members[] = $questions[$name];
            }
        }

        foreach ($members as $question) {
            $ids = $question->categories()->pluck('id')->map(intval(...))->all();
            $question->syncCategories([...$ids, (int) $category->getKey()]);
        }

        Ordering::move(
            Question::class,
            array_map(static fn (Question $question): int => (int) $question->getKey(), $members),
            (int) $category->getKey(),
        );

        return $category;
    }

    /**
     * The offered type, installed now if the site has not taken it — `webx:setup` usually has.
     * A type by that slug the site already has is left as it is, whatever it was made into.
     */
    private function blockType(DemoLedger $ledger): bool
    {
        foreach ($this->offers->documents([QuestionsModule::ID]) as $offer) {
            if ($this->offers->install($offer) !== BlockOffers::PRESENT) {
                $block = Block::query()->where('slug', $offer['slug'])->first();

                if ($block instanceof Block) {
                    $ledger->created($block, 'the '.$offer['slug'].' block type');
                }
            }
        }

        if (! Block::query()->where('slug', 'faq')->where('is_enabled', true)->exists()) {
            $ledger->note('the questions are in the panel, but no FAQ block was put anywhere: the site has no enabled block type "faq".');

            return false;
        }

        return true;
    }

    /**
     * `/faq` under the home page: every category, with the filter, markup by default — on.
     *
     * @param  array<string, mixed>  $input
     */
    private function page(array $input, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : 'faq';
        $home = Page::home();

        if (! $home instanceof Page) {
            return;
        }

        if (app(Reserved::class)->taken($slug) || Page::withTrashed()->whereTranslationLikeAny('slug', $slug)->exists()) {
            $ledger->note("no FAQ page was made: /{$slug} is already taken on this site.");

            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];

        $page = new Page([
            'title' => $this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => 'FAQ'],
            // The same slug in every language the page is written in: a page with no slug in a
            // language has no address in it, and the second language is where decision 9 shows.
            'slug' => array_fill_keys(array_keys($this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => '']), $slug),
            'blocks' => [[
                'key' => 'faq-all',
                'type' => 'faq',
                'values' => array_filter([
                    'title' => $this->words($block['title'] ?? null),
                    'questions' => ['categories' => [], 'limit' => null, 'filter' => true, 'markup' => null],
                    'all_label' => $this->words($block['all_label'] ?? null),
                ], static fn (mixed $value): bool => $value !== null),
            ]],
        ]);

        try {
            $page->appendTo($home);
        } catch (Throwable $refused) {
            $ledger->note("no FAQ page was made: {$refused->getMessage()}");

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
     * "Questions about payment" at the end of one demo service — only a service the services
     * demo made a moment ago: somebody's real service is not the place for an example.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, FaqCategory>  $categories
     */
    private function service(array $input, array $categories, DemoLedger $ledger): void
    {
        $category = $categories[(string) ($input['category'] ?? '')] ?? null;
        $ids = $ledger->idsOf('services', Service::class);

        if ($category === null || $ids === []) {
            return;
        }

        $slug = (string) ($input['slug'] ?? '');
        $service = Service::query()->whereKey($ids)->where('slug->'.$this->locales->defaultCode(), $slug)->first();

        if (! $service instanceof Service || $service->hasDraft()) {
            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];

        $ledger->changed($service, ['blocks', 'draft', 'published_at'], "the FAQ block in /{$slug}");

        $before = $service->versions()->pluck('id')->all();

        $service->setAttribute('blocks', [...$service->blocksTree(), [
            'key' => $slug.'-faq',
            'type' => 'faq',
            'values' => array_filter([
                'title' => $this->words($block['title'] ?? null),
                // Markup left to the default: off, because these categories are picked — the
                // same questions will stand on every service that shows them.
                'questions' => ['categories' => [(int) $category->getKey()], 'limit' => null, 'filter' => false, 'markup' => null],
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
     * own default, as the other demos do, rather than an empty FAQ.
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
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/faq.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('faq.json is not a FAQ.');
        }

        return $document;
    }
}
