<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Closure;
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
 *
 * And `--module`, for the site that outlives its first seed: one module in or out without a
 * second set of everything else, its requirements brought along, and a removal refused when
 * something still seeded stands on it.
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

    #[Test]
    public function a_module_installed_after_the_seed_is_seeded_alone_beside_the_journal(): void
    {
        $this->register(new DemoModule('media', [], $this->tickets('media')), new DemoModule('pages', [], $this->tickets('pages')));
        $this->artisan('webx:demo')->assertSuccessful();

        // Next month's module, on a site whose journal is already there.
        $this->register(new DemoModule('services', ['media'], $this->tickets('services')));

        $this->artisan('webx:demo', ['--module' => ['services']])
            ->doesntExpectOutputToContain('media added')
            ->expectsOutputToContain('webx:demo --remove --module=services takes it back out')
            ->assertSuccessful();

        $this->assertSame(['media', 'pages', 'services'], $this->subjects(), 'Nobody else seeds a second set.');

        $ledger = $this->ledger();
        $ledger->load();
        $this->assertSame(['media', 'pages', 'services'], $ledger->modules());
    }

    #[Test]
    public function a_module_the_journal_already_holds_is_refused_unless_forced(): void
    {
        $this->register(new DemoModule('media', [], $this->tickets('media')), new DemoModule('pages', [], $this->tickets('pages')));
        $this->artisan('webx:demo')->assertSuccessful();

        $this->artisan('webx:demo', ['--module' => ['pages']])
            ->expectsOutputToContain('Demo content of pages is already installed')
            ->expectsOutputToContain('webx:demo --remove --module=pages takes it out again')
            ->assertFailed();

        $this->assertSame(['media', 'pages'], $this->subjects());

        $this->artisan('webx:demo', ['--module' => ['pages'], '--force' => true])->assertSuccessful();

        $this->assertSame(['media', 'pages', 'pages'], $this->subjects(), 'Forced, only that module seeds again.');
    }

    #[Test]
    public function a_requirement_that_is_not_seeded_yet_comes_along_first_and_is_said(): void
    {
        $covers = [];

        $this->register(
            new DemoModule('blog', ['media'], static function (DemoLedger $ledger) use (&$covers): void {
                $covers = $ledger->idsOf('media', Ticket::class);
                $ledger->created(Ticket::query()->create(['subject' => 'blog']));
            }, order: 100),
            new DemoModule('media', [], $this->tickets('media'), order: 500),
            new DemoModule('pages', [], $this->tickets('pages')),
        );

        $this->artisan('webx:demo', ['--module' => ['blog']])
            ->expectsOutputToContain('media added: blog requires it')
            ->assertSuccessful();

        $this->assertSame(['media', 'blog'], $this->subjects(), 'The requirement first, and pages not at all.');
        $this->assertCount(1, $covers, 'The blog found the picture it hangs on.');
    }

    #[Test]
    public function a_module_nobody_installed_is_refused_by_name(): void
    {
        $this->register(new DemoModule('media', [], $this->tickets('media')), new PagesModule);

        $this->artisan('webx:demo', ['--module' => ['medai']])
            ->expectsOutputToContain('No installed module with demo content is called medai')
            ->expectsOutputToContain('These have some:')
            ->assertFailed();

        // Installed, but with nothing to seed, is the same answer.
        $this->artisan('webx:demo', ['--module' => ['pages']])->assertFailed();

        $this->assertSame([], $this->subjects());
    }

    #[Test]
    public function removing_one_module_takes_out_its_part_and_leaves_the_rest_of_the_journal(): void
    {
        $this->register(new DemoModule('media', [], $this->tickets('media')), new DemoModule('pages', [], $this->tickets('pages')));
        $this->artisan('webx:demo')->assertSuccessful();

        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['pages']])
            ->expectsOutputToContain('1 item removed')
            ->assertSuccessful();

        $this->assertSame(['media'], $this->subjects());

        $ledger = $this->ledger();
        $ledger->load();
        $this->assertSame(['media'], $ledger->modules());

        // And removed, it can be seeded on its own again.
        $this->artisan('webx:demo', ['--module' => ['pages']])->assertSuccessful();
        $this->assertSame(['media', 'pages'], $this->subjects());

        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['media', 'pages']])->assertSuccessful();
        $this->assertSame([], $this->subjects());
        $this->assertFileDoesNotExist($ledger->path(), 'The last module out takes the journal with it.');
    }

    #[Test]
    public function removing_what_a_seeded_module_requires_is_refused_with_the_command_that_works(): void
    {
        $this->register(
            new DemoModule('media', [], $this->tickets('media')),
            new DemoModule('blog', ['media'], $this->tickets('blog')),
            new DemoModule('shop', ['blog'], $this->tickets('shop')),
        );
        $this->artisan('webx:demo')->assertSuccessful();

        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['media']])
            ->expectsOutputToContain('blog requires media, and its demo is still seeded.')
            ->expectsOutputToContain('shop requires blog, and its demo is still seeded.')
            ->expectsOutputToContain('webx:demo --remove --module=blog --module=shop --module=media takes them out together')
            ->assertFailed();

        $this->assertSame(['media', 'blog', 'shop'], $this->subjects(), 'Nothing was taken out.');

        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['blog', 'shop', 'media']])->assertSuccessful();
        $this->assertSame([], $this->subjects());
    }

    #[Test]
    public function removing_a_module_the_journal_does_not_hold_says_so(): void
    {
        $this->register(new DemoModule('media', [], $this->tickets('media')), new DemoModule('pages', [], $this->tickets('pages')));
        $this->artisan('webx:demo', ['--module' => ['media']])->assertSuccessful();

        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['pages']])
            ->expectsOutputToContain('Nothing to remove for pages')
            ->assertSuccessful();

        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['nope']])
            ->expectsOutputToContain('No module is called nope')
            ->assertFailed();

        $this->assertSame(['media'], $this->subjects());
    }

    /**
     * One ticket named after the module, so that what is left says who seeded it.
     *
     * @return Closure(DemoLedger): void
     */
    private function tickets(string $id): Closure
    {
        return static function (DemoLedger $ledger) use ($id): void {
            $ledger->created(Ticket::query()->create(['subject' => $id]));
        };
    }

    /**
     * @return list<string>
     */
    private function subjects(): array
    {
        return array_values(Ticket::query()->orderBy('id')->pluck('subject')->map(static fn (mixed $subject): string => (string) $subject)->all());
    }

    private function ledger(): DemoLedger
    {
        return $this->app->make(DemoLedger::class);
    }
}
