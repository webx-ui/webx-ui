<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories a service is in, in which order, and where it stands inside each (§2.5).
 *
 * After both tables it links, which the filenames guarantee inside this package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_category', function (Blueprint $table): void {
            $table->categoryLinks('service', 'service_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_category');
    }
};
