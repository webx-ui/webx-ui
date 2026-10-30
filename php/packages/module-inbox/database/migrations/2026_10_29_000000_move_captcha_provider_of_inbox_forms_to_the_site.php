<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The captcha became the site's (ORDERS §28.1): one provider for every form, chosen on the Captcha
 * tab of the settings. A site that set its forms up before has the provider in each form, so the
 * provider of its switched-on forms becomes the site's — and nothing has to be set up again.
 *
 * Only where the site has not chosen one, and only where `module-settings` keeps the settings: a
 * site without it goes on as before, each form with its own provider. Two providers on different
 * forms — the one more forms use, and a line in the log; the others keep theirs and the audit says
 * so. Nothing in the forms changes: a provider there still means "on". The keys are not moved
 * either — the site's captcha reads the inbox's names of the environment, and `.env` is the site's.
 *
 * `down()` takes the site's choice back off, if it is still the one this wrote.
 */
return new class extends Migration
{
    private const string TABLE = 'cms_settings';

    private const string KEY = 'captcha.provider';

    private const array PROVIDERS = ['recaptcha', 'turnstile'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || DB::table(self::TABLE)->where('key', self::KEY)->exists()) {
            return;
        }

        $counts = $this->counts();

        if ($counts === []) {
            return;
        }

        $provider = array_key_first($counts);

        if (count($counts) > 1) {
            Log::warning('webx-inbox: the forms ask for different captchas ({counts}); the site\'s captcha is now {provider}, the others keep their own.', [
                'counts' => json_encode($counts),
                'provider' => $provider,
            ]);
        }

        DB::table(self::TABLE)->insert([
            'key' => self::KEY,
            'value' => json_encode($provider),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->forget();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $provider = array_key_first($this->counts());

        if ($provider === null) {
            return;
        }

        DB::table(self::TABLE)->where('key', self::KEY)->where('value', json_encode($provider))->delete();

        $this->forget();
    }

    /**
     * How many switched-on forms ask for each provider, the most first; a tie — the first by name.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach (DB::table('inbox_forms')->where('is_enabled', true)->pluck('options') as $options) {
            $options = is_string($options) ? json_decode($options, true) : null;
            // What a form does not say itself is the config's default (§5).
            $provider = (is_array($options) ? ($options['antispam.captcha'] ?? null) : null) ?? config('webx-inbox.antispam.captcha');

            if (is_string($provider) && in_array($provider, self::PROVIDERS, true)) {
                $counts[$provider] = ($counts[$provider] ?? 0) + 1;
            }
        }

        uksort($counts, static fn (string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);

        return $counts;
    }

    /** The settings are kept in the cache: a row written round the model has to move their group. */
    private function forget(): void
    {
        $settings = 'WebxUi\Settings\Settings';

        if (class_exists($settings) && app()->bound($settings)) {
            app($settings)->forget();
        }
    }
};
