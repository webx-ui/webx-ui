<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Product;

/**
 * §4 and §5: a product at `/{slug}-{id}`, one spelling of it, and what its address says in each
 * of the three states.
 */
final class AddressesTest extends TestCase
{
    #[Test]
    public function a_product_lives_at_its_slug_and_its_id(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad X1', $laptops);

        $this->assertSame("thinkpad-x1-{$product->id}", $product->routePath());

        $this->get("/thinkpad-x1-{$product->id}")
            ->assertOk()
            ->assertSee('ThinkPad X1')
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get('/laptops')->assertOk()->assertSee('Laptops');
    }

    #[Test]
    public function any_other_spelling_of_a_product_answers_301_to_the_canonical_one(): void
    {
        $product = $this->product('ThinkPad X1', $this->category('laptops'));

        $this->get("/thinkpad-{$product->id}?utm_source=mail")
            ->assertStatus(301)
            ->assertRedirect("/thinkpad-x1-{$product->id}?utm_source=mail");

        // After a rename the old slug is just another spelling.
        $product->update(['slug' => 'thinkpad-x1-carbon']);

        $this->get("/thinkpad-x1-{$product->id}")
            ->assertStatus(301)
            ->assertRedirect("/thinkpad-x1-carbon-{$product->id}");
    }

    #[Test]
    public function a_number_nobody_has_is_still_a_404(): void
    {
        $this->get('/something-999')->assertNotFound();
    }

    #[Test]
    public function an_unpublished_product_answers_200_with_the_trimmed_page_and_noindex(): void
    {
        $product = $this->product('Old model', $this->category('laptops'), ['sku' => 'OLD-1']);
        $product->update(['is_published' => false]);

        $this->get("/old-model-{$product->id}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('No longer sold')
            ->assertSee('OLD-1')
            ->assertSee('noindex', false);
    }

    #[Test]
    public function a_published_product_in_no_visible_category_behaves_as_unpublished(): void
    {
        $hidden = $this->category('archive', published: false);
        $product = $this->product('Forgotten', $hidden);

        $this->assertTrue($product->is_published);
        $this->assertFalse($product->isVisible());

        $this->get("/forgotten-{$product->id}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('No longer sold');
    }

    #[Test]
    public function a_deleted_product_redirects_to_its_main_category(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad X1', $laptops);
        $product->delete();

        $this->get("/thinkpad-x1-{$product->id}")
            ->assertStatus(301)
            ->assertRedirect('/laptops');
    }

    #[Test]
    public function a_deleted_product_with_no_visible_category_is_gone(): void
    {
        $hidden = $this->category('archive', published: false);
        $product = $this->product('Forgotten', $hidden);
        $product->delete();

        $this->get("/forgotten-{$product->id}")->assertStatus(410);

        $orphan = $this->product('Orphan');
        $orphan->delete();

        $this->get("/orphan-{$orphan->id}")->assertStatus(410);
    }

    #[Test]
    public function a_deleted_product_falls_back_on_a_visible_additional_category(): void
    {
        $hidden = $this->category('archive', published: false);
        $sale = $this->category('sale');
        $product = $this->product('Last one', $hidden);
        $product->categories()->attach($sale->id);
        $product->delete();

        $this->get("/last-one-{$product->id}")
            ->assertStatus(301)
            ->assertRedirect('/sale');
    }

    #[Test]
    public function a_restored_product_gets_its_address_back(): void
    {
        $product = $this->product('ThinkPad X1', $this->category('laptops'));
        $product->delete();
        Product::onlyTrashed()->findOrFail($product->id)->restore();

        $this->get("/thinkpad-x1-{$product->id}")->assertOk();
    }

    #[Test]
    public function a_deleted_category_is_gone_and_a_hidden_one_is_not_found(): void
    {
        $empty = $this->category('empty');
        $empty->delete();

        $this->get('/empty')->assertStatus(410);
        $this->get('/empty/brand_apple')->assertStatus(410);

        $this->category('drafts', published: false);

        $this->get('/drafts')->assertNotFound();
    }

    #[Test]
    public function a_tail_behind_a_category_is_the_filters_and_not_served_yet(): void
    {
        $this->category('laptops');

        $this->get('/laptops/brand_apple')->assertNotFound();
    }
}
