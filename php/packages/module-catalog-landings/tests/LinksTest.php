<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * §7 of the landings spec: the four lists of links between the catalogue's pages.
 */
final class LinksTest extends TestCase
{
    private Category $laptops;

    private Category $tablets;

    private Brand $apple;

    private Brand $dell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
        $this->tablets = $this->category('tablets');
        $this->apple = $this->brand('Apple');
        $this->dell = $this->brand('Dell');

        $this->product('MacBook Air', $this->laptops, $this->apple, ['price' => 900]);
        $this->product('XPS', $this->laptops, $this->dell, ['price' => 1200]);
        $this->product('iPad', $this->tablets, $this->apple, ['price' => 500]);
    }

    #[Test]
    public function a_category_shows_its_collections_on_its_plain_page_within_the_limit(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], ['on_category' => true, 'name' => 'Apple laptops', 'position' => 2]);
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])], ['on_category' => true, 'name' => 'Dell laptops', 'position' => 1]);
        $this->landing('laptops-cheap', $this->laptops, ['price' => ['max' => 1000]], ['name' => 'Cheap laptops']);

        $page = (string) $this->get('/laptops')->assertOk()->assertSee('Collections')->getContent();
        $this->assertLessThan(strpos($page, '>Apple laptops<'), strpos($page, '>Dell laptops<'));
        $this->assertStringNotContainsString('>Cheap laptops<', $page);

        $this->get('/laptops/price_0-1000')->assertOk()->assertDontSee('>Dell laptops<', false);

        $this->app['config']->set('webx-catalog-landings.links.category', 1);
        $this->get('/laptops')->assertOk()->assertSee('>Dell laptops<', false)->assertDontSee('>Apple laptops<', false);
    }

    #[Test]
    public function a_landing_links_its_neighbours_and_the_same_set_on_other_categories(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], ['name' => 'Apple laptops']);
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])], ['name' => 'Dell laptops']);
        $this->landing('tablets-apple', $this->tablets, ['brand' => $this->brands([$this->apple])], ['name' => 'Apple tablets']);
        $this->landing('tablets-cheap', $this->tablets, ['price' => ['max' => 1000]], ['name' => 'Cheap tablets']);

        $this->get('/laptops-apple')
            ->assertOk()
            ->assertSee('See also')
            ->assertSee('>Dell laptops<', false)
            ->assertSee('The same in other categories')
            ->assertSee('>Apple tablets<', false)
            ->assertDontSee('>Cheap tablets<', false)
            // Not a neighbour of itself.
            ->assertDontSee('<li><a href="http://localhost/laptops-apple">', false);

        // A choice over the set is another list: no links under it.
        $this->get('/laptops-apple/price_0-1000')->assertOk()->assertDontSee('>Dell laptops<', false);

        $this->app['config']->set('webx-catalog-landings.links.siblings', 0);
        $this->get('/laptops-apple')->assertOk()->assertDontSee('>Dell laptops<', false)->assertSee('>Apple tablets<', false);
    }

    #[Test]
    public function a_product_is_in_the_collections_whose_list_holds_it_from_one_query(): void
    {
        $gaming = $this->category('gaming', $this->laptops);
        $alienware = $this->product('Alienware', $gaming, $this->dell, ['price' => 2500]);

        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])], ['name' => 'Dell laptops']);
        $this->landing('gaming-dell', $gaming, ['brand' => $this->brands([$this->dell])], ['name' => 'Gaming Dell']);
        $this->landing('dell', null, ['brand' => $this->brands([$this->dell])], ['name' => 'All of Dell']);
        $this->landing('laptops-dear', $this->laptops, ['price' => ['min' => 2000]], ['name' => 'Dear laptops']);
        $this->landing('laptops-cheap', $this->laptops, ['price' => ['max' => 1000]], ['name' => 'Cheap laptops']);
        $this->landing('laptops-apple-dell', $this->laptops, ['brand' => $this->brands([$this->apple, $this->dell])], ['name' => 'Apple or Dell']);
        $this->landing('tablets-dell', $this->tablets, ['brand' => $this->brands([$this->dell])], ['name' => 'Dell tablets']);

        $landingQueries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$landingQueries): void {
            if (str_contains($query->sql, 'catalog_landings')) {
                $landingQueries++;
            }
        });

        $page = (string) $this->get($alienware->url())->assertOk()->assertSee('In collections')->getContent();

        $this->assertSame(1, $landingQueries);

        foreach (['Dell laptops', 'Gaming Dell', 'All of Dell', 'Dear laptops', 'Apple or Dell'] as $name) {
            $this->assertStringContainsString('>'.$name.'<', $page);
        }

        foreach (['Cheap laptops', 'Dell tablets'] as $name) {
            $this->assertStringNotContainsString('>'.$name.'<', $page);
        }

        // The deeper base first, among equal positions.
        $this->assertLessThan(strpos($page, '>Dell laptops<'), strpos($page, '>Gaming Dell<'));
    }

    #[Test]
    public function an_empty_landing_gets_no_links(): void
    {
        $nobody = $this->brand('Nobody');
        $this->landing('laptops-nobody', $this->laptops, ['brand' => $this->brands([$nobody])], ['on_category' => true, 'name' => 'Nobody laptops']);
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], ['name' => 'Apple laptops']);

        $this->artisan('webx:catalog-landings:count', ['--all' => true])->assertSuccessful();

        $this->get('/laptops')->assertOk()->assertDontSee('>Nobody laptops<', false);
        $this->get('/laptops-apple')->assertOk()->assertDontSee('>Nobody laptops<', false);
    }
}
