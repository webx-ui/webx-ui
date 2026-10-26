<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Site\Money;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * `<x-webx-configurator>` on the site (§12): everything printed by the server — the steps with
 * what is hidden at the start, the price of the start and its breakdown, «from N», the picture,
 * the discount, the request button and the messengers — and the widget only switching it.
 */
final class SiteTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class, SettingsServiceProvider::class, ...parent::getPackageProviders($app), InboxServiceProvider::class];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_prints_every_step_with_the_price_of_the_start(): void
    {
        $this->publish();

        $html = $this->render();

        $this->assertStringContainsString('<section class="webx-configurator" id="webx-configurator-shower" data-webx-configurator="shower" data-webx-configurator-form="request">', $html);
        $this->assertStringContainsString('<h2 class="webx-configurator__title">Душові</h2>', $html);

        // Every step, the choice of the start selected: a radio in a box, the box marked.
        foreach (['construction', 'size', 'glass', 'hardware'] as $step) {
            $this->assertStringContainsString('data-webx-configurator-step="'.$step.'"', $html);
        }
        $this->assertMatchesRegularExpression('~class="webx-configurator__option webx-configurator__option--card is-selected" data-webx-configurator-option="corner"~', $html);
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[construction\]" value="corner" form="webx-inbox-request"\s+checked~', $html);

        // The sizes of the corner, from its defaults, in their limits — and the steppers printed
        // hidden: without the script they would do nothing.
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[h\]" value="2000" min="1300" max="2400" step="50" form="webx-inbox-request"~', $html);
        $this->assertStringContainsString('data-webx-configurator-by="-1" aria-label="Менше: Висота" aria-controls="webx-configurator-shower-h" hidden', $html);

        // (900 + 900) × 2000 / 1e6 = 3.6 m²: 8000 + 4420 × 3.6 = 23 912, rounded to 100.
        $this->assertStringContainsString('<span class="webx-configurator__total" data-webx-configurator-total>'.e(Money::format(23900, 'UAH', 'uk')).'</span>', $html);
        $this->assertStringContainsString('Конструкція: Кутова', $html);
        $this->assertStringContainsString('3,6', $html);

        // «from N» at the card's defaults: the niche is 5000 + 4000 × 1.8 = 12 200.
        $this->assertStringContainsString('від '.e(Money::format(12200, 'UAH', 'uk')), $html);
        $this->assertStringContainsString('від '.e(Money::format(23900, 'UAH', 'uk')), $html);

        // The picture of the main step's choice, from the library.
        $this->assertMatchesRegularExpression('~<img class="webx-configurator__preview-image" src="[^"]*media/demo/corner\.svg[?"]~', $html);

        // The swatch is data in a custom property, not CSS of the module.
        $this->assertStringContainsString('style="--webx-configurator-swatch: linear-gradient(135deg, #b8862b, #f3d27a)"', $html);

        // The widget reads the schema in the page's language, and the script is printed once.
        $this->assertStringContainsString('<script type="application/json" data-webx-configurator-data>', $html);
        $this->assertStringContainsString('"label":"Кутова"', $html);
        $this->assertSame(1, substr_count($this->render('<x-webx-configurator key="shower" /><x-webx-configurator key="shower" />'), 'webx/configurator/configurator.js?v='));
    }

    #[Test]
    public function what_is_not_offered_at_the_start_is_printed_hidden_and_not_sent(): void
    {
        $this->publish(initial: ['construction' => 'niche']);

        $html = $this->render();

        // The depth is only for the corner: hidden, and disabled so the form does not send it.
        $this->assertMatchesRegularExpression('~data-webx-configurator-field="d"\s+hidden~', $html);
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[d\]"[^>]*disabled~', $html);
    }

    #[Test]
    public function the_request_goes_with_the_configuration_without_javascript_too(): void
    {
        $this->publish();

        $html = $this->render();

        // The opener of the dialog of the form (WIDGETS §6.4) — a link, so `:target` opens it.
        $this->assertStringContainsString('<a class="webx-configurator__submit" href="#webx-form-request" data-webx-form="request" data-webx-sheet>Отримати розрахунок</a>', $html);

        // The radios, the sizes and the two marks of the enclosure belong to the dialog's form.
        $this->assertStringContainsString('<input type="hidden" name="webx_enclosure[]" value="configurator" form="webx-inbox-request">', $html);
        $this->assertStringContainsString('<input type="hidden" name="configurator[key]" value="shower" form="webx-inbox-request">', $html);

        // No price leaves the page.
        $this->assertStringNotContainsString('name="configurator[total]', $html);
    }

    #[Test]
    public function without_the_form_there_is_no_button(): void
    {
        $this->publish(form: null);

        $html = $this->render();

        $this->assertStringNotContainsString('webx-configurator__submit', $html);
        $this->assertStringNotContainsString('webx_enclosure', $html);
        $this->assertStringNotContainsString(' form="', $html);
        $this->assertStringContainsString('data-webx-configurator-total', $html);
    }

    #[Test]
    public function a_discount_in_force_strikes_the_old_price_and_says_its_label(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->publish(discount: ['percent' => 10, 'until' => '2026-10-31', 'label' => '−10% до 31.10']);

        $html = $this->render();

        // 23 912 − 2 391.20 = 21 520.80, rounded to 21 500; the struck-out price is 23 900.
        $this->assertStringContainsString('<span class="webx-configurator__discount" data-webx-configurator-discount >−10% до 31.10</span>', $html);
        $this->assertStringContainsString('<span data-webx-configurator-before-value>'.e(Money::format(23900, 'UAH', 'uk')).'</span>', $html);
        $this->assertStringContainsString('data-webx-configurator-total>'.e(Money::format(21500, 'UAH', 'uk')).'<', $html);
        $this->assertStringContainsString('webx-configurator__line is-discount', $html);
    }

    #[Test]
    public function after_its_last_day_the_discount_is_gone(): void
    {
        Carbon::setTestNow('2026-11-01 09:00:00');
        $this->publish(discount: ['percent' => 10, 'until' => '2026-10-31', 'label' => '−10% до 31.10']);

        $html = $this->render();

        $this->assertMatchesRegularExpression('~data-webx-configurator-discount\s+hidden~', $html);
        $this->assertMatchesRegularExpression('~data-webx-configurator-before\s+hidden~', $html);
        $this->assertStringContainsString('data-webx-configurator-total>'.e(Money::format(23900, 'UAH', 'uk')).'<', $html);
        $this->assertStringNotContainsString('is-discount', $html);
    }

    #[Test]
    public function the_messengers_of_the_contacts_stand_beside_the_button(): void
    {
        $this->publish();

        app(Settings::class)->save([
            'contacts.phones' => [['number' => '+800 8765 4321', 'label' => ['uk' => 'Офіс'], 'primary' => true, 'messengers' => ['viber']]],
            'contacts.messengers' => [['channel' => 'telegram', 'url' => 'https://t.me/example_bot']],
        ]);

        $html = $this->render();

        $this->assertStringContainsString('class="webx-configurator__messenger webx-configurator__messenger--telegram" href="https://t.me/example_bot" target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('webx-configurator__messenger--viber', $html);
        $this->assertStringContainsString('Написати нам у Telegram', $html);
        $this->assertStringContainsString('<svg class="webx-configurator__messenger-icon"', $html);
    }

    #[Test]
    public function the_heading_and_the_preset_of_a_block_win_and_a_stale_preset_is_dropped(): void
    {
        $this->publish();

        $html = $this->render('<x-webx-configurator key="shower" title="-" :preset="[\'construction\' => \'niche\', \'w\' => 1000, \'gone\' => \'x\', \'glass\' => \'nothing-like-it\']" />');

        $this->assertStringNotContainsString('webx-configurator__title', $html);
        $this->assertMatchesRegularExpression('~data-webx-configurator-option="niche"~', $html);
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[construction\]" value="niche" form="webx-inbox-request"\s+checked~', $html);
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[w\]" value="1000"~', $html);
        // An unknown glass falls back to the first one, as the engine does.
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[glass\]" value="clear" form="webx-inbox-request"\s+checked~', $html);

        $this->assertStringContainsString('<h2 class="webx-configurator__title">Своя</h2>', $this->render('<x-webx-configurator key="shower" title="Своя" />'));
    }

    #[Test]
    public function nothing_is_printed_for_a_key_nobody_published(): void
    {
        $this->assertSame('', trim($this->render('<x-webx-configurator key="nobody" />')));

        $this->publish();
        Configurator::query()->sole()->delete();

        $this->assertSame('', trim($this->render()));
    }

    #[Test]
    public function the_site_reads_a_configurator_once_until_it_changes(): void
    {
        config(['cache.default' => 'array', 'webx-cache.enabled' => true]);
        $this->publish();
        $this->render();

        $queries = 0;
        $this->app['db']->listen(static function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'from "configurators"')) {
                $queries++;
            }
        });

        $this->render();
        $this->assertSame(0, $queries, 'a warm page asks the database for no configurator');

        // A publication moves the group, and the next page reads the new schema.
        $configurator = Configurator::query()->sole();
        $writer = app(ConfiguratorWriter::class);
        $schema = $this->schema();
        $schema['steps'][0]['options'][0]['label'] = ['uk' => 'Кутова нова'];
        $writer->update($configurator, ['schema' => $schema]);
        $writer->publish($configurator->refresh());

        $this->assertStringContainsString('Кутова нова', $this->render());
    }

    /**
     * @param  array<string, mixed>|null  $initial
     * @param  array<string, mixed>|null  $discount
     */
    private function publish(?string $form = 'request', ?array $initial = null, ?array $discount = null): Configurator
    {
        $this->mediaFile();

        if ($form !== null && ! Form::query()->where('slug', $form)->exists()) {
            Form::query()->create(['slug' => $form, 'title' => ['uk' => 'Запит'], 'is_enabled' => true, 'options' => []]);
        }

        $schema = $this->schema();

        if ($initial !== null) {
            $schema['initial'] = $initial;
        }

        if ($discount !== null) {
            $schema['discount'] = $discount;
        }

        $writer = app(ConfiguratorWriter::class);
        $configurator = $writer->create('shower', ['uk' => 'Душові'], $schema, $form);

        return $writer->publish($configurator);
    }

    private function render(string $blade = '<x-webx-configurator key="shower" />'): string
    {
        // A fresh request each time: the script is printed once per page, not once per test.
        $this->app->forgetScopedInstances();
        $this->app->setLocale('uk');

        return Blade::render($blade);
    }
}
