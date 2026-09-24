<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Services\Tests\Fixtures\Landing;

/**
 * `webx-services.index` off: the prefix stays, the route under it goes, and the address is free
 * for a page of blocks — which the trail then starts with.
 */
final class NoIndexTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-services.index', false);
    }

    #[Test]
    public function the_prefix_is_not_the_packages_and_categories_keep_their_addresses(): void
    {
        $category = $this->category('implants');

        $this->assertFalse($this->app['router']->has(ServicesServiceProvider::INDEX_ROUTE));
        $this->assertSame('services/implants', $category->routeCanonical()?->path);
        $this->get('/services')->assertNotFound();
        $this->get('/services/implants')->assertOk();
    }

    #[Test]
    public function the_trail_starts_with_what_stands_at_the_prefix(): void
    {
        $category = $this->category('implants');
        $this->service('crowns')->syncCategories([$category->getKey()]);

        // Nothing there yet: no step rather than one that leads to a 404.
        $this->assertSame(['Home', 'Implants', 'Crowns'], $this->names('/services/crowns'));

        $landing = $this->landing('What we do', published: false);

        $this->assertNotContains('What we do', $this->names('/services/crowns'));

        $landing->update(['published' => true]);

        $this->assertSame(['Home', 'What we do', 'Implants', 'Crowns'], $this->names('/services/crowns'));
    }

    private function landing(string $title, bool $published): Landing
    {
        Schema::create('landings', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('published');
        });

        $landing = Landing::query()->create(['title' => $title, 'published' => $published]);

        Route::query()->create([
            'locale' => 'en',
            'path' => 'services',
            'kind' => Route::CANONICAL,
            'entity_type' => $landing->getMorphClass(),
            'entity_id' => $landing->getKey(),
        ]);

        return $landing;
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        $page = (string) $this->get($url)->assertOk()->getContent();

        return array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name');
    }
}
