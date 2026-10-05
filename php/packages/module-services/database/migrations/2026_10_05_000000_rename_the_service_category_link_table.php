<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The link between services and their categories, named the way every other section names its
 * own: `<thing>_category_<thing>`, like `recipe_category_recipe` and `event_category_event`.
 *
 * A rename, because sites have services filed under categories. Skipped when it has already
 * moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_category') && ! Schema::hasTable('service_category_service')) {
            Schema::rename('service_category', 'service_category_service');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_category_service') && ! Schema::hasTable('service_category')) {
            Schema::rename('service_category_service', 'service_category');
        }
    }
};
