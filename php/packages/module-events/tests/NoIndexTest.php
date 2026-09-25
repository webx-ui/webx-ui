<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\EventsServiceProvider;
use WebxUi\Pages\Models\Page;

/**
 * `webx-events.index` off (decision 12): the prefix stays, the route under it goes, and the
 * address is free for a page of `module-pages` — which gets it, and which the trail of an event
 * then starts with (CLAUDE.md §4 on `Reserved`). The calendar files stay: they are the events'.
 */
final class NoIndexTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-events.index', false);
    }

    #[Test]
    public function the_route_is_gone_and_a_page_takes_the_address_and_the_first_step_of_the_trail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00'));

        $this->assertFalse($this->app['router']->has(EventsServiceProvider::INDEX_ROUTE));
        $this->assertTrue($this->app['router']->has(EventsServiceProvider::ICS_ROUTE));

        $classes = $this->category('cooking-classes');
        $event = $this->event('dumplings', '2026-10-12 10:00:00');
        $event->syncCategories([$classes->id]);

        // Nothing at the prefix yet: no step rather than one that leads to a 404.
        $this->assertSame(['Home', 'Cooking classes', 'Dumplings'], $this->names('/events/dumplings'));

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'What is on', 'slug' => 'events']);
        $page->appendTo($home);
        $page->publish();

        $this->assertSame('events', $page->refresh()->routeCanonical()?->path);
        $this->assertSame(['Home', 'What is on', 'Cooking classes', 'Dumplings'], $this->names('/events/dumplings'));
        $this->assertSame(['Home', 'What is on', 'Cooking classes'], $this->names('/events/cooking-classes'));

        $this->get('/events/dumplings.ics')->assertOk();
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        $page = (string) $this->get($url)->assertOk()->getContent();

        return array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name');
    }
}
