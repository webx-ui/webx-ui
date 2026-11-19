<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

/**
 * The step drawn again speaks the language of the page that asked, not the application's.
 *
 * A site whose application language is English and whose own languages are Russian (the main one)
 * and Ukrainian: the address of the API has no language, so a Ukrainian checkout got its step back
 * with English words and the methods, points and town named in the main language.
 */
final class PageLanguageTest extends StepTestCase
{
    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('webx-localization.locales', [['code' => 'ru', 'default' => true], ['code' => 'uk']]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->townA->update(['names' => ['ru' => 'Город А', 'uk' => 'Місто А']]);
        $this->pickup->update(['titles' => ['ru' => 'Самовывоз из магазина', 'uk' => 'Самовивіз із магазину']]);
        $this->courier->update(['titles' => ['ru' => 'Наш курьер', 'uk' => 'Наш кур’єр']]);
        $this->point($this->townA, 'store', ['titles' => ['ru' => 'Магазин В', 'uk' => 'Магазин В-укр'], 'number' => '3']);
    }

    #[Test]
    public function the_step_of_a_ukrainian_page_comes_back_ukrainian(): void
    {
        $html = $this->step(['X-Webx-Locale' => 'uk']);

        $this->assertStringContainsString('Спосіб доставки', $html);
        $this->assertStringContainsString('Змінити', $html);
        $this->assertStringContainsString('Самовивіз із магазину', $html);
        $this->assertStringContainsString('Наш кур’єр', $html);
        $this->assertStringContainsString('Магазин В-укр', $html);
        $this->assertStringContainsString('Місто А', $html);
        // The lists the step's widgets fetch by ready addresses carry the language too.
        $this->assertStringContainsString('locale=uk', $html);

        foreach (['Delivery method', 'Change', 'Самовывоз из магазина', 'Город А'] as $other) {
            $this->assertStringNotContainsString($other, $html);
        }
    }

    #[Test]
    public function without_the_header_it_is_the_sites_main_language_not_the_applications(): void
    {
        $html = $this->step();

        $this->assertStringContainsString('Способ доставки', $html);
        $this->assertStringContainsString('Самовывоз из магазина', $html);
        $this->assertStringContainsString('Город А', $html);
        $this->assertStringNotContainsString('Delivery method', $html);
    }

    #[Test]
    public function the_methods_and_a_list_by_its_ready_address_are_in_the_language_asked(): void
    {
        $methods = $this->withCart()
            ->getJson("/api/delivery/methods?locality={$this->townA->id}", ['X-Webx-Locale' => 'uk'])
            ->assertOk();

        $this->assertSame('Місто А, Region A', $methods->json('locality.label'));
        $this->assertContains('Самовивіз із магазину', array_column((array) $methods->json('methods'), 'title'));

        $towns = $this->getJson('/api/delivery/localities/popular?country=XX&locale=uk')->assertOk();
        $this->assertContains('Місто А', array_column((array) $towns->json('options'), 'label'));
    }

    /** @param  array<string, string>  $headers */
    private function step(array $headers = []): string
    {
        return (string) $this->withCart()
            ->withHeaders(['X-Webx-Delivery-View' => 'step', ...$headers])
            ->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $this->pickup->id])
            ->assertOk()
            ->json('html');
    }
}
