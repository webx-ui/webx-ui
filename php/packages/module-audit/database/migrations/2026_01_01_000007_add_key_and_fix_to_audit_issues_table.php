<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            // What tells two findings of one check at one address apart — the field of a record,
            // the asset of a page. Part of the fingerprint; kept so that a fix can find its way
            // back to the record without parsing the details.
            $table->string('key', 512)->default('')->after('fingerprint');
            // The fix pressed on it, and when. The finding stays until the next run says it is
            // gone: a fix changes the site, the audit only believes a run.
            $table->string('fixed_with', 64)->nullable()->after('ignored_by');
            $table->timestamp('fixed_at')->nullable()->after('fixed_with');
        });
    }

    public function down(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->dropColumn(['key', 'fixed_with', 'fixed_at']);
        });
    }
};
