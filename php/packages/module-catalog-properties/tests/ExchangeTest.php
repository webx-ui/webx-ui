<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Doctor\ExchangeCheck;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\ExchangeFormats;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Exchange\Exporter;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\Importer;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ProductForm;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Localization\Locales;

/**
 * The satellites' columns of the exchange (§7.2, §13 of the exchange spec): labels by code, the
 * stock status that is never created, a brand by slug or name, a column per property in its type's
 * shape — and a product with all of them exported and sent back changing nothing.
 */
final class ExchangeTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), LabelsServiceProvider::class, StockServiceProvider::class, BrandsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    #[Test]
    public function a_csv_with_every_part_exported_and_imported_back_changes_nothing(): void
    {
        $this->roundTrip('csv');
    }

    #[Test]
    public function an_xlsx_with_every_part_exported_and_imported_back_changes_nothing(): void
    {
        $this->roundTrip('xlsx');
    }

    #[Test]
    public function labels_are_read_by_code_and_created_only_when_asked(): void
    {
        $sale = Label::query()->create(['title' => 'Sale', 'code' => 'sale'])->refresh();
        $shoe = $this->sold('Shoe', 'S-1');

        $run = $this->import("sku,labels\nS-1,SALE ; new\n");
        $this->assertSame(1, $run->failed);
        $this->assertStringContainsString('new', (string) DB::table('catalog_exchange_errors')->where('run_id', $run->id)->value('message'));
        $this->assertSame([], $this->labelsOf($shoe));

        $run = $this->import("sku,labels\nS-1,sale;new\n", ['create_missing' => true]);
        $this->assertSame([0, 1, 0], [$run->created, $run->updated, $run->failed]);
        $new = Label::query()->where('code', 'new')->sole();
        $this->assertSame([$sale->id, $new->id], $this->labelsOf($shoe));
    }

    #[Test]
    public function a_stock_status_is_found_by_code_and_never_created(): void
    {
        $shoe = $this->sold('Shoe', 'S-1');
        $statuses = StockStatus::query()->count();

        $run = $this->import("sku,stock\nS-1,Out-Of-Stock\n");
        $this->assertSame(0, $run->failed);
        $this->assertSame('out-of-stock', DB::table('catalog_stock_statuses')->where('id', DB::table(StockStatus::LINKS)->where('product_id', $shoe->id)->value('status_id'))->value('code'));

        $run = $this->import("sku,stock\nS-1,sold-out\n", ['create_missing' => true]);
        $this->assertSame(1, $run->failed);
        $error = DB::table('catalog_exchange_errors')->where('run_id', $run->id)->sole();
        $this->assertSame('stock', $error->column);
        $this->assertStringContainsString('in-stock', (string) $error->message);
        $this->assertSame($statuses, StockStatus::query()->count());
    }

    #[Test]
    public function a_brand_is_named_by_slug_by_name_or_by_id_and_created_when_asked(): void
    {
        $apple = Brand::query()->create(['title' => 'Apple Inc', 'slug' => 'apple', 'is_visible' => true])->refresh();
        $shoe = $this->sold('Shoe', 'S-1');

        foreach (['APPLE', 'apple inc', '#'.$apple->id] as $cell) {
            DB::table(Brand::LINKS)->delete();
            $run = $this->import("sku,brand\nS-1,{$cell}\n");
            $this->assertSame(0, $run->failed, $cell);
            $this->assertSame($apple->id, (int) DB::table(Brand::LINKS)->where('product_id', $shoe->id)->value('brand_id'), $cell);
        }

        $run = $this->import("sku,brand\nS-1,Zebra Works\n");
        $this->assertSame(1, $run->failed);

        $run = $this->import("sku,brand\nS-1,Zebra Works\n", ['create_missing' => true]);
        $this->assertSame(0, $run->failed);
        $zebra = Brand::query()->where('id', DB::table(Brand::LINKS)->where('product_id', $shoe->id)->value('brand_id'))->sole();
        $this->assertSame('Zebra Works', $zebra->getTranslation('title', 'en'));
        $this->assertSame('zebra-works', $zebra->getTranslation('slug', 'en'));
        $this->assertTrue((bool) $zebra->is_visible);

        // Two brands of one name: the row is refused with both, never guessed.
        $twin = Brand::query()->create(['title' => 'Zebra Works', 'slug' => 'zebra-two', 'is_visible' => true])->refresh();
        $run = $this->import("sku,brand\nS-1,zebra works\n");
        $this->assertStringContainsString('#'.$twin->id, (string) DB::table('catalog_exchange_errors')->where('run_id', $run->id)->value('message'));
    }

    #[Test]
    public function each_property_is_a_column_in_its_types_shape(): void
    {
        $this->useLocales('en', 'de');
        $color = $this->property('Color', Property::SELECT, ['code' => 'color', 'is_multiple' => true]);
        $black = $this->value($color, 'Black');
        $white = $this->value($color, 'White');
        $weight = $this->property('Net weight', Property::NUMBER, ['code' => 'net-weight', 'unit_suffix' => ' kg', 'precision' => 2]);
        $wifi = $this->property('Wi-Fi', Property::BOOL, ['code' => 'wifi']);
        $note = $this->property('Note', Property::TEXT, ['code' => 'note']);
        $phones = $this->category('phones');
        $this->set($phones, [$color, $weight, $wifi, $note]);
        $phone = $this->product('Phone', $phones);
        $phone->update(['sku' => 'P-1']);

        $codes = array_keys($this->app->make(ExchangeColumns::class)->available());
        $this->assertContains('net_weight', $codes, 'A hyphen of the code is an underscore of the header.');

        $run = $this->import("sku,color,net_weight,wifi,note,note@de\nP-1,black; WHITE,\"1 234,5\",yes,Small,Klein\n");

        $this->assertSame(0, $run->failed, (string) json_encode(DB::table('catalog_exchange_errors')->get()));
        $values = $this->app->make(ProductForm::class)->values($phone->refresh())['properties.values'];
        $this->assertSame([$black->id, $white->id], $values[$color->id]);
        $this->assertSame(1234.5, $values[$weight->id]);
        $this->assertTrue($values[$wifi->id]);
        $this->assertSame(['en' => 'Small', 'de' => 'Klein'], $values[$note->id]);

        // A slug names a value as well as its name does; `no` takes the yes away.
        $run = $this->import("sku,color,wifi\nP-1,".$white->getTranslation('slug', 'en').",no\n");
        $this->assertSame(0, $run->failed);
        $values = $this->app->make(ProductForm::class)->values($phone->refresh())['properties.values'];
        $this->assertSame([$white->id], $values[$color->id]);
        $this->assertArrayNotHasKey($wifi->id, $values);

        $run = $this->import("sku,net_weight\nP-1,heavy\n");
        $this->assertSame('net_weight', DB::table('catalog_exchange_errors')->where('run_id', $run->id)->value('column'));
    }

    #[Test]
    public function a_value_down_a_tree_is_created_by_its_path_when_asked(): void
    {
        $fits = $this->property('Fits', Property::SELECT, ['code' => 'fits', 'is_tree' => true]);
        $bmw = $this->value($fits, 'BMW');
        $parts = $this->category('parts');
        $this->set($parts, [$fits]);
        $part = $this->product('Brake pad', $parts);
        $part->update(['sku' => 'B-1']);

        $run = $this->import("sku,fits\nB-1,bmw/3 Series/E90\n");
        $this->assertSame(1, $run->failed);
        $this->assertSame(1, PropertyValue::query()->count());

        $run = $this->import("sku,fits\nB-1,bmw/3 Series/E90\n", ['create_missing' => true]);
        $this->assertSame(0, $run->failed, (string) json_encode(DB::table('catalog_exchange_errors')->get()));
        $series = PropertyValue::query()->where('parent_id', $bmw->id)->sole();
        $e90 = PropertyValue::query()->where('parent_id', $series->id)->sole();
        $this->assertSame('3 Series', $series->getTranslation('title', 'en'));
        $this->assertSame($e90->id, $this->app->make(ProductForm::class)->values($part->refresh())['properties.values'][$fits->id]);

        // The same path again finds what the first run made, and two rows of one file make it once.
        $other = $this->product('Disc', $parts);
        $other->update(['sku' => 'B-2']);
        $run = $this->import("sku,fits\nB-1,BMW/3 series/E91\nB-2,BMW/3 Series/E91\n", ['create_missing' => true]);
        $this->assertSame(0, $run->failed);
        $this->assertSame(4, PropertyValue::query()->count());
    }

    #[Test]
    public function a_value_outside_the_set_of_the_category_is_an_error_of_its_column(): void
    {
        $size = $this->property('Size', Property::NUMBER, ['code' => 'size']);
        $socks = $this->product('Socks', $this->category('socks'));
        $socks->update(['sku' => 'S-1']);

        $run = $this->import("sku,size\nS-1,42\n");

        $error = DB::table('catalog_exchange_errors')->where('run_id', $run->id)->sole();
        $this->assertSame('size', $error->column);
        $this->assertSame('42', $error->value);
    }

    #[Test]
    public function a_check_without_writing_creates_no_value_of_any_book(): void
    {
        $color = $this->property('Color', Property::SELECT, ['code' => 'color']);
        $shirts = $this->category('shirts');
        $this->set($shirts, [$color]);
        $shirt = $this->product('Shirt', $shirts);
        $shirt->update(['sku' => 'T-1']);

        $run = $this->import("sku,color,labels,brand\nT-1,Red,hot,Acme\n", ['create_missing' => true], dryRun: true);

        $this->assertSame([0, 1, 0], [$run->created, $run->updated, $run->failed], (string) json_encode(DB::table('catalog_exchange_errors')->get()));
        $this->assertSame(0, PropertyValue::query()->count());
        $this->assertSame(0, Label::withTrashed()->count());
        $this->assertSame(0, Brand::withTrashed()->count());
    }

    #[Test]
    public function creating_in_a_book_needs_the_right_to_write_it(): void
    {
        $color = $this->property('Color', Property::SELECT, ['code' => 'color']);
        $context = new ImportContext(createMissing: true, dryRun: false, defaultLocale: 'en', can: static fn (string $permission): bool => $permission !== 'catalog.manage');
        $columns = $this->app->make(ExchangeColumns::class);

        foreach (['color' => 'Red', 'labels' => 'hot', 'brand' => 'Acme'] as $code => $cell) {
            try {
                $columns->find($code)?->parse($cell, $context);
                $this->fail("The column [{$code}] created without the right to.");
            } catch (RowError $error) {
                $this->assertStringContainsString('catalog.manage', $error->getMessage(), $code);
            }
        }

        $this->assertSame(0, $color->values()->count());
    }

    #[Test]
    public function a_property_coded_as_a_column_of_the_core_is_renamed_and_doctor_says_so(): void
    {
        $this->property('Summary line', Property::TEXT, ['code' => 'summary']);
        $columns = $this->app->make(ExchangeColumns::class);

        $this->assertArrayHasKey('p_summary', $columns->all());
        $this->assertSame(['summary' => 'p_summary'], $columns->renamed());

        $said = $this->app->make(ExchangeCheck::class)->run();
        $this->assertCount(1, $said);
        $this->assertStringContainsString('p_summary', $said[0]->detail);
    }

    private function roundTrip(string $format): void
    {
        $this->useLocales('en', 'de');
        Queue::fake();

        $color = $this->property('Color', Property::SELECT, ['code' => 'color', 'is_multiple' => true]);
        $black = $this->value($color, 'Black');
        $semi = $this->value($color, 'Grey; light');
        $fits = $this->property('Fits', Property::SELECT, ['code' => 'fits', 'is_tree' => true]);
        $e90 = $this->value($fits, 'E90', $this->value($fits, '3 Series', $this->value($fits, 'BMW')));
        $weight = $this->property('Weight', Property::NUMBER, ['code' => 'weight', 'precision' => 2]);
        $wifi = $this->property('Wi-Fi', Property::BOOL, ['code' => 'wifi']);
        $usb = $this->property('USB', Property::BOOL, ['code' => 'usb']);
        $note = $this->property('Note', Property::TEXT, ['code' => 'note']);
        $loose = $this->property('Loose', Property::NUMBER, ['code' => 'loose']);

        $phones = $this->category('phones');
        $this->set($phones, [$color, $fits, $weight, $wifi, $usb, $note]);

        $phone = $this->product('Phone', $phones, [
            $color->id => [$black, $semi],
            $fits->id => $e90,
            $weight->id => 1.35,
            $wifi->id => true,
            $note->id => ['en' => 'Small', 'de' => 'Klein'],
            // Held, but out of the set (decision 6): kept, and never written into a file.
            $loose->id => 7.0,
        ]);
        $phone->update(['sku' => 'P-1']);

        $sale = Label::query()->create(['title' => 'Sale', 'code' => 'sale'])->refresh();
        $hot = Label::query()->create(['title' => 'Hot', 'code' => 'hot'])->refresh();
        DB::table(Label::LINKS)->insert([['label_id' => $sale->id, 'product_id' => $phone->id], ['label_id' => $hot->id, 'product_id' => $phone->id]]);
        DB::table(StockStatus::LINKS)->insert(['product_id' => $phone->id, 'status_id' => StockStatus::query()->where('code', 'on-order')->value('id')]);
        $acme = Brand::query()->create(['title' => 'Acme', 'slug' => 'acme', 'is_visible' => true])->refresh();
        DB::table(Brand::LINKS)->insert(['product_id' => $phone->id, 'brand_id' => $acme->id]);

        // Nothing chosen anywhere: the default status, no brand, no labels, empty properties.
        $bare = $this->product('Bare', $phones);
        $bare->update(['sku' => 'B-1']);

        $form = $this->app->make(ProductForm::class);
        $before = [$form->values($phone->refresh()), $form->values($bare->refresh())];
        $books = [Label::withTrashed()->count(), Brand::withTrashed()->count(), PropertyValue::query()->count(), DB::table(StockStatus::LINKS)->count()];

        $codes = [...array_keys($this->app->make(ExchangeColumns::class)->available()), 'name@de', 'note@de'];
        $export = $this->app->make(Exporter::class)->start(['ids' => [$phone->id, $bare->id]], $codes, $format);
        $this->assertSame(ExchangeRun::DONE, $export->status);

        $path = Storage::disk('local')->path((string) $export->file);
        $rows = iterator_to_array($this->app->make(ExchangeFormats::class)->find($format)?->read($path, ['encoding' => 'UTF-8', 'delimiter' => ',']) ?? []);
        $header = $rows[1];
        $cells = array_combine($header, $rows[2]);
        $this->assertSame('sale;hot', $cells['labels']);
        $this->assertSame('on-order', $cells['stock']);
        $this->assertSame('acme', $cells['brand']);
        $this->assertSame('Black;#'.$semi->id, $cells['color'], 'A name with the separator in it is written by id.');
        $this->assertSame('BMW/3 Series/E90', $cells['fits']);
        $this->assertSame('1.35', $cells['weight']);
        $this->assertSame(['1', '0'], [$cells['wifi'], $cells['usb']]);
        $this->assertSame(['Small', 'Klein'], [$cells['note'], $cells['note@de']]);
        $this->assertSame('', $cells['loose']);
        $this->assertSame('', array_combine($header, $rows[3])['stock'], 'The default nobody chose is not pinned by a file.');

        $run = $this->app->make(Importer::class)->start(['path' => $path, 'name' => 'back.'.$format], array_combine($header, $header), ['key' => 'sku', 'create_missing' => true, 'empty_clears' => true]);

        $this->assertSame([0, 2, 0], [$run->created, $run->updated, $run->failed], (string) json_encode(DB::table('catalog_exchange_errors')->get()));
        $this->assertSame(0, HistoryEntry::query()->where('parent_id', $run->history_id)->count(), 'Nothing changed, so nothing is journaled.');
        $this->assertEquals($before, [$form->values($phone->refresh()), $form->values($bare->refresh())]);
        $this->assertSame($books, [Label::withTrashed()->count(), Brand::withTrashed()->count(), PropertyValue::query()->count(), DB::table(StockStatus::LINKS)->count()]);
        $this->assertSame(1, DB::table('catalog_product_property_values')->where('property_id', $loose->id)->count(), 'What is out of the set stays.');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function import(string $content, array $options = [], bool $dryRun = false): ExchangeRun
    {
        $path = tempnam(sys_get_temp_dir(), 'webx-test-').'.csv';
        file_put_contents($path, $content);
        $importer = $this->app->make(Importer::class);
        $mapping = $importer->inspect($path, 'file.csv')['mapping'];

        return $importer->start(['path' => $path, 'name' => 'file.csv'], $mapping, $options, $dryRun);
    }

    private function sold(string $name, string $sku): Product
    {
        $product = $this->product($name, $this->category(strtolower($name).'s'));
        $product->update(['sku' => $sku]);

        return $product->refresh();
    }

    /** @return list<int> */
    private function labelsOf(Product $product): array
    {
        return DB::table(Label::LINKS)->where('product_id', $product->id)->orderBy('label_id')->pluck('label_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    private function useLocales(string ...$codes): void
    {
        $this->app['config']->set('webx-localization.locales', array_map(
            static fn (string $code, int $index): array => ['code' => $code, 'default' => $index === 0],
            $codes,
            array_keys($codes),
        ));

        $this->app->make(Locales::class)->forget();
    }
}
