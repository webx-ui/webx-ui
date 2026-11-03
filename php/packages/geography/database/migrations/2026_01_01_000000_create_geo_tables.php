<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_countries', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->json('names')->nullable();
            $table->boolean('active')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('geo_regions', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2);
            $table->string('code', 8)->nullable();
            $table->json('names');
            $this->key($table->string('name_key', 191));
            $table->json('manual_fields')->nullable();
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('geo_countries');
            $table->unique(['country_code', 'name_key']);
            $table->index(['country_code', 'code']);
        });

        Schema::create('geo_region_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->constrained('geo_regions')->cascadeOnDelete();
            $this->key($table->string('name_key', 191));
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'name_key']);
            $table->index('name_key');
        });

        Schema::create('geo_localities', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2);
            // A region with localities is refused deletion by the editor; the key says the same
            // to whatever deletes behind its back.
            $table->foreignId('region_id')->nullable()->constrained('geo_regions')->restrictOnDelete();
            $table->json('district')->nullable();
            $table->string('kind', 16);
            $table->json('names');
            $this->key($table->string('name_key', 191));
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->unsignedInteger('weight')->default(0);
            $table->boolean('active')->default(true);
            $table->string('created_by', 32);
            // The fields an administrator changed by hand: a carrier's sync leaves them alone.
            $table->json('manual_fields')->nullable();
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('geo_countries');
            $table->index(['country_code', 'region_id', 'name_key']);
            $table->index(['country_code', 'weight']);
        });

        Schema::create('geo_locality_names', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('locality_id')->constrained('geo_localities')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('locale', 8)->nullable();
            $table->string('name', 191);
            $this->key($table->string('name_key', 191));
            $table->string('kind', 8);
            $table->string('source', 32)->nullable();

            // Starts with the country so that a prefix `LIKE 'q%'` is a range on the index.
            $table->index(['country_code', 'name_key', 'locality_id']);
            $table->unique(['locality_id', 'name_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_locality_names');
        Schema::dropIfExists('geo_localities');
        Schema::dropIfExists('geo_region_aliases');
        Schema::dropIfExists('geo_regions');
        Schema::dropIfExists('geo_countries');
    }

    /**
     * A normalised key compares byte for byte. Under the server's default `_ci` collation two
     * different keys — a letter with and without its diacritic — would be one, and the unique
     * index would refuse the second region; the key carries no language rules on purpose (§4.2).
     */
    private function key(ColumnDefinition $column): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $column->collation('utf8mb4_bin');
        }
    }
};
