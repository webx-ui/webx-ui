<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');

            // Permissions are strings the modules declare in the manifest, so the manifest is
            // the list of what exists and a role only records which of them it grants. A
            // permissions table would be a second, quietly diverging copy of that list.
            $table->json('permissions');

            $table->timestamps();
        });

        Schema::create('cms_role_user', function (Blueprint $table): void {
            $table->foreignId('cms_user_id')->constrained('cms_users')->cascadeOnDelete();
            $table->foreignId('cms_role_id')->constrained('cms_roles')->cascadeOnDelete();
            $table->primary(['cms_user_id', 'cms_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_role_user');
        Schema::dropIfExists('cms_roles');
    }
};
