<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Services\ServicesServiceProvider;

/**
 * Services with no prefix at all (§4.3): at the root beside the pages, and no index — `/` is the
 * site's. A class of its own because the prefix is read once, at boot.
 */
final class NoPrefixTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-services.prefix', '');
    }

    #[Test]
    public function services_and_categories_sit_at_the_root_and_there_is_no_index(): void
    {
        $category = $this->category('implants');
        $service = $this->service('crowns');
        $service->syncCategories([$category->getKey()]);

        $this->assertSame('implants', $category->routeCanonical()?->path);
        $this->assertSame('crowns', $service->routeCanonical()?->path);
        $this->assertFalse($this->app['router']->has(ServicesServiceProvider::INDEX_ROUTE));

        // No index, so no step for it in the trail either.
        $page = (string) $this->get('/crowns')->assertOk()->getContent();

        $this->assertSame(['Home', 'Implants', 'Crowns'], array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name'));
    }
}
