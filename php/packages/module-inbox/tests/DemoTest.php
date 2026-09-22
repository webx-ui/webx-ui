<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;

/**
 * The feedback form every site turns out to need, seeded and taken out again.
 *
 * With nobody to notify on purpose: a demo that mailed somebody the first time a visitor
 * pressed Send would be sending from an address nobody has set up yet.
 */
final class DemoTest extends TestCase
{
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

    #[Test]
    public function removing_takes_the_form_and_its_questions(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Form::query()->count());
        $this->assertSame(0, Field::withTrashed()->count());
    }
}
