<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The keys of the social networks an administrator fills in (spec §3, §9.3): one row per
        // provider of the registry. Secrets are `encrypted:array` in `credentials`, never read back.
        Schema::create('users_providers', function (Blueprint $table): void {
            $table->string('key', 32)->primary();
            $table->boolean('enabled')->default(false);
            $table->json('options')->nullable();
            $table->text('credentials')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_providers');
    }
};
