<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebxUi\Inbox\Models\Status;

/**
 * What can happen to a submission, as rows rather than as an enum: every installation of this
 * thing has agreed on its own words for "we are on it", and a client who cannot add one ends up
 * keeping the real state in the notes.
 *
 * The three flags are what the panel reads instead of the key, so a renamed status keeps
 * working: one status is what a new submission gets, one kind is spam and is hidden and pruned,
 * and the closed ones are the ones nothing further is expected of.
 */
return new class extends Migration
{
    /** The languages the package ships words in; a status is seeded in all of them at once. */
    private const LOCALES = ['en', 'ru', 'uk', 'de', 'es', 'fr', 'it', 'pl', 'pt', 'tr'];

    public function up(): void
    {
        Schema::create('inbox_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->json('title')->nullable();

            // A tone of `WxBadge` — `default`, `primary`, `success`, `warning`, `info`,
            // `danger` — and not a hex value: the panel has two themes, and a colour picked
            // in one of them is unreadable in the other.
            $table->string('color', 16)->default('default');

            $table->boolean('is_default')->default(false);
            $table->boolean('is_spam')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        $this->seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_statuses');
    }

    /**
     * The five a panel starts with. They are ordinary rows: rename them, recolour them, add
     * others, delete the ones that are in nobody's way.
     */
    private function seed(): void
    {
        $seeds = [
            ['key' => 'new', 'color' => 'primary', 'is_default' => true],
            ['key' => 'in-progress', 'color' => 'warning'],
            ['key' => 'done', 'color' => 'success', 'is_closed' => true],
            ['key' => 'rejected', 'color' => 'default', 'is_closed' => true],
            ['key' => 'spam', 'color' => 'danger', 'is_spam' => true, 'is_closed' => true],
        ];

        foreach ($seeds as $position => $seed) {
            Status::query()->create([
                ...$seed,
                'title' => $this->title($seed['key']),
                'position' => $position,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function title(string $key): array
    {
        $title = [];

        foreach (self::LOCALES as $locale) {
            $title[$locale] = (string) trans("webx-inbox::statuses.{$key}", [], $locale);
        }

        return $title;
    }
};
