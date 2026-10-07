<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Files fetched from an address before the fix kept the server's header whole —
     * `image/png; qs=0.7` — and a type with parameters is not the type anything compares with.
     * Row by row in PHP rather than one UPDATE: the string functions differ in every database.
     */
    public function up(): void
    {
        DB::table('media_files')
            ->where('mime', 'like', '%;%')
            ->orderBy('id')
            ->select(['id', 'mime'])
            ->chunkById(500, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('media_files')
                        ->where('id', $row->id)
                        ->update(['mime' => strtolower(trim(explode(';', (string) $row->mime)[0]))]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to put back: the parameters never meant anything to the library.
    }
};
