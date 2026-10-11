<?php

declare(strict_types=1);

namespace WebxUi\Seo\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\ThemeDemo;
use WebxUi\Routing\Models\Route;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Links\LinkWriter;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Targets\Binding;
use WebxUi\Seo\Targets\UrlTarget;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * The FAQ and the interlinking a theme writes for pages of its own demo (THEMES §15.1, §23 TH3.4).
 *
 * `demo/seo/pages.json` of the first layer that has one names a page by its address in the
 * default language, its questions and its links in every language the theme wrote. The page
 * itself is the theme's demo of `module-pages`, which is seeded first (SeoModule::requires()):
 * each language's address is the one the registry gave that page, not a spelling the fixture
 * would have to keep in step with the page's slugs.
 *
 * A rule somebody already has for one of those addresses stays theirs, and so does a block of
 * links. Both features may be off on the site: the tables are there either way, and turning a
 * feature on shows what was seeded.
 */
final class ThemePagesDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly UrlTargets $targets,
        private readonly PageFaq $faq,
        private readonly LinkWriter $links,
    ) {}

    /** The theme brings pages of its own to dress — what SeoModule::requires() asks about. */
    public static function exists(): bool
    {
        $directory = ThemeDemo::directory('seo');

        return $directory !== null && is_file($directory.'/pages.json');
    }

    public function seed(DemoLedger $ledger): void
    {
        if (! self::exists()) {
            return;
        }

        foreach ($this->read() as $page) {
            if (! is_array($page) || ! is_string($page['page'] ?? null)) {
                continue;
            }

            foreach ($this->addresses($page['page']) as $locale => $address) {
                $this->writeFaq($ledger, $address, $locale, is_array($page['faq'] ?? null) ? $page['faq'] : []);
                $this->writeLinks($ledger, $address, $locale, is_array($page['links'] ?? null) ? $page['links'] : []);
            }
        }
    }

    /** @param list<mixed> $items */
    private function writeFaq(DemoLedger $ledger, string $address, string $locale, array $items): void
    {
        $questions = [];

        foreach ($items as $item) {
            $question = $item['question'][$locale] ?? null;
            $answer = $item['answer'][$locale] ?? null;

            if (is_string($question) && is_string($answer)) {
                $questions[] = ['question' => [$locale => $question], 'answer' => [$locale => $answer]];
            }
        }

        if ($questions === [] || $this->faq->ruleFor($address, $locale) instanceof SeoUrl) {
            return;
        }

        $rule = $this->faq->newRule($address);
        $this->faq->write($rule, $questions);
        $ledger->created($rule, 'faq '.$address);
    }

    /** @param array<string, mixed> $links */
    private function writeLinks(DemoLedger $ledger, string $address, string $locale, array $links): void
    {
        $items = [];

        foreach (is_array($links['items'] ?? null) ? $links['items'] : [] as $item) {
            $anchor = $item['anchor'][$locale] ?? null;
            $to = is_string($item['to'] ?? null) ? ($this->addresses($item['to'])[$locale] ?? null) : null;

            if (is_string($anchor) && $to !== null) {
                $items[] = ['acceptor' => $to, 'anchor' => $anchor];
            }
        }

        if ($items === []) {
            return;
        }

        $plan = $this->links->plan($address, $items, $locale, strict: true);

        if ($plan->hasErrors() || ! $plan->donor instanceof Binding || $this->links->blockFor($plan->donor->target) !== null) {
            return;
        }

        $heading = $links['heading'][$locale] ?? null;
        $block = $this->links->save($plan, ['heading' => is_string($heading) ? $heading : null, 'is_active' => true]);
        $ledger->created($block, 'links '.$address);
    }

    /**
     * The address of the page in every language it has, by the page the default-language address
     * answers with: an address nothing answers is a page that was not seeded, and gets nothing.
     *
     * @return array<string, string>
     */
    private function addresses(string $address): array
    {
        $target = $this->targets->resolve($address)->target;

        if ($target->entityType === null || $target->entityId === null) {
            return [];
        }

        $addresses = [];

        foreach (Route::query()->where('entity_type', $target->entityType)->where('entity_id', $target->entityId)->whereNull('target_id')->get() as $route) {
            $addresses[$route->locale] = $this->targets->savedAddress(
                new UrlTarget($route->locale, '/'.$route->path, $route->entity_type, $route->entity_id),
            );
        }

        return $addresses;
    }

    /** @return list<mixed> */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(ThemeDemo::directory('seo').'/pages.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('demo/seo/pages.json of the theme is not a list of pages.');
        }

        return array_values($document);
    }
}
