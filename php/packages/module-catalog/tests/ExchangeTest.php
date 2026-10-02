<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Uploads\FreeSpace;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Exchange\Columns\ValueColumn;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeFormats;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Exchange\Exporter;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\Importer;
use WebxUi\Catalog\Exchange\ProcessExchangeChunk;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Catalog\Panel\ProductForm;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Tests\Fixtures\IndexingEngine;
use WebxUi\Catalog\Tests\Fixtures\NotePart;

/**
 * The exchange on the core's columns (§13 of the exchange spec): a file exported and sent back
 * changes nothing; the keys, the modes, empty cells; a bad row alone rolled back; a check writes
 * nothing; categories by path; "not in the file"; languages; one mark per chunk; permissions;
 * what a CSV does not say about itself; XLSX; a chunk that died repeated without doubles.
 */
final class ExchangeTest extends TestCase
{
    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        // The pieces of an upload in a folder of the test's own, never short of room.
        $this->uploads = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webx-exchange-uploads-'.bin2hex(random_bytes(4));
        $this->app->instance(FreeSpace::class, new class extends FreeSpace
        {
            public function bytes(string $path): int
            {
                return PHP_INT_MAX;
            }
        });
        $this->app->singleton(Uploads::class, fn ($app): Uploads => new Uploads(
            $app->make('config'),
            $app->make(UploadPurposes::class),
            $app->make(FreeSpace::class),
            $this->uploads,
        ));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->uploads);

        parent::tearDown();
    }

    #[Test]
    public function a_csv_exported_and_imported_back_changes_nothing(): void
    {
        $this->roundTrip('csv');
    }

    #[Test]
    public function an_xlsx_exported_and_imported_back_changes_nothing(): void
    {
        $this->roundTrip('xlsx');
    }

    #[Test]
    public function a_row_is_found_by_its_article_number_its_outside_id_or_its_id(): void
    {
        $shoes = $this->category('shoes');
        $boot = $this->product('Boot', $shoes, ['sku' => 'B-1', 'external_id' => 'erp-7']);

        $this->import("sku,name\nB-1,Boot one\n");
        $this->assertSame('Boot one', $boot->refresh()->displayName());

        $this->import("external_id,name\nerp-7,Boot two\n", ['key' => 'external_id']);
        $this->assertSame('Boot two', $boot->refresh()->displayName());

        $this->import("id,name\n{$boot->id},Boot three\n", ['key' => 'id']);
        $this->assertSame('Boot three', $boot->refresh()->displayName());

        // An id nobody has is a new product, and it does not take that id.
        $run = $this->import("id,name\n9999,Sandal\n", ['key' => 'id']);
        $this->assertSame(1, $run->created);
        $this->assertNull(Product::query()->find(9999));

        $this->assertSame(2, Product::query()->count());
    }

    #[Test]
    public function a_product_in_the_bin_is_an_error_of_its_row_and_not_a_second_product(): void
    {
        $old = $this->product('Old belt', null, ['sku' => 'BELT']);
        $old->delete();

        $run = $this->import("sku,name\nBELT,New belt\n");

        $this->assertSame(0, $run->created);
        $this->assertSame(1, $run->failed);
        $this->assertSame(1, Product::withTrashed()->count());
        $error = DB::table('catalog_exchange_errors')->where('run_id', $run->id)->sole();
        $this->assertSame(2, (int) $error->row);
        $this->assertSame('sku', $error->column);
        $this->assertSame('BELT', $error->value);
        $this->assertStringContainsString('#'.$old->id, (string) $error->message);
    }

    #[Test]
    public function update_only_skips_the_new_and_create_only_skips_the_found(): void
    {
        $this->product('Boot', null, ['sku' => 'B-1']);

        $run = $this->import("sku,name\nB-1,Boot changed\nN-1,New one\n", ['mode' => 'update']);
        $this->assertSame([1, 0, 1], [$run->updated, $run->created, $run->skipped]);
        $this->assertNull(Product::query()->where('sku', 'N-1')->first());

        $run = $this->import("sku,name\nB-1,Boot again\nN-1,New one\n", ['mode' => 'create']);
        $this->assertSame([0, 1, 1], [$run->updated, $run->created, $run->skipped]);
        $this->assertSame('Boot changed', Product::query()->where('sku', 'B-1')->sole()->displayName());
    }

    #[Test]
    public function an_empty_cell_leaves_the_field_unless_the_profile_erases_and_an_unmapped_column_is_never_touched(): void
    {
        $boot = $this->product('Boot', null, ['sku' => 'B-1', 'summary' => 'Warm', 'priority' => 5]);

        $this->import("sku,summary,priority\nB-1,,7\n", mapping: ['sku' => 'sku', 'summary' => 'summary', 'priority' => null]);
        $boot->refresh();
        $this->assertSame('Warm', $boot->getTranslation('summary', 'en'));
        $this->assertSame(5, $boot->priority, 'An unmapped column is not imported.');

        $this->import("sku,summary\nB-1,\n", ['empty_clears' => true]);
        $this->assertNull($boot->refresh()->getTranslation('summary', 'en', false));
    }

    #[Test]
    public function a_bad_row_is_rolled_back_alone_and_said_where_and_why(): void
    {
        $shoes = $this->category('shoes');

        $run = $this->import("sku,name,price,category\nA-1,First,10,shoes\nA-2,Second,ten,shoes\nA-3,Third,30,shoes\n", ['create_missing' => false]);

        $this->assertSame(2, $run->created);
        $this->assertSame(1, $run->failed);
        $this->assertSame(['A-1', 'A-3'], Product::query()->orderBy('sku')->pluck('sku')->all());
        $this->assertSame($shoes->id, Product::query()->where('sku', 'A-3')->value('category_id'));

        $error = DB::table('catalog_exchange_errors')->where('run_id', $run->id)->sole();
        $this->assertSame([3, 'price', 'ten'], [(int) $error->row, $error->column, $error->value]);
        $this->assertSame(__('webx-catalog::exchange.errors.not-a-number'), $error->message);
    }

    #[Test]
    public function the_form_refusing_a_row_names_the_column(): void
    {
        $run = $this->import("sku,name,is_published\nA-1,Orphan,1\n");

        $this->assertSame(1, $run->failed);
        $error = DB::table('catalog_exchange_errors')->where('run_id', $run->id)->sole();
        $this->assertSame(__('webx-catalog::errors.publish-needs-category'), $error->message);
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function a_check_writes_nothing_and_still_lists_every_error(): void
    {
        $boot = $this->product('Boot', null, ['sku' => 'B-1']);
        $history = HistoryEntry::query()->count();

        $run = $this->import(
            "sku,name,category,price\nB-1,Boot changed,Shoes/Winter,10\nN-1,New,Shoes/Summer,5\nN-2,Bad,Shoes,cheap\n",
            ['create_missing' => true],
            dryRun: true,
        );

        $this->assertTrue($run->dry_run);
        $this->assertSame(ExchangeRun::DONE, $run->status);
        $this->assertSame([1, 1, 1], [$run->updated, $run->created, $run->failed]);
        $this->assertSame('Boot', $boot->refresh()->displayName());
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(0, Category::withTrashed()->count(), 'The categories the check created went with it.');
        $this->assertSame($history, HistoryEntry::query()->count(), 'Nothing in the journal, not even a run.');
        $this->assertSame(1, DB::table('catalog_exchange_errors')->where('run_id', $run->id)->count());
    }

    #[Test]
    public function categories_by_path_are_created_when_asked_and_refused_otherwise(): void
    {
        $electronics = $this->category('electronics');
        $electronics->update(['name' => 'Electronics']);

        $refused = $this->import("sku,name,category\nP-1,Phone,Electronics/Phones\n");
        $this->assertSame(1, $refused->failed);
        $this->assertStringContainsString('Electronics/Phones', (string) DB::table('catalog_exchange_errors')->where('run_id', $refused->id)->value('message'));

        $run = $this->import("sku,name,category,categories\nP-1,Phone,electronics/phones,Electronics/Phones/Cases;#{$electronics->id}\nP-2,Other phone,Electronics/Phones\n", ['create_missing' => true]);

        $this->assertSame(2, $run->created);
        $phones = Category::query()->where('parent_id', $electronics->id)->sole();
        $this->assertSame('phones', $phones->getTranslation('name', 'en'));
        $cases = Category::query()->where('parent_id', $phones->id)->sole();
        $this->assertTrue($cases->is_published);

        $phone = Product::query()->where('sku', 'P-1')->sole();
        $this->assertSame($phones->id, $phone->category_id);
        $this->assertEqualsCanonicalizing([$cases->id, $electronics->id], $phone->categories()->pluck('catalog_categories.id')->all());
        $this->assertSame($phones->id, Product::query()->where('sku', 'P-2')->value('category_id'), 'The second row finds what the first one created.');
    }

    #[Test]
    public function a_name_repeated_down_the_tree_gets_a_numbered_slug_rather_than_an_error(): void
    {
        // Found by a real shop's run: «Gaskets» under two branches, and every row of the second
        // one refused because the flat slug was taken.
        $run = $this->import("sku,name,category\nG-1,One,Engine/Gaskets\nG-2,Two,Hydraulics/Gaskets\nG-3,Three,Pumps/Gaskets\n", ['create_missing' => true]);

        $this->assertSame([3, 0], [$run->created, $run->failed]);
        $slugs = Category::query()->get()->filter(static fn (Category $one): bool => $one->getTranslation('name', 'en') === 'Gaskets')
            ->map(static fn (Category $one): string => (string) $one->getTranslation('slug', 'en'))->sort()->values()->all();
        $this->assertSame(['gaskets', 'gaskets-2', 'gaskets-3'], $slugs);
    }

    #[Test]
    public function a_path_two_sisters_make_ambiguous_is_refused_with_both_ids(): void
    {
        $one = $this->category('shoes');
        $two = $this->category('shoes-too');
        $two->update(['name' => 'Shoes']);

        $run = $this->import("sku,name,category\nB-1,Boot,Shoes\n");

        $message = (string) DB::table('catalog_exchange_errors')->where('run_id', $run->id)->value('message');
        $this->assertStringContainsString('#'.$one->id, $message);
        $this->assertStringContainsString('#'.$two->id, $message);
    }

    #[Test]
    public function not_in_the_file_unpublishes_only_within_the_categories_of_the_file(): void
    {
        $shoes = $this->category('shoes');
        $belts = $this->category('belts');
        $this->product('Boot', $shoes, ['sku' => 'B-1']);
        $gone = $this->product('Sandal', $shoes, ['sku' => 'B-2']);
        $foreign = $this->product('Belt', $belts, ['sku' => 'L-1']);

        $check = $this->import("sku,price\nB-1,10\n", ['absent' => 'unpublish'], dryRun: true);
        $this->assertSame(1, $check->absent);
        $this->assertTrue($gone->refresh()->is_published, 'A check only counts.');

        $run = $this->import("sku,price\nB-1,10\n", ['absent' => 'unpublish']);

        $this->assertSame(1, $run->absent);
        $this->assertFalse($gone->refresh()->is_published);
        $this->assertTrue($foreign->refresh()->is_published, 'Somebody else\'s range stays.');
        $this->assertNull($gone->deleted_at);
        $this->assertSame(1, HistoryEntry::query()->where('parent_id', $run->history_id)->where('event', HistoryEntry::UNPUBLISHED)->where('subject_id', $gone->id)->count());

        $this->import("sku,price\nB-1,10\n", ['absent' => 'unpublish', 'absent_scope' => 'all']);
        $this->assertFalse($foreign->refresh()->is_published);
    }

    #[Test]
    public function a_run_stopped_on_errors_does_not_take_the_step_not_in_the_file(): void
    {
        config(['webx-catalog.exchange.max_errors' => 1]);
        $shoes = $this->category('shoes');
        $this->product('Boot', $shoes, ['sku' => 'B-1']);
        $other = $this->product('Sandal', $shoes, ['sku' => 'B-2']);

        $run = $this->import("sku,price\nB-1,x\nB-3,y\nB-4,z\n", ['absent' => 'unpublish']);

        $this->assertSame(ExchangeRun::STOPPED, $run->status);
        $this->assertTrue($other->refresh()->is_published);
        $this->assertSame(1, DB::table('catalog_exchange_errors')->where('run_id', $run->id)->count(), 'No more than max_errors are kept.');
    }

    #[Test]
    public function a_column_with_a_language_writes_that_translation(): void
    {
        $this->useLocales('en', 'de');
        $boot = $this->product('Boot', null, ['sku' => 'B-1']);

        $this->import("sku,name,name@de\nB-1,Winter boot,Winterstiefel\n");

        $boot->refresh();
        $this->assertSame('Winter boot', $boot->getTranslation('name', 'en'));
        $this->assertSame('Winterstiefel', $boot->getTranslation('name', 'de'));
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function a_chunk_marks_its_products_for_the_engine_in_one_statement(): void
    {
        config(['webx-catalog.exchange.chunk' => 10]);
        DB::table('catalog_index_queue')->delete();

        DB::enableQueryLog();
        $this->import("sku,name\nA-1,One\nA-2,Two\nA-3,Three\n");
        $statements = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_index_queue'));
        DB::disableQueryLog();

        $this->assertCount(1, $statements);
        $this->assertSame(3, DB::table('catalog_index_queue')->count());
    }

    #[Test]
    public function a_column_behind_a_permission_is_neither_offered_nor_taken(): void
    {
        $this->withNotes();
        $this->app->make(ExchangeColumns::class)->register(new ValueColumn('note', field: 'notes.text'));

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('exchange/columns'))
            ->assertOk()
            ->assertJsonMissing(['key' => 'note'])
            ->assertJsonFragment(['key' => 'sku']);

        $this->actingAs($this->editor([...$this->allCatalog(), 'catalog.notes']), 'cms')
            ->getJson($this->api('exchange/columns'))
            ->assertJsonFragment(['key' => 'note']);

        $this->expectExceptionMessage(__('webx-catalog::exchange.errors.mapping-unknown', ['code' => 'note']));

        $editor = $this->editor();
        $this->app->make(Importer::class)->start(
            ['path' => $this->file("sku,note\nB-1,hello\n")],
            ['sku' => 'sku', 'note' => 'note'],
            [],
            can: static fn (string $permission): bool => $editor->hasPermission($permission),
        );
    }

    #[Test]
    public function creating_a_value_needs_the_right_to_write_it_even_on_the_queue(): void
    {
        $this->withNotes();
        $this->app->make(ExchangeColumns::class)->register(new class implements ExchangeColumn
        {
            public function key(): string
            {
                return 'note';
            }

            public function label(): string
            {
                return 'Note';
            }

            public function field(): string
            {
                return 'notes.text';
            }

            public function localized(): bool
            {
                return false;
            }

            public function export(Collection $products, ?string $locale): array
            {
                return [];
            }

            public function parse(string $cell, ImportContext $context): mixed
            {
                if ($context->createMissing && ! $context->can('catalog.notes.write')) {
                    throw RowError::because('create-forbidden', ['permission' => 'catalog.notes.write']);
                }

                return $cell;
            }
        });

        $editor = $this->editor([...$this->allCatalog(), 'catalog.notes']);
        $run = $this->app->make(Importer::class)->start(
            ['path' => $this->file("sku,name,note\nB-1,Boot,hello\n")],
            ['sku' => 'sku', 'name' => 'name', 'note' => 'note'],
            ['create_missing' => true],
            can: static fn (string $permission): bool => $editor->hasPermission($permission),
        );

        $this->assertSame(1, $run->failed);
        $this->assertSame('note', DB::table('catalog_exchange_errors')->where('run_id', $run->id)->value('column'));
    }

    #[Test]
    public function a_csv_is_read_by_its_bom_its_separator_and_its_encoding(): void
    {
        $formats = $this->app->make(ExchangeFormats::class);
        $csv = $formats->find('csv');
        $this->assertNotNull($csv);

        $bom = $this->file("\xEF\xBB\xBFsku;name\nB-1;Bottes d\xC3\xA9t\xC3\xA9\n");
        $this->assertSame(['encoding' => 'UTF-8', 'delimiter' => ';'], $csv->options($bom));

        $latin = $this->file("sku\tname\nB-2\tCaf\xE9 cr\xE8me\n");
        $options = $csv->options($latin);
        $this->assertSame(['encoding' => 'Windows-1252', 'delimiter' => "\t"], $options);
        $this->assertSame([1 => ['sku', 'name'], 2 => ['B-2', 'Café crème']], iterator_to_array($csv->read($latin, $options)));

        $this->import("\xEF\xBB\xBFsku;name\nB-1;Bottes d\xC3\xA9t\xC3\xA9\n");
        $this->import("sku\tname\nB-2\tCaf\xE9 cr\xE8me\n");

        $this->assertSame('Bottes dété', Product::query()->where('sku', 'B-1')->sole()->displayName());
        $this->assertSame('Café crème', Product::query()->where('sku', 'B-2')->sole()->displayName());
    }

    #[Test]
    public function an_xlsx_keeps_text_as_text_and_numbers_the_rows_as_a_spreadsheet_does(): void
    {
        $xlsx = $this->app->make(ExchangeFormats::class)->find('xlsx');
        $this->assertNotNull($xlsx);
        $path = tempnam(sys_get_temp_dir(), 'webx-test-').'.xlsx';

        $this->assertSame(3, $xlsx->write($path, [['sku', 'description'], ['0012', '=1+1'], ['', '']]));
        $this->assertSame([1 => ['sku', 'description'], 2 => ['0012', '=1+1']], iterator_to_array($xlsx->read($path, [])));

        @unlink($path);
    }

    #[Test]
    public function a_large_file_goes_to_the_queue_and_a_chunk_that_died_is_done_again_once(): void
    {
        config(['webx-catalog.exchange.sync_limit' => 2, 'webx-catalog.exchange.chunk' => 2]);
        Queue::fake();

        $run = $this->import("sku,name\nA-1,One\nA-2,Two\nA-3,Three\nA-4,Four\nA-5,Five\n");

        $this->assertSame(ExchangeRun::QUEUED, $run->status);
        Queue::assertPushed(ProcessExchangeChunk::class, 1);

        $importer = $this->app->make(Importer::class);

        // The second chunk dies after its rows were saved and before the commit — as a worker
        // killed at that moment would.
        $died = false;
        $this->app['events']->listen('eloquent.saving: '.ExchangeRun::class, static function (ExchangeRun $saving) use (&$died): void {
            if ($saving->rows_done === 4 && ! $died) {
                $died = true;

                throw new RuntimeException('killed');
            }
        });

        // Budget of nothing: one chunk per call.
        $this->assertTrue($importer->work($run->id, 0.0));

        try {
            $importer->work($run->id, 0.0);
            $this->fail('The chunk should have died.');
        } catch (RuntimeException $killed) {
            $this->assertSame('killed', $killed->getMessage());
        }

        $this->assertSame(2, $run->refresh()->rows_done, 'The dead chunk is not counted.');
        $this->assertSame(['A-1', 'A-2'], Product::query()->orderBy('sku')->pluck('sku')->all());

        while ($importer->work($run->id, 0.0)) {
            // The rest.
        }

        $run->refresh();
        $this->assertSame(ExchangeRun::DONE, $run->status);
        $this->assertSame([5, 5, 5], [$run->rows_total, $run->rows_done, $run->created]);
        $this->assertSame(['A-1', 'A-2', 'A-3', 'A-4', 'A-5'], Product::query()->orderBy('sku')->pluck('sku')->all());
        $this->assertSame(5, HistoryEntry::query()->where('parent_id', $run->history_id)->count());
        $this->assertSame(['import'], HistoryEntry::query()->where('parent_id', $run->history_id)->distinct()->pluck('source')->all());
    }

    #[Test]
    public function the_api_imports_an_upload_exports_a_selection_and_hands_the_file_out(): void
    {
        $shoes = $this->category('shoes');
        $boot = $this->product('Boot', $shoes, ['sku' => 'B-1']);
        $editor = $this->editor();

        $upload = ['id' => $this->uploaded($editor, "sku,price\nB-1,12.5\n", 'prices.csv')];

        $this->actingAs($editor, 'cms')->postJson($this->api('exchange/inspect'), ['upload_id' => $upload['id']])
            ->assertOk()
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.header', ['sku', 'price'])
            ->assertJsonPath('data.mapping', ['sku' => 'sku', 'price' => 'price']);

        $this->actingAs($editor, 'cms')->postJson($this->api('exchange/import'), [
            'upload_id' => $upload['id'],
            'mapping' => ['sku' => 'sku', 'price' => 'price'],
        ])->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.updated', 1);

        $this->assertSame('12.50', $boot->refresh()->price);

        $export = $this->actingAs($editor, 'cms')->postJson($this->api('exchange/export'), [
            'selection' => ['ids' => [$boot->id]],
            'columns' => ['sku', 'price'],
            'format' => 'csv',
        ])->assertOk()->assertJsonPath('data.status', 'done')->json('data');

        $this->assertNotNull($export['file_url']);
        $this->assertStringContainsString("sku,price\nB-1,12.50", $this->get($export['file_url'])->assertOk()->streamedContent());
        $this->get($this->api('exchange/download/'.$export['id'].'/catalog-export-'.$export['id'].'.csv'))->assertForbidden();

        $viewer = $this->editor(['catalog.view']);
        $this->actingAs($viewer, 'cms')->postJson($this->api('exchange/import'), ['upload_id' => 'x', 'mapping' => []])->assertForbidden();
        $this->actingAs($viewer, 'cms')->getJson($this->api('exchange/runs/'.$export['id']))->assertOk();
    }

    #[Test]
    public function the_prune_takes_old_files_then_old_runs(): void
    {
        $boot = $this->product('Boot', null, ['sku' => 'B-1']);
        $run = $this->app->make(Exporter::class)->start(['ids' => [$boot->id]], ['sku'], 'csv');
        Storage::disk('local')->assertExists((string) $run->file);

        $this->travel(25)->hours();
        $this->artisan('webx:catalog:exchange-prune')->assertSuccessful();
        Storage::disk('local')->assertMissing((string) $run->file);
        $this->assertNull($run->refresh()->file);

        $this->travel(91)->days();
        $this->artisan('webx:catalog:exchange-prune')->assertSuccessful();
        $this->assertNull(ExchangeRun::query()->find($run->id));
    }

    private function roundTrip(string $format): void
    {
        $this->useLocales('en', 'de');
        Queue::fake();

        $electronics = $this->category('electronics');
        $phones = $this->category('phones', parent: $electronics);
        $sale = $this->category('sale');

        $phone = $this->product('Phone', $phones, [
            'sku' => '0012',
            'barcode' => '4600000000001',
            'external_id' => 'erp-1',
            'summary' => 'Small',
            'description' => '<p>A <strong>phone</strong>.</p>',
            'price' => '1234.50',
            'old_price' => '1500',
            'unit' => 'pcs',
            'priority' => 3,
        ]);
        $phone->setTranslation('name', 'de', 'Telefon')->save();
        $phone->categories()->attach($sale->id);
        ProductImage::query()->create(['product_id' => $phone->id, 'path' => 'catalog/0/1/abc.jpg', 'position' => 0]);

        $case = $this->product('Case; with a semicolon', null, ['sku' => 'C-1', 'price' => '9.99']);

        $form = $this->app->make(ProductForm::class);
        $before = [$form->values($phone->refresh()), $form->values($case->refresh())];

        $codes = [...array_keys($this->app->make(ExchangeColumns::class)->available()), 'name@de'];
        $export = $this->app->make(Exporter::class)->start(['ids' => [$phone->id, $case->id]], $codes, $format);

        $this->assertSame(ExchangeRun::DONE, $export->status);
        $this->assertSame(2, $export->rows_done);

        $path = Storage::disk('local')->path((string) $export->file);
        $header = iterator_to_array($this->app->make(ExchangeFormats::class)->find($format)?->read($path, ['encoding' => 'UTF-8', 'delimiter' => ',']) ?? [])[1];
        $this->assertSame($codes, $header);

        $run = $this->app->make(Importer::class)->start(['path' => $path, 'name' => 'back.'.$format], array_combine($header, $header), ['key' => 'sku']);

        $this->assertSame(ExchangeRun::DONE, $run->status);
        $this->assertSame([0, 2, 0], [$run->created, $run->updated, $run->failed], (string) json_encode(DB::table('catalog_exchange_errors')->get()));
        $this->assertSame(0, HistoryEntry::query()->where('parent_id', $run->history_id)->count(), 'Nothing changed, so nothing is journaled.');
        $this->assertEquals($before, [$form->values($phone->refresh()), $form->values($case->refresh())]);
        $this->assertSame('erp-1', $phone->external_id);
        $this->assertSame(1, $phone->images()->count());
        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string|null>|null  $mapping  null — what the headers suggest
     */
    private function import(string $content, array $options = [], ?array $mapping = null, bool $dryRun = false): ExchangeRun
    {
        $path = $this->file($content);
        $importer = $this->app->make(Importer::class);
        $mapping ??= $importer->inspect($path, 'file.csv')['mapping'];

        return $importer->start(['path' => $path, 'name' => 'file.csv'], $mapping, $options, $dryRun);
    }

    /** A finished chunked upload of this content, as the panel leaves it. */
    private function uploaded(CmsUser $admin, string $contents, string $name): string
    {
        $uploads = $this->app->make(Uploads::class);
        [$upload] = $uploads->start($admin, ExchangeFiles::UPLOAD_PURPOSE, $name, strlen($contents), 'text/csv', $name.'|'.strlen($contents));

        $body = fopen('php://memory', 'r+b');
        fwrite($body, $contents);
        rewind($body);
        $uploads->append($upload, 0, $body, strlen($contents));
        fclose($body);

        return $upload->id;
    }

    private function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'webx-test-').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    /** @return list<string> */
    private function allCatalog(): array
    {
        return ['catalog.view', 'catalog.manage', 'catalog.delete'];
    }

    private function withNotes(): void
    {
        Schema::create('test_notes', static function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->primary();
            $table->string('text')->nullable();
        });

        $this->app->make(ProductParts::class)->register(new NotePart);
        $this->app->make(ScreenRegistry::class)->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'tabs',
            'node' => [
                'id' => 'notes-tab',
                'type' => 'wx-tab',
                'can' => 'catalog.notes',
                'children' => [['id' => 'notes-text', 'type' => 'wx-input', 'name' => 'notes.text']],
            ],
        ]]);
    }

    /**
     * @param  Application  $app
     */
    protected function withAnIndex($app): void
    {
        $app['config']->set('webx-catalog.engine', 'indexing');
        $app->singleton(IndexingEngine::class);
        $app->afterResolving(CatalogEngines::class, static function (CatalogEngines $engines): void {
            $engines->register('indexing', IndexingEngine::class);
        });
    }
}
