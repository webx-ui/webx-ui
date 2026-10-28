<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer groups (spec §3, §13.1): a code and a name in one language (decision 24). The
        // panel lists them by name, the rules of a shop name them by code.
        Schema::create('users_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255)->index();
            $table->timestamps();
        });

        // Many to many: «wholesale» and «club» are different axes. The list of the panel filters
        // by group (`group_id` first in the key), a visitor's groups are read by `user_id`.
        Schema::create('users_group_user', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('users_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['group_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_group_user');
        Schema::dropIfExists('users_groups');
    }
};
