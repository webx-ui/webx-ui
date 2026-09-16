<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * Moving a page through the section, which is the same gesture as changing its address (§5).
 */
final class MoveApiTest extends TestCase
{
    #[Test]
    public function a_branch_moved_inside_another_page_takes_its_addresses_with_it(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $about = $this->page('about');

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($catalog->getKey()).'/move', ['target' => $about->getKey(), 'zone' => 'inside'])
            ->assertOk();

        $this->assertSame('about/catalog', $response->json('data.page.path'));

        // What the panel says out loud: the page and everything under it are somewhere else now.
        $this->assertSame(2, $response->json('data.addresses_changed'));
        $this->assertSame('about/catalog/shoes', $shoes->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function a_page_dropped_beside_the_home_page_is_refused(): void
    {
        $about = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($about->getKey()).'/move', ['target' => $this->home()->getKey(), 'zone' => 'after'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('zone');
    }

    #[Test]
    public function the_home_page_itself_cannot_be_moved(): void
    {
        $about = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($this->home()->getKey()).'/move', ['target' => $about->getKey(), 'zone' => 'inside'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    #[Test]
    public function a_page_cannot_be_dropped_into_its_own_branch(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($catalog->getKey()).'/move', ['target' => $shoes->getKey(), 'zone' => 'inside'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target');
    }

    #[Test]
    public function an_order_among_siblings_is_kept_by_the_list(): void
    {
        $this->page('about');
        $contact = $this->page('contact');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($contact->getKey()).'/move', ['target' => $this->home()->getKey(), 'zone' => 'inside'])
            ->assertOk();

        $items = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data.items');

        $this->assertSame(['about', 'contact'], array_column($items, 'path'));
    }
}
