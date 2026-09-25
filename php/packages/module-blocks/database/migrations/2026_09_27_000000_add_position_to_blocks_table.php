<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            // Where a type stands in the list and in the picker, dragged by an editor. Not
            // `sort`: that one is the order of the styles on the page, and rearranging the
            // cards for convenience must not change which rule wins on the site.
            $table->unsignedInteger('position')->default(0)->after('sort');
        });

        // The order every site saw until today, so that nothing moves the moment the column
        // appears: `sort`, then the identifier.
        $position = 0;

        foreach (DB::table('blocks')->orderBy('sort')->orderBy('slug')->pluck('id') as $id) {
            DB::table('blocks')->where('id', $id)->update(['position' => ++$position]);
        }
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
