<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Localization\Locales;

/**
 * Stock statuses and which one each product is in (§2.2 of the dictionaries spec).
 *
 * The shared columns of every module's categories (`$table->category()`: the translated name as
 * `title`, the order, `is_visible` — here "in the filter" — the fields of the project, the bin),
 * plus the status's own: a code, a tone, whether it can be bought and whether it is the default.
 *
 * A product without a row is in the default status, so the satellite goes onto a live catalogue
 * without writing forty thousand rows. The three statuses every shop starts with are made here,
 * in English; a site renames them in the panel.
 *
 * The third day: the link table points at the catalogue's products, which are made on the second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_stock_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('color', 16)->default('neutral');
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_default')->default(false);

            $table->category();
        });

        Schema::create('catalog_product_stock', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('catalog_products')->cascadeOnDelete();
            $table->foreignId('status_id')->index()->constrained('catalog_stock_statuses');
        });

        $now = Carbon::now();
        $statuses = [
            ['code' => 'in-stock', 'title' => 'In stock', 'color' => 'success', 'is_purchasable' => true, 'is_default' => true],
            ['code' => 'out-of-stock', 'title' => 'Out of stock', 'color' => 'danger', 'is_purchasable' => false, 'is_default' => false],
            ['code' => 'on-order', 'title' => 'On order', 'color' => 'warning', 'is_purchasable' => true, 'is_default' => false],
        ];

        foreach ($statuses as $position => $status) {
            DB::table('catalog_stock_statuses')->insert([
                ...$status,
                // Under the fallback language, which every language of the site reads until somebody
                // translates it.
                'title' => json_encode([app(Locales::class)->fallback() => $status['title']]),
                'position' => $position,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_stock');
        Schema::dropIfExists('catalog_stock_statuses');
    }
};
