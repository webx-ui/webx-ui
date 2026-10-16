<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The codes of outside services (§3.1 of the snippets spec). The place is a key of the config,
 * not a foreign key: places live in `config/webx-snippets.php`, and a place taken out of it
 * leaves its codes here, printed nowhere, until somebody moves them.
 *
 * `mediumText` for the code: MySQL's `TEXT` is 65 535 bytes, and the rule allows 65 536 — a code
 * in letters of two bytes would hit the column before it hit the rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snippets_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('place', 64)->index();
            $table->mediumText('code');
            $table->string('consent', 16)->default('necessary');
            $table->json('only_paths')->nullable();
            $table->json('except_paths')->nullable();
            $table->json('locales')->nullable();
            $table->boolean('on_stage')->default(false);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['place', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snippets_codes');
    }
};
