<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Users\Doctor\UsersTableCheck;

/**
 * What the migration finds where its table goes (spec §3, decision 13): nothing — it makes its
 * own; Laravel's empty table of the old skeleton — gone with its empty `password_reset_tokens`;
 * a table with rows — a refusal in words, and `webx:doctor` with the same line. Every test starts
 * from an empty database: nothing here migrates on its own.
 */
final class MigrationTest extends MigrationTestCase
{
    #[Test]
    public function on_a_clean_database_it_makes_its_table_and_takes_it_back(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(
            ['id', 'email', 'email_verified_at', 'password', 'first_name', 'last_name', 'phone', 'locale', 'extra', 'blocked_at', 'remember_token', 'last_sign_in_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('users'),
        );
        $this->assertSame(['ok'], array_map(static fn (Diagnosis $line): string => $line->state, $this->doctor()));

        $this->rollBack();
        $this->assertFalse(Schema::hasTable('users'));
    }

    #[Test]
    public function over_the_empty_table_of_laravel_it_drops_it_and_makes_its_own(): void
    {
        $this->laravelTables();

        // Before: not migrated yet, said as a warning — `migrate` mends it.
        $this->assertSame(['warn'], array_map(static fn (Diagnosis $line): string => $line->state, $this->doctor()));

        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue(Schema::hasColumn('users', 'first_name'));
        $this->assertFalse(Schema::hasColumn('users', 'name'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));

        // Laravel's table is not brought back.
        $this->rollBack();
        $this->assertFalse(Schema::hasTable('users'));
    }

    #[Test]
    public function a_table_with_rows_is_refused_in_words_and_doctor_says_the_same(): void
    {
        $this->laravelTables();
        DB::table('users')->insert([
            ['name' => 'One', 'email' => 'one@example.test', 'password' => 'x'],
            ['name' => 'Two', 'email' => 'two@example.test', 'password' => 'x'],
        ]);
        $line = 'Table `users` already holds 2 rows; move them out or drop the table, then migrate again.';

        $doctor = $this->doctor();
        $this->assertSame('fail', $doctor[0]->state);
        $this->assertSame($line, $doctor[0]->detail);

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->fail('The migration went over a table with rows.');
        } catch (RuntimeException $refusal) {
            $this->assertSame($line, $refusal->getMessage());
        }

        // Nothing of theirs was touched.
        $this->assertSame(2, DB::table('users')->count());
        $this->assertTrue(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    }

    #[Test]
    public function an_empty_table_that_is_not_laravels_is_left_alone_too(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('login');
        });

        $this->expectExceptionMessage('Table `users` is not the one Laravel creates and not this module\'s; drop it, then migrate again.');

        Artisan::call('migrate', ['--force' => true]);
    }

    private function rollBack(): void
    {
        $migration = require dirname(__DIR__).'/database/migrations/2026_01_01_000000_create_users_table.php';
        $migration->down();
    }

    /** What the skeleton of before made: Laravel's `users` and `password_reset_tokens`. */
    private function laravelTables(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', static function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /** @return list<Diagnosis> */
    private function doctor(): array
    {
        return $this->app->make(UsersTableCheck::class)->run();
    }
}
