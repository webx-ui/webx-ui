<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            // The visitor whose cart this is; null — a guest's. No foreign key: the visitors
            // module may not be installed, and its `users` table is not this package's.
            $table->unsignedBigInteger('user_id')->nullable()->after('token');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
