<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories a vacancy is in, in which order. `item_position` comes with the shared macro;
 * the index lists a group in the one order vacancies have, `position`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_category_vacancy', function (Blueprint $table): void {
            $table->categoryLinks('vacancy', 'vacancy_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_category_vacancy');
    }
};
