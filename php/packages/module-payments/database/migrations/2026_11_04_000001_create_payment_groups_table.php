<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\MethodTypes;

/**
 * The manager's edits of the groups and a method's move to another group (groups spec §2.1, §2.4).
 * The methods are not touched: `group_key = null` is the group of the type, so each is in place at
 * once. What a configured site would lose is its first item, so the groups in use get positions by
 * their first enabled method. No cache to bump: nothing has read these rows yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_groups', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->string('pictogram', 32)->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_methods', static function (Blueprint $table): void {
            $table->string('group_key', 64)->nullable()->after('position');
        });

        $now = now();

        foreach ($this->firstPositions() as $key => $position) {
            DB::table('payment_groups')->insert(['key' => $key, 'position' => $position, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_methods', static function (Blueprint $table): void {
            $table->dropColumn('group_key');
        });

        Schema::dropIfExists('payment_groups');
    }

    /**
     * The lowest position of the enabled methods of each group; a type nobody registers says nothing.
     *
     * @return array<string, int>
     */
    private function firstPositions(): array
    {
        $types = app(MethodTypes::class);
        $groups = app(MethodGroups::class);
        $positions = [];

        foreach (DB::table('payment_methods')->where('enabled', true)->orderBy('position')->orderBy('id')->get(['type', 'position']) as $method) {
            $group = $types->has($method->type) ? $groups->ofType($types->get($method->type)) : null;

            if ($group !== null) {
                $positions[$group] ??= (int) $method->position;
            }
        }

        return $positions;
    }
};
