<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where on the site the form stood when it was sent — `footer`, `article` (layout regions §11).
 *
 * One form of subscription in the footer and in every article is one form, and without this
 * the panel cannot say which of the two brought a submission in. Null is "the page did not
 * say", which is every submission written before today and every one typed in by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_submissions', function (Blueprint $table): void {
            // 32: the same limit the form component checks the attribute against.
            $table->string('placement', 32)->nullable()->after('source');

            // The list filters by it within one form, and asks which places one form has.
            $table->index(['form_id', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::table('inbox_submissions', function (Blueprint $table): void {
            $table->dropIndex(['form_id', 'placement']);
            $table->dropColumn('placement');
        });
    }
};
