<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            // The slugs a type was known by before a rename. The history of pages is not
            // rewritten by a rename, so restoring an old version brings an old slug back: this
            // is how it finds its way to the type it became.
            $table->json('former_slugs')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            $table->dropColumn('former_slugs');
        });
    }
};
