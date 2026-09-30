<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Properties of products (§2 of the properties spec): the groups the card shows them under, the
 * properties themselves, the reference book of values (a tree scoped by property — a flat book is a
 * tree without depth), the intervals a number is filtered by, the sets of the categories and the
 * values of the products, one row per value.
 *
 * The fourth day: after the dictionaries, whose `media_files` and `catalog_products` it points at
 * (docs/pitfalls/laravel-and-php.md, «Миграции всех пакетов сортируются вместе»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_property_groups', function (Blueprint $table): void {
            $table->id();
            $table->category();
        });

        Schema::create('catalog_properties', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('code')->nullable();
            $table->string('type', 16);
            $table->foreignId('group_id')->nullable()->constrained('catalog_property_groups')->nullOnDelete();
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_tree')->default(false);
            $table->boolean('leaves_only')->default(false);
            $table->boolean('is_filterable')->default(false)->index();
            $table->string('filter_mode', 16)->nullable();
            $table->boolean('is_indexable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('in_card')->default(false);
            $table->boolean('on_page')->default(true);
            $table->boolean('in_list')->default(false);
            $table->string('value_order', 16)->default('alpha');
            $table->boolean('has_color')->default(false);
            $table->boolean('has_image')->default(false);
            $table->json('unit_prefix')->nullable();
            $table->json('unit_suffix')->nullable();
            $table->unsignedTinyInteger('precision')->default(0);
            $table->json('toggle_slug')->nullable();
            $table->json('seo_pattern')->nullable();
            $table->integer('position')->default(0)->index();
            $table->json('extra')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('catalog_property_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('catalog_properties')->cascadeOnDelete();
            $table->nestedSet();
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->string('color', 7)->nullable();
            $table->foreignId('image_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'lft']);
        });

        Schema::create('catalog_property_intervals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('catalog_properties')->cascadeOnDelete();
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->decimal('min', 20, 6)->nullable();
            $table->decimal('max', 20, 6)->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('catalog_category_property', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('catalog_categories')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('catalog_properties')->cascadeOnDelete();
            $table->integer('position')->default(0);

            $table->primary(['category_id', 'property_id']);
            $table->index('property_id');
        });

        Schema::create('catalog_product_property_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('catalog_properties')->cascadeOnDelete();
            $table->foreignId('value_id')->nullable()->constrained('catalog_property_values')->cascadeOnDelete();
            $table->decimal('number', 20, 6)->nullable();
            $table->boolean('flag')->nullable();
            $table->json('text')->nullable();

            $table->index(['product_id', 'property_id']);
            $table->index(['property_id', 'value_id']);
            $table->index(['property_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_property_values');
        Schema::dropIfExists('catalog_category_property');
        Schema::dropIfExists('catalog_property_intervals');
        Schema::dropIfExists('catalog_property_values');
        Schema::dropIfExists('catalog_properties');
        Schema::dropIfExists('catalog_property_groups');
    }
};
