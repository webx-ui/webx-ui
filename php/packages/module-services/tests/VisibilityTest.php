<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Services\Models\ServiceCategory;

/**
 * What a reader can reach, asked once for the handler and once for the sitemap (§17.2 of the SEO
 * spec) — the two must never disagree.
 */
final class VisibilityTest extends TestCase
{
    #[Test]
    public function a_draft_is_a_404_and_is_not_in_the_map(): void
    {
        $this->service('draft', published: false);
        $this->service('live');

        $this->get('/services/draft')->assertNotFound();
        $this->get('/services/live')->assertOk();

        $map = $this->map('service');

        $this->assertStringContainsString('/services/live<', $map);
        $this->assertStringNotContainsString('/services/draft<', $map);
    }

    #[Test]
    public function a_service_in_the_bin_is_gone_from_both(): void
    {
        $this->service('binned')->delete();

        $this->get('/services/binned')->assertNotFound();
        $this->assertStringNotContainsString('/services/binned<', $this->map('service'));
    }

    #[Test]
    public function a_service_in_a_hidden_category_still_answers(): void
    {
        $hidden = $this->category('secret', visible: false);
        $service = $this->service('whitening');
        $service->syncCategories([$hidden->getKey()]);

        $this->get('/services/secret')->assertNotFound();
        $this->get('/services/whitening')->assertOk();

        $this->assertStringNotContainsString('/services/secret<', $this->map('service-category'));
        $this->assertStringContainsString('/services/whitening<', $this->map('service'));

        // Nowhere else to find it on the index, so it is listed with the services in no category.
        $this->assertStringContainsString('Whitening', (string) $this->get('/services')->getContent());
    }

    #[Test]
    public function the_index_is_an_address_of_the_map(): void
    {
        $this->get('/services')->assertOk();

        $this->assertStringContainsString('http://localhost/services<', (string) $this->get('/sitemap.xml')->getContent().$this->allMaps());
    }

    #[Test]
    public function the_index_answers_under_the_prefix_of_each_language_and_no_other(): void
    {
        $this->useLocales('en', 'uk');

        $page = (string) $this->get('/uk/services')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="alternate" hreflang="uk" href="http://localhost/uk/services">', $page);
        $this->get('/xx/services')->assertNotFound();
        $this->get('/en/services')->assertRedirect('/services')->assertStatus(301);
    }

    #[Test]
    public function a_category_with_services_refuses_to_go_into_the_bin(): void
    {
        $category = $this->category('implants');
        $this->service('crowns')->syncCategories([$category->getKey()]);

        $refused = $this->refusalOf(static fn (): ?bool => $category->delete());

        $this->assertStringContainsString('1 services', $refused->getMessage());
        $this->assertSame(422, $refused->render(Request::create('/', 'DELETE', server: ['HTTP_ACCEPT' => 'application/json']))?->getStatusCode());
        $this->assertFalse($category->refresh()->trashed());
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function refusalOf(callable $action): CategoryException
    {
        try {
            $action();
        } catch (CategoryException $refused) {
            return $refused;
        }

        $this->fail('The action should have been refused.');
    }

    #[Test]
    public function a_category_has_no_blocks_tab_until_the_site_prints_blocks(): void
    {
        $tabs = array_column(Tree::children(Screens::tree(ServiceCategory::SCREEN)[0]), 'id');

        $this->assertSame(['content', 'seo'], $tabs);
    }

    #[Test]
    public function an_empty_category_goes_into_the_bin_and_its_page_with_it(): void
    {
        $category = $this->category('empty');

        $category->delete();

        $this->get('/services/empty')->assertNotFound();
    }

    private function map(string $type): string
    {
        $response = $this->get("/sitemap-{$type}.xml");

        return $response->getStatusCode() === 404 ? '' : (string) $response->getContent();
    }

    /** Every file the index names, glued together — wherever the route ended up. */
    private function allMaps(): string
    {
        preg_match_all('#<loc>([^<]+)</loc>#', (string) $this->get('/sitemap.xml')->getContent(), $files);

        return implode('', array_map(
            fn (string $url): string => (string) $this->get((string) parse_url($url, PHP_URL_PATH))->getContent(),
            $files[1],
        ));
    }
}
