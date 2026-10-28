<?php

declare(strict_types=1);

namespace WebxUi\Users\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Users\Fields\UserFields;

/**
 * A fast search by one field of the site (spec §10.3): writes a migration of the site that adds a
 * generated column `extra_<name>` over the JSON of `extra` and an index on it — unique for a
 * `unique` field. The panel's filter and the check of `unique` use the column once it is there.
 *
 * A migration of the site rather than of the module: which fields a site has is the site's, and
 * the module's migrations are the same for every site.
 */
final class IndexFieldCommand extends Command
{
    protected $signature = 'webx:users:index-field {name : the field of the site, as declared}';

    protected $description = 'Write a migration that indexes one field of the visitors kept in `extra`';

    public function handle(UserFields $fields, Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        $field = $fields->get($name);

        if ($field === null) {
            $known = array_map(static fn ($field): string => $field->name, $fields->all());
            $this->components->error("No field «{$name}» of the visitors. Known: ".($known === [] ? 'none' : implode(', ', $known)).'.');

            return self::FAILURE;
        }

        $column = $field->indexColumn();
        $directory = database_path('migrations');
        $slug = 'index_users_'.$column;

        if ($files->glob("{$directory}/*_{$slug}.php") !== []) {
            $this->components->error("A migration for «{$name}» is there already: database/migrations/*_{$slug}.php.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($directory);
        $path = $directory.'/'.date('Y_m_d_His').'_'.$slug.'.php';
        $files->put($path, $this->migration($name, $column, $field->unique));

        $this->components->info('Written database/migrations/'.basename($path).' — run `php artisan migrate`.');

        return self::SUCCESS;
    }

    private function migration(string $name, string $column, bool $unique): string
    {
        $index = $unique ? 'unique' : 'index';
        $drop = $unique ? 'dropUnique' : 'dropIndex';
        // The path goes inside a string in double quotes of the migration: its own quotes escaped.
        $path = '$.\\"'.$name.'\\"';

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            // Written by `webx:users:index-field {$name}`: the field «{$name}» of the visitors, out of
            // the JSON of `extra` into a column the database keeps itself, and an index on it.
            return new class extends Migration
            {
                public function up(): void
                {
                    \$driver = Schema::getConnection()->getDriverName();

                    Schema::table('users', function (Blueprint \$table) use (\$driver): void {
                        \$column = \$table->string('{$column}', 191)->nullable();

                        match (\$driver) {
                            'sqlite' => \$column->virtualAs("json_extract(extra, '{$path}')"),
                            'pgsql' => \$column->storedAs("extra->>'{$name}'"),
                            default => \$column->virtualAs("json_unquote(json_extract(extra, '{$path}'))"),
                        };

                        \$table->{$index}('{$column}');
                    });
                }

                public function down(): void
                {
                    Schema::table('users', function (Blueprint \$table): void {
                        \$table->{$drop}(['{$column}']);
                        \$table->dropColumn('{$column}');
                    });
                }
            };

            PHP;
    }
}
