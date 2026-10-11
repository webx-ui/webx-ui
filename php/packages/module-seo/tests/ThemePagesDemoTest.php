<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Seo\Demo\ThemePagesDemo;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Tests\Fixtures\MapsEntities;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The FAQ and the interlinking a theme writes for pages of its own demo (THEMES §23, TH3.4): each
 * language of the page gets the questions and links written in it, at the address the registry
 * gave the page in that language.
 */
final class ThemePagesDemoTest extends TestCase
{
    use MapsEntities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapEntities();
    }

    #[Test]
    public function without_a_theme_there_is_nothing_to_seed_and_the_pages_are_not_waited_for(): void
    {
        $this->assertFalse(ThemePagesDemo::exists());
        $this->assertSame(['articles'], app(SeoModule::class)->requires());

        $this->entity(['ru' => 'about']);
        app(ThemePagesDemo::class)->seed($this->ledger());

        $this->assertSame(0, SeoUrl::query()->count());
    }

    #[Test]
    public function each_language_of_the_page_gets_its_questions_and_its_links(): void
    {
        $this->theme();
        $this->assertSame(['articles', 'pages'], app(SeoModule::class)->requires());

        $this->entity(['ru' => 'about', 'uk' => 'pro']);
        $this->entity(['ru' => 'contacts', 'uk' => 'kontakty']);

        $ledger = $this->ledger();
        app(ThemePagesDemo::class)->seed($ledger);

        $ru = SeoUrl::query()->where('pattern', '/about')->with('faqItems')->sole();
        $uk = SeoUrl::query()->where('pattern', '/uk/pro')->with('faqItems')->sole();
        $this->assertSame(['Ru question?', 'Only in ru?'], $ru->faqItems->map(fn ($item) => $item->questionText('ru'))->all());
        $this->assertSame(['Uk question?'], $uk->faqItems->map(fn ($item) => $item->questionText('uk'))->all());

        $blocks = SeoLinkBlock::query()->with('items')->orderBy('locale')->get();
        $this->assertSame(['ru' => 'Ru read on', 'uk' => 'Uk read on'], $blocks->pluck('heading', 'locale')->all());
        // The address nothing answers is left out, not written as a broken link.
        $this->assertSame([['Ru contacts'], ['Uk contacts']], $blocks->map(fn (SeoLinkBlock $block) => $block->items->pluck('anchor')->all())->all());
        $this->assertSame(['/kontakty'], $blocks->firstWhere('locale', 'uk')?->items->pluck('path')->all());

        // A page the demo did not seed gets nothing; the ledger can take back all four records.
        $this->assertSame(0, SeoUrl::query()->where('pattern', '/not-seeded')->count());
        $this->assertCount(4, $ledger->entries());
    }

    #[Test]
    public function a_second_run_and_somebodys_own_rule_are_left_alone(): void
    {
        $this->theme();
        $this->entity(['ru' => 'about', 'uk' => 'pro']);
        $this->entity(['ru' => 'contacts', 'uk' => 'kontakty']);
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/uk/pro', 'priority' => 0, 'is_active' => true]);

        app(ThemePagesDemo::class)->seed($this->ledger());
        app(ThemePagesDemo::class)->seed($this->ledger());

        $this->assertSame(2, SeoUrl::query()->count());
        $this->assertSame(0, SeoUrl::query()->where('pattern', '/uk/pro')->sole()->faqItems()->count());
        $this->assertSame(2, SeoLinkBlock::query()->count());
    }

    private function theme(): void
    {
        $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('site', __DIR__.'/Fixtures/theme', true)]));
    }

    private function ledger(): DemoLedger
    {
        $ledger = new DemoLedger(new Filesystem, Storage::getFacadeRoot(), storage_path('framework/testing/demo-'.bin2hex(random_bytes(4)).'.json'));
        $ledger->forModule('seo');

        return $ledger;
    }
}
