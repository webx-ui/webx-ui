<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Pages\Models\Page;
use WebxUi\Seo\Rendering\Seo;

/**
 * The block type the module offers (§4.4) and the markup it puts on the page (§3.5, decision 8):
 * installed once and never over the site's own, printed as questions, one `FAQPage` however many
 * blocks, and a page that goes on answering when the module is gone.
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_offered_type_is_installed_and_published(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('FAQ', $block->title);
        $this->assertSame('Offered by faq', $block->publishedVersion?->comment);
        $this->assertSame('wx-collection', $block->publishedVersion?->schema[1]['type'] ?? null);
    }

    #[Test]
    public function a_type_the_site_already_has_by_that_slug_is_never_touched(): void
    {
        $own = Block::query()->create(['slug' => 'faq', 'title' => 'Our questions']);
        $own->saveVersion(['template' => '<div data-wx-block="faq">Ours</div>']);
        $own->publish();
        $version = $own->refresh()->published_version_id;

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['faq']])
            ->expectsOutputToContain('left alone')
            ->assertSuccessful();

        $own->refresh();

        $this->assertSame('Our questions', $own->title);
        $this->assertSame($version, $own->published_version_id);
        $this->assertStringContainsString('Ours', (string) $own->publishedVersion?->template);
    }

    #[Test]
    public function the_block_prints_the_questions_with_their_anchors_and_the_filter(): void
    {
        $this->installBlock();
        $billing = $this->category('Billing');
        $this->category('Empty');
        $cards = $this->question('Paying by card', 'Оплата картой', categories: [$billing]);
        $this->question('Without a category');

        $html = $this->render([$this->node(['title' => ['en' => 'Questions'], 'questions' => ['filter' => true]])]);

        $this->assertStringContainsString('<h2 class="b-faq__title">Questions</h2>', $html);
        $this->assertStringContainsString('<details class="b-faq__item" id="paying-by-card" data-faq-categories="'.$billing->id.'">', $html);
        $this->assertStringContainsString('<summary class="b-faq__question">Paying by card</summary>', $html);
        $this->assertStringContainsString('<p>About Paying by card.</p>', $html);
        $this->assertStringContainsString('data-faq-group="'.$billing->id.'"', $html);
        $this->assertStringNotContainsString('>Empty</button>', $html, 'a button that empties the list helps nobody');
        $this->assertStringContainsString('>All</button>', $html);

        $this->assertStringNotContainsString('b-faq__filter', $this->render([$this->node(['questions' => ['filter' => false]])]));

        $this->nextRequest('ru');
        $russian = $this->render([$this->node(['questions' => ['categories' => [$billing->id]]])]);

        // The anchor is the same in every language: a link is a link, whichever page it is on.
        $this->assertStringContainsString('id="'.$cards->anchor.'"', $russian);
        $this->assertStringContainsString('Оплата картой', $russian);
        $this->assertStringNotContainsString('Without a category', $russian);
    }

    #[Test]
    public function two_blocks_sharing_a_question_give_one_faqpage_with_it_once(): void
    {
        $this->installBlock();
        $billing = $this->category('Billing');
        $delivery = $this->category('Delivery');
        $this->question('Refunds', categories: [$billing, $delivery]);
        $this->question('Cards', categories: [$billing]);
        $this->question('Couriers', categories: [$delivery]);

        $this->render([
            $this->node(['questions' => ['categories' => [$billing->id], 'markup' => true]]),
            $this->node(['questions' => ['categories' => [$delivery->id], 'markup' => true]]),
        ]);

        $put = $this->app->make(Seo::class)->putBlocks();

        $this->assertSame(['faq'], array_keys($put));
        $this->assertSame('FAQPage', $put['faq']['@type']);
        $this->assertSame(['Refunds', 'Cards', 'Couriers'], array_column($put['faq']['mainEntity'], 'name'));
        $this->assertSame(['@type' => 'Answer', 'text' => 'About Refunds.'], $put['faq']['mainEntity'][0]['acceptedAnswer']);
    }

    #[Test]
    public function by_default_the_markup_is_on_for_everything_and_off_for_chosen_categories(): void
    {
        $this->installBlock();
        $billing = $this->category('Billing');
        $this->question('Refunds', categories: [$billing]);

        $this->render([$this->node(['questions' => ['categories' => [$billing->id], 'markup' => null]])]);
        $this->assertSame([], $this->app->make(Seo::class)->putBlocks(), 'the same questions on every service page are not marked up');

        $this->nextRequest();
        $this->render([$this->node(['questions' => ['markup' => null]])]);
        $this->assertArrayHasKey('faq', $this->app->make(Seo::class)->putBlocks());

        $this->nextRequest();
        $this->render([$this->node(['questions' => ['markup' => false]])]);
        $this->assertSame([], $this->app->make(Seo::class)->putBlocks(), 'the editor may turn it off');
    }

    #[Test]
    public function the_page_prints_the_markup_in_its_head(): void
    {
        $this->installBlock();
        $this->question('Refunds');

        $this->get($this->page([$this->node()]))
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Refunds"', false);
    }

    #[Test]
    public function without_the_module_the_block_is_empty_and_the_page_still_answers(): void
    {
        $this->installBlock();
        $this->question('Refunds');
        $url = $this->page([$this->node()]);

        $this->get($url)->assertOk()->assertSee('id="refunds"', false);

        $this->app->make(CollectionSources::class)->forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('<div class="b-faq__list">', false)
            ->assertDontSee('id="refunds"', false)
            ->assertDontSee('FAQPage', false);
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     */
    private function render(array $blocks): string
    {
        return (string) $this->app->make(Renderer::class)->render($blocks);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function node(array $values = []): array
    {
        static $count = 0;
        $count++;

        return ['key' => "k{$count}", 'type' => 'faq', 'values' => $values];
    }

    /**
     * A published page with these blocks, at its address.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(array $blocks): string
    {
        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'FAQ', 'slug' => 'faq']);
        $page->appendTo($home);
        $page->blocks = $blocks;
        $page->save();
        $page->publish();

        return '/faq';
    }
}
