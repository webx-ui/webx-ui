<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Tests\Fixtures\DemoModule;
use WebxUi\Admin\Tests\Fixtures\PagesModule;
use WebxUi\Admin\Tests\Fixtures\Ticket;

/**
 * Demo content, and the journal that is the only way back out of it.
 *
 * Four things here would be quiet if they broke, and all four are about the command rather
 * than about anything a module seeds: a requirement that is not installed has to skip with a
 * reason instead of failing halfway; the order has to follow the requirements and not the
 * navigation; the removal has to run backwards; and a record that was already there has to be
 * put back rather than deleted — a site left with no home page is not a site.
 */
final class DemoCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->ledger()->path());

        parent::tearDown();
    }

    #[Test]
    public function a_module_whose_requirement_is_not_installed_is_skipped_with_the_reason(): void
    {
        $this->register(
            new DemoModule('blog', ['media'], static function (DemoLedger $ledger): void {
                $ledger->created(Ticket::query()->create(['subject' => 'An article']));
            }),
        );

        $this->artisan('webx:demo')
            ->expectsOutputToContain('blog skipped: no media.')
            ->assertSuccessful();

        $this->assertSame(0, Ticket::query()->count(), 'Nothing should have been seeded.');
    }

    #[Test]
    public function a_module_is_seeded_after_what_it_requires_whatever_the_navigation_says(): void
    {
        $seeded = [];
        $seeder = static function (string $id) use (&$seeded): callable {
            return static function () use ($id, &$seeded): void {
                $seeded[] = $id;
            };
        };

        // The navigation puts `pages` first, exactly as the real panel does; its requirement
        // is last in it.
        $this->register(
            new DemoModule('pages', ['blocks'], $seeder('pages'), order: 200),
            new DemoModule('blocks', [], $seeder('blocks'), order: 600),
        );

        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(['blocks', 'pages'], $seeded);
    }

    #[Test]
    public function a_module_without_the_interface_is_walked_past(): void
    {
        $this->register(new PagesModule);

        $this->artisan('webx:demo')
            ->expectsOutputToContain('No installed module has demo content')
            ->assertSuccessful();
    }

    #[Test]
    public function the_journal_says_what_was_created_and_the_removal_plays_it_backwards(): void
    {
        $this->register(new DemoModule('tickets', [], static function (DemoLedger $ledger): void {
            $ledger->created(Ticket::query()->create(['subject' => 'The first']), 'The first');
            $ledger->created(Ticket::query()->create(['subject' => 'The second']), 'The second');
        }));

        $this->artisan('webx:demo')->assertSuccessful();

        $ledger = $this->ledger();
        $ledger->load();

        $this->assertFileExists($ledger->path());
        $this->assertSame(['The first', 'The second'], array_column($ledger->entries(), 'label'));
        $this->assertSame(2, Ticket::query()->count());

        $order = [];
        Ticket::deleting(static function (Ticket $ticket) use (&$order): void {
            $order[] = $ticket->subject;
        });

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(['The second', 'The first'], $order, 'The journal is played backwards.');
        $this->assertSame(0, Ticket::query()->count());
        $this->assertFileDoesNotExist($ledger->path());
    }

    #[Test]
    public function a_record_that_was_already_there_is_put_back_rather_than_deleted(): void
    {
        $ticket = Ticket::query()->create(['subject' => 'Written by somebody else']);

        $this->register(new DemoModule('tickets', [], static function (DemoLedger $ledger) use ($ticket): void {
            $ledger->changed($ticket, ['subject']);

            $ticket->subject = 'Filled in by the demo';
            $ticket->save();
        }));

        $this->artisan('webx:demo')->assertSuccessful();
        $this->assertSame('Filled in by the demo', (string) $ticket->fresh()?->subject);

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame('Written by somebody else', (string) $ticket->fresh()?->subject);
        $this->assertSame(1, Ticket::query()->count(), 'The record itself stays.');
    }

    #[Test]
    public function it_refuses_to_seed_over_a_journal_that_is_already_there(): void
    {
        $this->register(new DemoModule('tickets', [], static function (DemoLedger $ledger): void {
            $ledger->created(Ticket::query()->create(['subject' => 'The first']));
        }));

        $this->artisan('webx:demo')->assertSuccessful();

        $this->artisan('webx:demo')
            ->expectsOutputToContain('already installed')
            ->assertFailed();

        $this->assertSame(1, Ticket::query()->count());

        // Forced, the second set joins the first in the same journal, so that one removal
        // takes out both.
        $this->artisan('webx:demo', ['--force' => true])->assertSuccessful();
        $this->assertSame(2, Ticket::query()->count());

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
        $this->assertSame(0, Ticket::query()->count());
    }

    #[Test]
    public function removing_what_was_never_seeded_says_so_and_is_not_a_failure(): void
    {
        $this->artisan('webx:demo', ['--remove' => true])
            ->expectsOutputToContain('Nothing to remove')
            ->assertSuccessful();
    }

    private function ledger(): DemoLedger
    {
        return $this->app->make(DemoLedger::class);
    }
}
