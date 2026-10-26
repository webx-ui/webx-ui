<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The query budget of a configurator (§12.6, WEBX_UI_PERFORMANCE.md): on the site cold and warm,
 * and the list and the record of the panel. The published configurator is cached by key and its
 * pictures are read in one query, so a page with two configurators costs no more than with one;
 * a change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class, ...parent::getPackageProviders($app), InboxServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-cache.enabled', true);
    }

    #[Test]
    public function a_configurator_on_the_site_fits_its_budget_cold_and_warm(): void
    {
        $this->published('shower');
        $page = fn (): string => $this->render('<x-webx-configurator key="shower" />');

        // Cold: the configurator and its version, the form, the pictures — once each.
        $this->assertQueriesWithin(6, $page, 'cold');

        // Warm: the configurator comes from the cache; the pictures and the form are one query each.
        $this->assertQueriesWithin(3, $page, 'warm');
        $this->assertNoRepeatedQueries($page, 'warm');
    }

    #[Test]
    public function two_configurators_ask_no_more_per_configurator(): void
    {
        $this->published('shower');
        $this->published('doors');
        $two = fn (): string => $this->render('<x-webx-configurator key="shower" /><x-webx-configurator key="doors" />');
        $two();

        // Two configurators, each its own pictures: never one query per picture.
        $this->assertQueriesWithin(4, $two);
    }

    #[Test]
    public function the_list_and_a_record_of_the_panel_fit_their_budgets(): void
    {
        foreach (['shower', 'doors', 'windows'] as $key) {
            $this->published($key);
        }

        $editor = $this->editor();
        $list = '/api/cms/configurators';
        $one = $list.'/'.Configurator::query()->orderBy('id')->value('id');

        foreach ([[$list, 12], [$one, 14]] as [$url, $budget]) {
            $this->actingAs($editor, 'cms')->getJson($url)->assertOk();

            $this->assertQueriesWithin($budget, fn () => $this->actingAs($editor, 'cms')->getJson($url)->assertOk(), $url);
        }

        $this->assertNoRepeatedQueries(fn () => $this->actingAs($editor, 'cms')->getJson($list)->assertOk(), $list);
    }

    private function published(string $key): Configurator
    {
        if (! Form::query()->where('slug', 'request')->exists()) {
            $this->mediaFile();
            Form::query()->create(['slug' => 'request', 'title' => ['uk' => 'Запит'], 'is_enabled' => true, 'options' => []]);
        }

        $writer = app(ConfiguratorWriter::class);

        return $writer->publish($writer->create($key, ['uk' => ucfirst($key)], $this->schema(), 'request'));
    }

    private function render(string $blade): string
    {
        $this->app->forgetScopedInstances();

        return Blade::render($blade);
    }
}
