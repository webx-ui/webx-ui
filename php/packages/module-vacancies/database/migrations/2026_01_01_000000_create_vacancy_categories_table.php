<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories of vacancies — "Development", "Sales": the groups and the filter of the index, with
 * no address of their own (decision 2). The shared columns and nothing else; the slug is kept and
 * held unique already, because it is the key of the filter and, one day, an address (§7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_categories', function (Blueprint $table): void {
            $table->id();

            $table->category();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_categories');
    }
};
