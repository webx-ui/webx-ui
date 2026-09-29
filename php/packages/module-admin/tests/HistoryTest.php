<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\Facades\History;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\HistoryPruner;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\History\Mcp\HistoryModule;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\Product;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The journal (WEBX_UI_HISTORY.md §8): what a save writes, what it leaves out, who it says did
 * it, and who may read it back.
 */
final class HistoryTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->text('body')->nullable();
            $table->integer('lft')->default(0);
            $table->string('token')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->types()->register(
            'catalog.product',
            Product::class,
            fields: ['name' => 'Name', 'price' => 'Price'],
            permission: ['catalog.view', 'catalog.manage'],
        );

        $this->types()->register('admins.admin', null, permission: 'admins.view');
    }

    #[Test]
    public function a_save_is_one_row_with_what_it_changed_language_by_language(): void
    {
        $product = Product::query()->create(['name' => ['en' => 'Kettle', 'ru' => 'Чайник'], 'price' => '100', 'is_visible' => true]);

        $product->setTranslations('name', ['en' => 'Kettle', 'ru' => 'Чайник электрический']);
        $product->price = '120';
        $product->is_visible = true; // the same value through its cast: no change
        $product->save();

        $rows = HistoryEntry::query()->orderBy('id')->get();

        $this->assertSame(['created', 'updated'], $rows->pluck('event')->all());
        $this->assertSame('catalog.product', $rows[1]->subject_type);
        $this->assertSame($product->id, $rows[1]->subject_id);
        $this->assertEquals([
            ['field' => 'name.ru', 'from' => 'Чайник', 'to' => 'Чайник электрический'],
            ['field' => 'price', 'from' => '100.00', 'to' => '120.00'],
        ], $this->sorted($rows[1]->changes ?? []));
    }

    #[Test]
    public function service_fields_hidden_ones_and_a_save_of_nothing_are_not_written(): void
    {
        $product = Product::query()->create(['name' => ['en' => 'Kettle']]);

        $product->forceFill(['lft' => 5, 'token' => 'secret'])->save();
        $product->touch();

        $this->assertSame(['created'], HistoryEntry::query()->pluck('event')->all());
    }

    #[Test]
    public function a_long_value_is_written_as_changed_with_its_length(): void
    {
        $product = Product::query()->create(['body' => 'Short']);

        $product->update(['body' => str_repeat('a', 900)]);

        $change = HistoryEntry::query()->where('event', 'updated')->sole()->changes[0] ?? [];

        $this->assertSame(['field' => 'body', 'from' => null, 'to' => null, 'long' => true, 'from_length' => 5, 'to_length' => 900], $change);
    }

    #[Test]
    public function the_row_is_rolled_back_with_the_save(): void
    {
        $product = Product::query()->create(['price' => '100']);

        try {
            DB::transaction(function () use ($product): void {
                $product->update(['price' => '200']);

                throw new RuntimeException('The form failed after the save.');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, HistoryEntry::query()->where('event', 'updated')->count());
    }

    #[Test]
    public function a_run_gathers_the_rows_written_inside_it(): void
    {
        $one = Product::query()->create(['price' => '100']);
        $two = Product::query()->create(['price' => '100']);
        $same = Product::query()->create(['price' => '100']);

        $this->app->make(HistoryContext::class)->set(HistoryContext::PANEL, new Editor([], 3, 'Anna'));

        History::run('catalog.product', ['what' => 'prices.csv', 'profile' => 'prices'], function () use ($one, $two, $same): void {
            $one->update(['price' => '110']);
            $two->update(['price' => '120']);
            // The same price again: a row of a run is only what really changed.
            $same->update(['price' => '100']);
        }, HistoryContext::IMPORT);

        $run = HistoryEntry::query()->where('event', 'run')->sole();
        $rows = HistoryEntry::query()->where('parent_id', $run->id)->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['import', 'import'], $rows->pluck('source')->all());
        $this->assertSame('import', $run->source);
        $this->assertSame('Anna', $run->admin_name);
        $this->assertSame(['what' => 'prices.csv', 'profile' => 'prices', 'rows' => 2], $run->summary);
        $this->assertNull($run->subject_id);

        // After the run, the door's own source again.
        $one->update(['price' => '130']);
        $this->assertSame('panel', HistoryEntry::query()->latest('id')->first()?->source);
    }

    #[Test]
    public function a_failed_run_says_so_and_keeps_what_it_did(): void
    {
        $product = Product::query()->create(['price' => '100']);

        try {
            History::run('catalog.product', ['what' => 'prices.csv'], function () use ($product): void {
                $product->update(['price' => '110']);

                throw new RuntimeException('Line 40: no such product.');
            }, HistoryContext::IMPORT);
        } catch (RuntimeException) {
        }

        $run = HistoryEntry::query()->where('event', 'run')->sole();

        $this->assertSame('Line 40: no such product.', $run->summary['failed'] ?? null);
        $this->assertSame(1, $run->summary['rows'] ?? null);
    }

    #[Test]
    public function an_agent_writes_as_mcp_with_the_administrator_and_the_grant(): void
    {
        $product = Product::query()->create(['price' => '100']);

        $this->app->make(HistoryContext::class)->during(
            HistoryContext::MCP,
            new Editor([], 9, 'Vasya'),
            42,
            fn () => $product->update(['price' => '90']),
        );

        $row = HistoryEntry::query()->where('event', 'updated')->sole();

        $this->assertSame('mcp', $row->source);
        $this->assertSame(9, $row->admin_id);
        $this->assertSame('Vasya', $row->admin_name);
        $this->assertSame(42, $row->grant_id);
    }

    #[Test]
    public function nobody_having_said_anything_in_a_console_is_console(): void
    {
        Product::query()->create(['price' => '100']);

        $row = HistoryEntry::query()->sole();

        $this->assertSame('console', $row->source);
        $this->assertNull($row->admin_id);
    }

    #[Test]
    public function a_module_writes_its_own_words_by_hand(): void
    {
        $product = Product::query()->create(['price' => '100']);

        History::record($product, 'published', ['is_visible' => [false, true]]);
        $this->assertNull(History::record($product, 'updated', ['price' => ['100.00', '100.00']]));

        $row = HistoryEntry::query()->where('event', 'published')->sole();
        $this->assertSame([['field' => 'is_visible', 'from' => false, 'to' => true]], $row->changes);
    }

    #[Test]
    public function a_model_nobody_registered_is_loud(): void
    {
        $this->types()->forget();

        $this->expectException(LogicException::class);

        Product::query()->create(['price' => '100']);
    }

    #[Test]
    public function the_panel_reads_a_feed_newest_first_with_labels_and_the_name_it_was_signed_with(): void
    {
        $product = Product::query()->create(['price' => '100']);

        // Written by an administrator whose account is gone since: the name stays.
        $this->app->make(HistoryContext::class)->during(
            HistoryContext::PANEL,
            new Editor([], 77, 'Deleted Dave'),
            null,
            fn () => $product->update(['price' => '120', 'name' => ['ru' => 'Чайник']]),
        );

        $this->actingAs(new Editor(['catalog.view']))
            ->getJson('/api/cms/history/catalog.product/'.$product->id)
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.event', 'updated')
            ->assertJsonPath('data.0.source', 'panel')
            ->assertJsonPath('data.0.admin.name', 'Deleted Dave')
            ->assertJsonPath('data.1.event', 'created')
            ->assertJsonFragment(['field' => 'price', 'label' => 'Price', 'from' => '100.00', 'to' => '120.00'])
            ->assertJsonFragment(['field' => 'name.ru', 'label' => 'Name (RU)']);

        $this->actingAs(new Editor(['catalog.view']))
            ->getJson('/api/cms/history/catalog.product/'.$product->id.'?field=name')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    #[Test]
    public function a_save_through_the_panels_api_is_panel_with_the_signed_in_administrator(): void
    {
        $this->assertContains('webx.history', (array) config('webx-admin.api_middleware'));

        $product = Product::query()->create(['price' => '100']);

        // A route of the panel's group that saves, the way a module's controller would.
        $this->app->make('router')
            ->middleware((array) config('webx-admin.api_middleware'))
            ->put('api/cms/test-products/{id}', function (int $id) {
                Product::query()->findOrFail($id)->update(['price' => '150']);

                return ['ok' => true];
            });

        $this->actingAs(new Editor(['catalog.manage'], 4, 'Petya'))
            ->putJson('/api/cms/test-products/'.$product->id)
            ->assertOk();

        $row = HistoryEntry::query()->where('event', 'updated')->sole();
        $this->assertSame('panel', $row->source);
        $this->assertSame('Petya', $row->admin_name);
    }

    #[Test]
    public function the_feed_is_behind_the_types_permission(): void
    {
        $product = Product::query()->create(['price' => '100']);

        $this->actingAs(new Editor(['pages.view']))
            ->getJson('/api/cms/history/catalog.product/'.$product->id)
            ->assertForbidden();

        $this->actingAs(new Editor(['catalog.view']))
            ->getJson('/api/cms/history/catalog.unknown/'.$product->id)
            ->assertNotFound();
    }

    #[Test]
    public function a_run_is_read_with_its_rows_and_a_search_by_record(): void
    {
        $one = Product::query()->create(['price' => '100']);
        $two = Product::query()->create(['price' => '100']);

        $run = History::run('catalog.product', ['what' => 'bulk hide'], function ($run) use ($one, $two) {
            $one->update(['is_visible' => false]);
            $two->update(['is_visible' => false]);

            return $run;
        }, HistoryContext::BULK);

        $this->actingAs(new Editor(['catalog.manage']))
            ->getJson('/api/cms/history/runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('run.summary.what', 'bulk hide')
            ->assertJsonPath('run.rows', 2)
            ->assertJsonPath('rows.total', 2);

        $this->actingAs(new Editor(['catalog.manage']))
            ->getJson('/api/cms/history/runs/'.$run->id.'?search='.$two->id)
            ->assertOk()
            ->assertJsonPath('rows.total', 1)
            ->assertJsonPath('rows.data.0.subject.id', $two->id)
            ->assertJsonPath('rows.data.0.run.summary.what', 'bulk hide');

        // A row of the record's own feed links to its run.
        $this->actingAs(new Editor(['catalog.manage']))
            ->getJson('/api/cms/history/catalog.product/'.$two->id)
            ->assertJsonPath('data.0.run.id', $run->id);

        $this->actingAs(new Editor(['admins.view']))
            ->getJson('/api/cms/history/runs/'.$run->id)
            ->assertForbidden();
    }

    #[Test]
    public function pruning_goes_in_batches_and_takes_a_run_whole_or_not_at_all(): void
    {
        config(['webx-admin.history.retention_days' => 30]);

        $old = Carbon::now()->subDays(40);
        $fresh = Carbon::now()->subDays(5);

        foreach (range(1, 5) as $id) {
            $this->row(['subject_id' => $id, 'created_at' => $old]);
        }

        $kept = $this->row(['subject_id' => 99, 'created_at' => $fresh]);

        $oldRun = $this->row(['event' => 'run', 'subject_id' => null, 'created_at' => $old]);
        foreach (range(1, 3) as $id) {
            $this->row(['parent_id' => $oldRun->id, 'subject_id' => $id, 'created_at' => $old]);
        }

        // Started before the line would be, with a row after it: a fresh run keeps all of it.
        $freshRun = $this->row(['event' => 'run', 'subject_id' => null, 'created_at' => $fresh]);
        $this->row(['parent_id' => $freshRun->id, 'subject_id' => 1, 'created_at' => $fresh]);

        $removed = $this->app->make(HistoryPruner::class)->prune(batch: 2);

        $this->assertSame(9, $removed);
        $this->assertEqualsCanonicalizing(
            [$kept->id, $freshRun->id],
            HistoryEntry::query()->whereNull('parent_id')->pluck('id')->all(),
        );
        $this->assertSame(1, HistoryEntry::query()->where('parent_id', $freshRun->id)->count());

        $this->artisan('webx:history:prune')->assertSuccessful();
    }

    #[Test]
    public function history_get_refuses_a_reader_without_the_types_permission(): void
    {
        $product = Product::query()->create(['price' => '100']);
        $product->update(['price' => '110']);

        $get = $this->tool('get');

        $answer = ($get->handler)(['subject_type' => 'catalog.product', 'subject_id' => $product->id], new Editor(['catalog.view']));
        $this->assertSame(2, $answer['total']);
        $this->assertSame('Price', $answer['entries'][0]['changes'][0]['label']);

        $this->expectException(ToolFailure::class);
        $this->expectExceptionMessage('may not read the history of catalog.product');

        ($get->handler)(['subject_type' => 'catalog.product', 'subject_id' => $product->id], new Editor(['admins.view']));
    }

    #[Test]
    public function history_runs_shows_only_the_runs_of_types_the_reader_may_read(): void
    {
        History::run('catalog.product', ['what' => 'prices'], static fn () => null, HistoryContext::IMPORT);
        History::run('admins.admin', ['what' => 'roles'], static fn () => null, HistoryContext::BULK);

        $runs = $this->tool('runs');

        $catalog = ($runs->handler)([], new Editor(['catalog.view']));
        $this->assertSame(['catalog.product'], array_column(array_column($catalog['runs'], 'subject'), 'type'));

        $both = ($runs->handler)(['source' => 'bulk'], new Editor(['catalog.view', 'admins.view']));
        $this->assertSame(['admins.admin'], array_column(array_column($both['runs'], 'subject'), 'type'));

        $byModule = ($runs->handler)(['module' => 'catalog'], new Editor(['catalog.view', 'admins.view']));
        $this->assertSame(1, $byModule['total']);
    }

    #[Test]
    public function the_tools_appear_with_the_first_type_and_carry_every_types_permission(): void
    {
        $module = $this->registry()->get('history');

        $this->assertInstanceOf(HistoryModule::class, $module);
        $this->assertSame(['get', 'runs'], array_map(static fn (Tool $tool): string => $tool->name, $module->mcpTools()));
        $this->assertSame(['catalog.view', 'catalog.manage', 'admins.view'], $module->mcpTools()[0]->permissions);

        $types = ($module->mcpResources()[0]->handler)();
        $this->assertSame('Price', $types['types'][0]['fields']['price']);
    }

    private function types(): HistoryTypes
    {
        return $this->app->make(HistoryTypes::class);
    }

    private function tool(string $name): Tool
    {
        $module = $this->registry()->get('history');
        $this->assertInstanceOf(HistoryModule::class, $module);

        foreach ($module->mcpTools() as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        $this->fail("No tool {$name}.");
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function row(array $values): HistoryEntry
    {
        return HistoryEntry::query()->create($values + [
            'subject_type' => 'catalog.product',
            'event' => 'updated',
            'source' => 'console',
            'admin_name' => '',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    private function sorted(array $changes): array
    {
        usort($changes, static fn (array $a, array $b): int => $a['field'] <=> $b['field']);

        return $changes;
    }
}
