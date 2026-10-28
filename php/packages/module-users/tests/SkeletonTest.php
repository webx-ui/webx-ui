<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * The skeleton `webx-ui/site` without Laravel's `users` (decision 13): no table, no
 * `password_reset_tokens`, no `App\Models\User` — and its migrations with the module's make the
 * module's table on a fresh site. Only in the monorepo, where the skeleton lies beside the package.
 */
final class SkeletonTest extends MigrationTestCase
{
    private static function site(): string
    {
        return dirname(__DIR__, 3).'/site';
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(self::site().'/composer.json')) {
            $this->markTestSkipped('Not in the monorepo: the skeleton is not beside the package.');
        }
    }

    #[Test]
    public function the_skeleton_has_no_users_of_laravel(): void
    {
        $this->assertFileDoesNotExist(self::site().'/app/Models/User.php');
        $this->assertFileDoesNotExist(self::site().'/database/factories/UserFactory.php');

        foreach (glob(self::site().'/database/migrations/*.php') ?: [] as $migration) {
            $code = (string) file_get_contents($migration);
            $this->assertDoesNotMatchRegularExpression("/Schema::create\\('(users|password_reset_tokens)'/", $code, basename($migration));
        }

        $this->assertStringNotContainsString('App\\Models\\User', (string) file_get_contents(self::site().'/config/auth.php'));
        $this->assertStringNotContainsString('App\\Models\\User', (string) file_get_contents(self::site().'/database/seeders/DatabaseSeeder.php'));
    }

    #[Test]
    public function a_fresh_site_gets_the_table_of_the_module(): void
    {
        $this->loadMigrationsFrom(self::site().'/database/migrations');

        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumn('users', 'first_name'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }
}
