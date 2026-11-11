<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The extra fields of the checkout an administrator defines (WEBX_UI_ORDER_FIELDS.md §2.1). Made
 * whether the checkout is on or not, like the drafts: switching it on needs no migration. The place
 * is a key of the registry in code, not an enum here — a new place needs no migration (§4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('type', 16);
            $table->string('place', 32);
            $table->json('label');
            $table->json('hint')->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            // Restrict: a checkbox with fields shown behind it is refused deletion (§7.3).
            $table->foreignId('parent_id')->nullable()->constrained('order_fields')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fields');
    }
};
