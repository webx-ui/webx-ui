<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;

/**
 * The settings of a form for its page (FORM_STEPS §3.6, §11): «Next» held until a step is answered,
 * the dialog scrolled as a whole, the words and the link of the button under the thank-you. Each is
 * off by default: a form saved before them prints what it always did.
 */
final class FormSiteOptionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
    }

    #[Test]
    public function the_panel_keeps_the_settings_and_refuses_a_link_that_would_run_script(): void
    {
        $form = $this->form('contact');
        $save = fn (array $options) => $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api("forms/{$form->getKey()}"), ['slug' => 'contact', 'title' => ['en' => 'Contact'], 'options' => $options]);

        $save(['thank-you.button-link' => ['target' => 'url', 'url' => 'javascript:alert(1)']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['options.thank-you.button-link']);
        $save(['dialog.scroll' => 'sideways'])->assertStatus(422)->assertJsonValidationErrors(['options.dialog.scroll']);

        $save([
            'thank-you.button' => ['en' => 'Back to Homepage', 'ru' => ''],
            'thank-you.button-link' => ['target' => 'url', 'url' => '/'],
            'steps.hold-next' => true,
            'dialog.scroll' => 'page',
        ])->assertOk();

        $options = $form->refresh()->options;
        $this->assertSame(['en' => 'Back to Homepage'], $options['thank-you.button']);
        $this->assertSame('/', $options['thank-you.button-link']['url']);
        $this->assertTrue($options['steps.hold-next']);
        $this->assertSame('page', $options['dialog.scroll']);

        // Off is not written down.
        $save(['steps.hold-next' => false, 'dialog.scroll' => 'box', 'thank-you.button-link' => null])->assertOk();
        $this->assertSame([], array_intersect_key($form->refresh()->options ?? [], array_flip(['steps.hold-next', 'dialog.scroll', 'thank-you.button-link'])));
    }

    #[Test]
    public function the_page_holds_next_and_prints_the_button_under_the_thank_you(): void
    {
        $this->steps([
            'steps.hold-next' => true,
            'thank-you.button' => ['en' => 'Back to Homepage'],
            'thank-you.button-link' => ['target' => 'url', 'url' => '/'],
        ]);

        $html = $this->page('<x-webx-inbox::form slug="contact" />');

        $this->assertMatchesRegularExpression('~<form [^>]*data-webx-steps-hold=""~', $html);
        $this->assertStringContainsString('<a class="webx-form__done" href="'.url('/').'" data-webx-done hidden>Back to Homepage</a>', $html);
        $this->assertStringNotContainsString('data-webx-dialog-close hidden>Close', $html);
    }

    #[Test]
    public function a_form_without_the_settings_prints_what_it_always_did(): void
    {
        $this->steps();

        $html = $this->page('<x-webx-inbox::form slug="contact" />');

        $this->assertStringNotContainsString('data-webx-steps-hold', $html);
        $this->assertStringContainsString('<button type="button" class="webx-form__done" data-webx-dialog-close hidden>Close</button>', $html);
    }

    /**
     * A form of two steps.
     *
     * @param  array<string, mixed>  $options
     */
    private function steps(array $options = []): void
    {
        $this->form('contact', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'is_required' => true, 'options' => ['choices' => [['value' => 'visit', 'label' => ['en' => 'A visit']]]]],
            ['name' => 'contacts', 'type' => FieldType::Step, 'title' => ['en' => 'Contacts']],
            ['name' => 'name', 'type' => FieldType::Text, 'is_required' => true],
        ], $options);
    }

    private function page(string $blade): string
    {
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }
}
