<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which groups a tariff is in, and where it stands inside each.
 *
 * After both tables it links, which the filenames guarantee inside this package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_category_tariff', function (Blueprint $table): void {
            $table->categoryLinks('tariff', 'tariff_categories', 'tariffs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_category_tariff');
    }
};
