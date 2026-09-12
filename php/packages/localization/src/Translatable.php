<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Database\Schema\Blueprint;

/**
 * The columns behind `HasTranslations`.
 *
 * A language map is an ordinary JSON column, so this adds nothing Eloquent could not do on its
 * own. What it adds is the word: a migration that says `translatable('title')` states which
 * columns hold every language, and a reader does not have to infer it from a type.
 */
final class Translatable
{
    public static function columns(Blueprint $table, string ...$columns): void
    {
        foreach ($columns as $column) {
            // Nullable because a record can exist before anybody has written a word of it —
            // a draft created from an import, a row made by a parent entity.
            $table->json($column)->nullable();
        }
    }

    public static function dropColumns(Blueprint $table, string ...$columns): void
    {
        $table->dropColumn($columns);
    }
}
