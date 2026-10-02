<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What was hidden on purpose (decision 9): a check, an address or a mask of them, and why.
        // Hidden is not deleted — the finding is still found and stored, only not counted.
        Schema::create('audit_ignores', function (Blueprint $table): void {
            $table->id();
            $table->string('check', 64);
            // An address, a mask (`/search/**`) or empty for every finding of the check.
            $table->string('pattern', 2048)->default('');
            $table->text('reason');
            // The administrator's id, `mcp` or `console`.
            $table->string('created_by', 64)->nullable();
            $table->timestamps();

            $table->index('check');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_ignores');
    }
};
