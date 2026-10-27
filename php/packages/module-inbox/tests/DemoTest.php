<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The feedback form every site turns out to need, seeded and taken out again.
 *
 * With nobody to notify on purpose: a demo that mailed somebody the first time a visitor
 * pressed Send would be sending from an address nobody has set up yet.
 */
final class DemoTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: what the pipeline makes of an upload is the media
        // module's test, and turning every demo photo into webp was a third of this one.
        $app['config']->set('webx-media.optimize.enabled', false);
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_one_form_with_three_questions_and_nobody_to_notify(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $form = Form::query()->where('slug', 'contact')->firstOrFail();

        $this->assertSame('Contact us', (string) $form->title);
        $this->assertSame([], $form->recipients());
        $this->assertSame(['name', 'email', 'message'], $form->liveFields()->pluck('name')->all());
        $this->assertSame('Thank you', (string) $form->option('thank-you.heading')['en']);
    }

    /** A theme's `demo/inbox/*.json` comes first: `theme-default` shows every kind of field. */
    #[Test]
    public function the_theme_brings_forms_of_its_own_with_their_choices(): void
    {
        $theme = sys_get_temp_dir().'/webx-inbox-demo-theme-'.getmypid();
        @mkdir($theme.'/demo/inbox', 0777, true);
        file_put_contents($theme.'/demo/inbox/showcase.json', (string) json_encode([
            'slug' => 'showcase',
            'title' => 'Every kind of field',
            'fields' => [
                ['name' => 'topic', 'type' => 'select', 'title' => 'Topic', 'options' => [
                    'choices' => [['value' => 'a', 'label' => ['en' => 'A question']], 'B'],
                    'rows' => 9,
                ]],
                ['name' => 'consent', 'type' => 'consent', 'title' => 'Consent', 'options' => ['text' => ['en' => 'I agree']]],
                ['name' => 'odd', 'type' => 'no-such-type', 'title' => 'Odd'],
            ],
        ]));

        try {
            $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/theme', $theme, false)]));

            $this->artisan('webx:demo')->assertSuccessful();

            $this->assertSame(['contact', 'showcase'], Form::query()->orderBy('slug')->pluck('slug')->all(), 'the module\'s contact form stays beside the theme\'s');

            $fields = Form::query()->where('slug', 'showcase')->firstOrFail()->liveFields()->get()->keyBy('name');
            $this->assertSame(['a' => 'A question', 'B' => 'B'], $fields['topic']->choices());
            $this->assertArrayNotHasKey('rows', $fields['topic']->options, 'options go through the panel\'s gate');
            $this->assertSame('I agree', $fields['consent']->optionText('text'));
            $this->assertSame('text', $fields['odd']->type->value);

            $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
            $this->assertSame(0, Form::query()->count());
        } finally {
            array_map('unlink', glob($theme.'/demo/inbox/*.json') ?: []);
            @rmdir($theme.'/demo/inbox');
            @rmdir($theme.'/demo');
            @rmdir($theme);
        }
    }

    #[Test]
    public function removing_takes_the_form_and_its_questions(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Form::query()->count());
        $this->assertSame(0, Field::withTrashed()->count());
    }
}
