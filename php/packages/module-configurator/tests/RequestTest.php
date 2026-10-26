<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Site\Money;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Mail\SubmissionReceived;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The configuration in a request of the inbox (§11): the server prices it again from the values
 * and the published schema, a price from the browser is never read, a size outside its limits is a
 * 422 the visitor reads, and the configuration goes only to the form the configurator names.
 */
final class RequestTest extends TestCase
{
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

        // A POST without the hidden timestamp of a drawn form reads as a robot (inbox §7).
        $app['config']->set('webx-inbox.antispam.min_seconds', 0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_request_carries_the_configuration_priced_by_the_server(): void
    {
        $this->publish();

        $this->send([
            'construction' => 'corner',
            'h' => 2000,
            'w' => 900,
            'd' => 900,
            'glass' => 'graphite',
            'hardware' => 'gold',
        ], extra: [
            // Whatever the browser says the price is, it is not read.
            'configurator' => ['total' => 1, 'lines' => [['amount' => 1]]],
            'total' => 1,
        ])->assertOk();

        $row = Submission::query()->sole()->value('configurator');
        $this->assertNotNull($row);
        $this->assertNull($row->field_id);
        $this->assertSame('enclosure', $row->type);
        $this->assertSame('Душові', $row->label);

        $payload = (array) $row->payload;

        // 3.6 m²: 8000 + 15 912 + 600 × 3.6 = 26 072; gold 12% = 3128.64; 29 200.64 → 29 200.
        $this->assertSame('configurator', $payload['kind']);
        $this->assertSame(['id' => Configurator::query()->sole()->getKey(), 'key' => 'shower', 'title' => 'Душові'], $payload['configurator']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame(3.6, $payload['area']);
        $this->assertSame(26072, $payload['subtotal']);
        $this->assertSame(29200, $payload['total']);
        $this->assertNull($payload['discount']);
        $this->assertSame('UAH', $payload['currency']);
        $this->assertSame(['construction' => 'corner', 'h' => 2000, 'w' => 900, 'd' => 900, 'glass' => 'graphite', 'hardware' => 'gold'], $payload['values']);
        $this->assertSame(['fixed', 'perM2', 'perM2', 'percent'], array_column($payload['lines'], 'kind'));
        $this->assertSame('Конструкція: Кутова', $payload['lines'][0]['line']);

        // The labels are a snapshot in the request's language.
        $this->assertSame(['step' => 'construction', 'label' => 'Конструкція', 'value' => 'corner', 'option' => 'Кутова'], $payload['selection'][0]);
        $this->assertSame([['key' => 'h', 'label' => 'Висота', 'value' => 2000, 'unit' => 'мм'], ['key' => 'w', 'label' => 'Ширина', 'value' => 900, 'unit' => 'мм'], ['key' => 'd', 'label' => 'Глибина', 'value' => 900, 'unit' => 'мм']], $payload['dimensions']);

        // The text the letter, the export and an agent read.
        $this->assertStringContainsString("Конструкція: Кутова\n", (string) $row->value);
        $this->assertStringContainsString('Висота: 2 000 мм · Ширина: 900 мм · Глибина: 900 мм', str_replace("\u{00A0}", ' ', (string) $row->value));
        $this->assertStringContainsString('Площа: 3,6 m²', (string) $row->value);
        // The symbol is ICU's: `₴` with one version, `UAH` with another.
        $this->assertStringEndsWith('Разом: '.Money::format(29200, 'UAH', 'uk'), (string) $row->value);
    }

    #[Test]
    public function a_discount_in_force_is_in_the_request(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->publish(['percent' => 10, 'until' => '2026-10-31', 'label' => '−10% до 31.10']);

        $this->send(['construction' => 'corner', 'h' => 2000, 'w' => 900, 'd' => 900])->assertOk();

        $payload = (array) Submission::query()->sole()->value('configurator')?->payload;

        // 23 912 − 2 391.20 = 21 520.80 → 21 500; before the discount 23 900.
        $this->assertSame(['percent' => 10, 'amount' => -2391.2, 'label' => '−10% до 31.10'], $payload['discount']);
        $this->assertSame(23900, $payload['total_before_discount']);
        $this->assertSame(21500, $payload['total']);
        $this->assertSame('discount', $payload['lines'][array_key_last($payload['lines'])]['kind']);
        $this->assertStringContainsString('−10% до 31.10 (−10%):', (string) Submission::query()->sole()->value('configurator')?->value);
    }

    #[Test]
    public function after_its_last_day_the_discount_is_not_given(): void
    {
        Carbon::setTestNow('2026-11-01 09:00:00');
        $this->publish(['percent' => 10, 'until' => '2026-10-31', 'label' => '−10% до 31.10']);

        $this->send(['construction' => 'corner', 'h' => 2000, 'w' => 900, 'd' => 900])->assertOk();

        $payload = (array) Submission::query()->sole()->value('configurator')?->payload;

        $this->assertNull($payload['discount']);
        $this->assertSame(23900, $payload['total']);
    }

    #[Test]
    public function a_size_outside_its_limits_is_refused_in_words(): void
    {
        $this->publish();

        $refused = $this->send(['construction' => 'corner', 'h' => 9000, 'w' => 900, 'd' => 900])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['configurator.values.h']);

        // In the words of the limits the visitor saw.
        $message = (string) ($refused->json('errors')['configurator.values.h'][0] ?? '');
        $this->assertStringContainsString('1300', $message);
        $this->assertStringContainsString('2400', $message);

        $this->send(['construction' => 'corner', 'h' => 'tall'])->assertStatus(422)->assertJsonValidationErrors(['configurator.values.h']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function what_the_visitor_could_not_see_is_normalised_not_refused(): void
    {
        $this->publish();

        // The niche has no depth; an unknown glass is the first one; an unknown key is dropped.
        $this->send(['construction' => 'niche', 'h' => 2000, 'w' => 900, 'd' => 99999, 'glass' => 'nothing-like-it', 'extra' => 'x'])->assertOk();

        $payload = (array) Submission::query()->sole()->value('configurator')?->payload;

        $this->assertSame(['construction' => 'niche', 'h' => 2000, 'w' => 900, 'glass' => 'clear', 'hardware' => 'chrome'], $payload['values']);
    }

    #[Test]
    public function a_configuration_goes_only_to_the_form_its_configurator_names(): void
    {
        $this->publish();
        $this->form('contact');

        // A form nobody named for a configurator.
        $this->postJson($this->intake('contact'), [
            'fields' => ['name' => 'Ada', 'phone' => '+800 000 0000'],
            'webx_enclosure' => ['configurator'],
            'configurator' => ['key' => 'shower', 'values' => ['construction' => 'corner']],
        ])->assertStatus(422)->assertJsonValidationErrors(['configurator']);

        // The right form, a configurator that does not name it.
        $writer = app(ConfiguratorWriter::class);
        $other = $writer->create('other', ['uk' => 'Інший'], $this->schema(), 'contact');
        $writer->publish($other);

        $this->send(['construction' => 'corner'], key: 'other')->assertStatus(422)->assertJsonValidationErrors(['configurator.key']);
        $this->send(['construction' => 'corner'], key: 'nobody')->assertStatus(422)->assertJsonValidationErrors(['configurator.key']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function an_unpublished_configurator_takes_no_requests(): void
    {
        $configurator = $this->publish();
        app(ConfiguratorWriter::class);
        $configurator->unpublish();

        $this->send(['construction' => 'corner'])->assertStatus(422)->assertJsonValidationErrors(['configurator.key']);
    }

    #[Test]
    public function two_configurations_from_one_person_are_two_requests(): void
    {
        $this->publish();

        $this->send(['construction' => 'corner', 'h' => 2000, 'w' => 900, 'd' => 900])->assertOk();
        $this->send(['construction' => 'corner', 'h' => 2000, 'w' => 900, 'd' => 900])->assertOk();
        $this->send(['construction' => 'corner', 'h' => 2100, 'w' => 900, 'd' => 900])->assertOk();

        $this->assertSame(2, Submission::query()->count());
    }

    #[Test]
    public function the_letter_carries_the_configuration(): void
    {
        Mail::fake();
        $this->publish();

        $this->send(['construction' => 'corner', 'h' => 2000, 'w' => 900, 'd' => 900])->assertOk();

        $html = (new SubmissionReceived(Submission::query()->sole()))->render();

        $this->assertStringContainsString('Душові', $html);
        $this->assertStringContainsString('Конструкція: Кутова<br />', $html);
    }

    #[Test]
    public function without_the_marks_the_form_is_a_form_like_any_other(): void
    {
        $this->publish();

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'phone' => '+800 000 0000']])->assertOk();

        $this->assertNull(Submission::query()->sole()->value('configurator'));
    }

    /**
     * @param  array<string, mixed>|null  $discount
     */
    private function publish(?array $discount = null): Configurator
    {
        $this->mediaFile();
        $this->form('request');

        $schema = $this->schema();

        if ($discount !== null) {
            $schema['discount'] = $discount;
        }

        $writer = app(ConfiguratorWriter::class);

        return $writer->publish($writer->create('shower', ['uk' => 'Душові'], $schema, 'request'));
    }

    private function form(string $slug): Form
    {
        $form = Form::query()->where('slug', $slug)->first() ?? Form::query()->create(['slug' => $slug, 'title' => ['uk' => 'Запит'], 'is_enabled' => true, 'options' => []]);

        foreach ([['name', FieldType::Text], ['phone', FieldType::Tel]] as $position => [$name, $type]) {
            $form->fields()->firstOrCreate(['name' => $name], ['type' => $type, 'title' => ['uk' => ucfirst($name)], 'is_enabled' => true, 'is_required' => true, 'position' => $position]);
        }

        return $form->refresh();
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $extra
     * @return TestResponse<Response>
     */
    private function send(array $values, string $key = 'shower', array $extra = []): TestResponse
    {
        $configurator = ['key' => $key, 'values' => $values, ...($extra['configurator'] ?? [])];
        unset($extra['configurator']);

        return $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'phone' => '+800 000 0000'],
            'webx_locale' => 'uk',
            'webx_enclosure' => ['configurator'],
            'configurator' => $configurator,
            ...$extra,
        ]);
    }

    private function intake(string $slug = 'request'): string
    {
        return '/'.trim((string) config('webx-inbox.path'), '/').'/'.$slug;
    }
}
