<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;
use WebxUi\Vacancies\VacanciesServiceProvider;

/**
 * `webx-vacancies.index` off (decision 1): the prefix stays, the route under it goes, and the
 * address is free for a page of `module-pages` — which gets it, and which the trail of a vacancy
 * then starts with (CLAUDE.md §4 on `Reserved`).
 */
final class NoIndexTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-vacancies.index', false);
    }

    #[Test]
    public function the_route_is_gone_and_a_page_takes_the_address_and_the_first_step_of_the_trail(): void
    {
        $this->assertFalse($this->app['router']->has(VacanciesServiceProvider::INDEX_ROUTE));

        $this->vacancy('designer');

        // Nothing at the prefix yet: no step rather than one that leads to a 404.
        $this->assertSame(['Home', 'Designer'], $this->names('/careers/designer'));

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'Join us', 'slug' => 'careers']);
        $page->appendTo($home);
        $page->publish();

        $this->assertSame('careers', $page->refresh()->routeCanonical()?->path);
        $this->assertSame(['Home', 'Join us', 'Designer'], $this->names('/careers/designer'));
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        $trail = $this->jsonLd((string) $this->get($url)->assertOk()->getContent(), 'BreadcrumbList');

        $this->assertIsArray($trail);

        return array_map(static fn (array $item): string => (string) $item['name'], (array) $trail['itemListElement']);
    }
}
