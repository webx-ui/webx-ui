<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which answer a line of the log is about.
 *
 * A corrected answer is three things — the field, what it was, what it became — and `from`
 * and `to` hold only two of them. The machine name and not the field's id, for the same reason
 * the log keeps a status by key: it is read long after the field may have gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_submission_events', function (Blueprint $table): void {
            $table->string('field', 64)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_submission_events', function (Blueprint $table): void {
            $table->dropColumn('field');
        });
    }
};
