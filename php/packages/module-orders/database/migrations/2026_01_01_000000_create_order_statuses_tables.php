<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\StatusColor;

/**
 * The two sets of statuses (spec §3, §5.2): public ones the buyer sees, internal ones the shop
 * works in, each internal one inside one public group. Rows, not an enum — every shop names its
 * own stages (decision 11) — and before the orders, because an order points at its status.
 *
 * The set a site starts with is seeded here from the `statuses` group of the dictionary, in every
 * language of the site at once (§14); after that it is the site's own, and nothing here touches it
 * again.
 */
return new class extends Migration
{
    /**
     * The set a new site starts with: public code => its internal ones, key => the milestone the
     * status sets and its colour. The keys only find the words; the rows keep no key (§3).
     *
     * @var array<string, array<string, array{milestone: Milestone|null, color: StatusColor}>>
     */
    private const array SET = [
        'accepted' => [
            'new' => ['milestone' => null, 'color' => StatusColor::Info],
            'awaiting_payment' => ['milestone' => null, 'color' => StatusColor::Warning],
        ],
        'in_progress' => [
            'taken' => ['milestone' => null, 'color' => StatusColor::Accent],
            'awaiting_call' => ['milestone' => null, 'color' => StatusColor::Warning],
            'packing' => ['milestone' => null, 'color' => StatusColor::Accent],
        ],
        'shipped' => [
            'handed_to_delivery' => ['milestone' => null, 'color' => StatusColor::Info],
        ],
        'completed' => [
            'completed' => ['milestone' => Milestone::Received, 'color' => StatusColor::Success],
        ],
        'cancelled' => [
            'cancelled' => ['milestone' => Milestone::Cancelled, 'color' => StatusColor::Neutral],
        ],
    ];

    public function up(): void
    {
        Schema::create('order_public_statuses', function (Blueprint $table): void {
            $table->id();
            // What templates and agents say: renaming the status leaves it as it was.
            $table->string('code', 48)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('position');
            // `notify_channels` — the channels that fire on entering this status (§10.2).
            $table->json('options')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('order_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_status_id')->constrained('order_public_statuses');
            $table->json('name');
            // A name from the closed palette, which the panel turns into `--wx-*` tokens: a hex
            // picked in the light theme is unreadable in the dark one.
            $table->string('color', 16);
            $table->unsignedSmallInteger('position');
            // `paid`, `received` or `cancelled` — what moving an order here also marks (decision 13).
            $table->string('sets_milestone', 24)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Named, so sqlite has it too: the statuses of a group, in their order.
            $table->index(['public_status_id', 'position']);
        });

        $this->seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
        Schema::dropIfExists('order_public_statuses');
    }

    private function seed(): void
    {
        $now = Carbon::now();
        $locales = $this->locales();
        $publicPosition = 0;
        $position = 0;

        foreach (self::SET as $code => $internal) {
            $publicId = DB::table('order_public_statuses')->insertGetId([
                'code' => $code,
                'name' => $this->words("public.{$code}.name", $locales),
                'description' => $this->words("public.{$code}.description", $locales),
                'position' => $publicPosition++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($internal as $key => $status) {
                DB::table('order_statuses')->insert([
                    'public_status_id' => $publicId,
                    'name' => $this->words("internal.{$key}", $locales),
                    'color' => $status['color']->value,
                    'position' => $position++,
                    'sets_milestone' => $status['milestone']?->value,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * The languages of the site, and the fallback one in any case: a language added later reads
     * it until somebody writes its own words.
     *
     * @return list<string>
     */
    private function locales(): array
    {
        $locales = app(Locales::class);

        return array_values(array_unique([$locales->fallback(), ...$locales->codes()]));
    }

    /**
     * One line of the dictionary in every language given; a language the package ships no words
     * in gets the fallback's.
     *
     * @param  list<string>  $locales
     */
    private function words(string $key, array $locales): string
    {
        $words = [];

        foreach ($locales as $locale) {
            $words[$locale] = (string) trans("webx-orders::statuses.{$key}", [], $locale);
        }

        return (string) json_encode($words, JSON_UNESCAPED_UNICODE);
    }
};
