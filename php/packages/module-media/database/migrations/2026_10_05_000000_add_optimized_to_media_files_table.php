<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            // Which optimize settings the picture has been through, so «Optimize» passes over it
            // until they change. Null for everything uploaded before there was a pipeline.
            $table->string('optimized', 16)->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropColumn('optimized');
        });
    }
};
