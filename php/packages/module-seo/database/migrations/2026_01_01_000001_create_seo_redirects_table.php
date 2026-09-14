<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->id();

            // The same three kinds the rules use, matched by the same class. Two vocabularies
            // for one idea is a support question waiting to happen.
            $table->string('match_type', 8);
            $table->string('pattern', 2048);
            $table->string('target', 2048);
            $table->smallInteger('status')->default(301);
            $table->boolean('is_active')->default(true);

            // Counted so a redirect nobody has followed in a year can be found and removed.
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'match_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
    }
};
