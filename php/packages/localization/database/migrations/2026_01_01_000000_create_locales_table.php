<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            // BCP-47, which is also what belongs in a `lang` attribute and an hreflang.
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('native_name');
            $table->string('direction', 3)->default('ltr');
            // Exactly one row is the default; the model enforces it, because a database
            // cannot express "one true row" without a partial index nobody has on MySQL.
            $table->boolean('is_default')->default(false);
            // A language can be switched off without losing the content written in it.
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
