<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Settings\Settings;

/**
 * The services' side of §17.5 of the SEO spec: index → main category → service, printed once as
 * a `BreadcrumbList` and once as the crumbs a reader sees; the `Service` block; the `ItemList` a
 * listing pushes.
 */
final class BreadcrumbsTest extends TestCase
{
    #[Test]
    public function the_main_category_is_the_first_one_and_moving_it_moves_both_trails(): void
    {
        $implants = $this->category('implants');
        $surgery = $this->category('surgery');
        $service = $this->service('crowns');
        $service->syncCategories([$implants->getKey(), $surgery->getKey()]);

        $page = (string) $this->get('/services/crowns')->assertOk()->getContent();

        $this->assertSame(['Home', 'Services', 'Implants', 'Crowns'], $this->names($page));
        $this->assertSame(['Home', 'Services', 'Implants', 'Crowns'], $this->visible($page));

        // The editor drags the other category to the top of the service's list.
        $service->syncCategories([$surgery->getKey(), $implants->getKey()]);

        $page = (string) $this->get('/services/crowns')->getContent();

        $this->assertSame(['Home', 'Services', 'Surgery', 'Crowns'], $this->names($page));
        $this->assertSame(['Home', 'Services', 'Surgery', 'Crowns'], $this->visible($page));
    }

    #[Test]
    public function a_hidden_main_category_is_no_step_of_the_trail(): void
    {
        $this->service('crowns')->syncCategories([$this->category('secret', visible: false)->getKey()]);

        $this->assertSame(['Home', 'Services', 'Crowns'], $this->names((string) $this->get('/services/crowns')->getContent()));
    }

    #[Test]
    public function a_category_page_has_the_index_above_it_and_lists_its_services(): void
    {
        $category = $this->category('implants');
        $this->service('crowns')->syncCategories([$category->getKey()]);
        $this->service('bridges')->syncCategories([$category->getKey()]);

        $page = (string) $this->get('/services/implants')->assertOk()->getContent();

        $this->assertSame(['Home', 'Services', 'Implants'], $this->names($page));
        $this->assertSame(
            ['http://localhost/services/crowns', 'http://localhost/services/bridges'],
            array_column($this->jsonLd($page, 'ItemList')['itemListElement'], 'url'),
        );
    }

    #[Test]
    public function a_service_names_the_organisation_as_its_provider_by_id(): void
    {
        $this->app->make(Settings::class)->save(['seo.org-name' => ['en' => 'Smile Clinic']]);

        $service = $this->service('crowns');
        $service->update(['lead' => 'Ceramic crowns in one visit.']);

        $page = (string) $this->get('/services/crowns')->getContent();
        $block = $this->jsonLd($page, 'Service');
        $organisation = $this->jsonLd($page, 'Organization');

        $this->assertSame('Crowns', $block['name']);
        $this->assertSame('Ceramic crowns in one visit.', $block['description']);
        $this->assertSame('http://localhost/services/crowns', $block['url']);
        $this->assertSame(['@id' => 'https://example.test/#organization'], $block['provider']);
        $this->assertSame($block['provider']['@id'], $organisation['@id']);
        $this->assertArrayNotHasKey('image', $block);
    }

    #[Test]
    public function without_an_organisation_there_is_no_provider_to_point_at(): void
    {
        $this->service('crowns');

        $this->assertArrayNotHasKey('provider', $this->jsonLd((string) $this->get('/services/crowns')->getContent(), 'Service'));
    }

    /**
     * @return list<string>
     */
    private function names(string $page): array
    {
        return array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name');
    }

    /**
     * @return list<string>
     */
    private function visible(string $page): array
    {
        preg_match('#<nav class="webx-breadcrumbs".*?</nav>#s', $page, $nav);
        preg_match_all('#<li[^>]*>(?:<a [^>]*>)?([^<]+)#', $nav[0] ?? '', $items);

        return array_map(static fn (string $text): string => html_entity_decode(trim($text)), $items[1]);
    }
}
