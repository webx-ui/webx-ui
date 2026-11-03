<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery module's own tables (spec §5): the methods a site turned on, where the shop's
 * courier goes, the carriers with their bindings to the shared geography, the points of every
 * carrier and of the shop, and the journal the directory sync writes.
 *
 * The second day: the localities these point at are `webx-ui/geography`'s, built on the first,
 * and MariaDB refuses a foreign key to a table that is not there yet (docs/pitfalls).
 *
 * `icon_id` names a file of the media library without a constraint: the media module is not a
 * requirement of delivery, and a method without its icon falls back to the carrier's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_carriers', static function (Blueprint $table): void {
            $table->string('key', 32)->primary();
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->text('credentials')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // The shop itself is a carrier with a row from the first day (§5).
        DB::table('delivery_carriers')->insert([
            'key' => 'store',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('delivery_methods', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('type', 64)->index();
            $table->json('titles');
            $table->json('descriptions')->nullable();
            $table->unsignedBigInteger('icon_id')->nullable();
            $table->string('point_kind', 32)->nullable();
            $table->json('countries')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->boolean('honours_paid_by_store')->default(true);
            $table->json('settings')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_method_localities', static function (Blueprint $table): void {
            $table->foreignId('method_id')->constrained('delivery_methods')->cascadeOnDelete();
            $table->foreignId('locality_id')->constrained('geo_localities')->cascadeOnDelete();
            $table->primary(['method_id', 'locality_id']);
            $table->index('locality_id');
        });

        Schema::create('delivery_sync_runs', static function (Blueprint $table): void {
            $table->id();
            $table->string('carrier', 32);
            $table->string('directory', 16);
            $table->string('status', 12);
            $table->string('trigger', 12);
            $table->text('cursor')->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('seen')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->unsignedInteger('queued')->default(0);
            $table->string('error', 500)->nullable();
            $table->json('warnings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // «The last success of this directory» (§5).
            $table->index(['carrier', 'directory', 'status', 'finished_at']);
        });

        Schema::create('delivery_carrier_localities', static function (Blueprint $table): void {
            $table->id();
            $table->string('carrier', 32);
            $table->string('external_id', 64);
            // Null — not bound yet: the record waits in the queue (§4.3). A locality deleted
            // outside the editor sends its bindings back there rather than taking them along.
            $table->foreignId('locality_id')->nullable()->constrained('geo_localities')->nullOnDelete();
            $table->string('status', 12);
            $table->string('method', 12)->nullable();
            $table->string('reason', 24)->nullable();
            $table->json('record');
            $table->json('features')->nullable();
            $table->unsignedInteger('streets')->default(0);
            $table->foreignId('seen_run_id')->nullable()->constrained('delivery_sync_runs')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['carrier', 'external_id']);
            // «The carrier's locality for a shared one» — what the methods of a locality read.
            $table->index(['carrier', 'locality_id']);
            $table->index('locality_id');
            // The queue.
            $table->index(['status', 'carrier']);
        });

        Schema::create('delivery_points', static function (Blueprint $table): void {
            $table->id();
            $table->string('carrier', 32);
            $table->string('external_id', 64)->nullable();
            $table->string('kind', 32);
            $table->foreignId('locality_id')->nullable()->constrained('geo_localities')->nullOnDelete();
            $table->foreignId('carrier_locality_id')->nullable()->constrained('delivery_carrier_localities')->nullOnDelete();
            $table->string('number', 16)->nullable();
            $table->json('titles')->nullable();
            $table->json('address');
            $table->string('postcode', 16)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->json('schedule')->nullable();
            $table->json('limits')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('search', 500)->default('');
            $table->json('meta')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('seen_run_id')->nullable()->constrained('delivery_sync_runs')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            // A shop's points have no external id; a unique index lets any number of nulls through.
            $table->unique(['carrier', 'external_id']);
            // What methods a locality has, and the points of one method there (§6.3, §12.2).
            $table->index(['locality_id', 'active', 'carrier', 'kind']);
            $table->index(['carrier', 'seen_run_id']);
            $table->index('carrier_locality_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_points');
        Schema::dropIfExists('delivery_carrier_localities');
        Schema::dropIfExists('delivery_sync_runs');
        Schema::dropIfExists('delivery_method_localities');
        Schema::dropIfExists('delivery_methods');
        Schema::dropIfExists('delivery_carriers');
    }
};
