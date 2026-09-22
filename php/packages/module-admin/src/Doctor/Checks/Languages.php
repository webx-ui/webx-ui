<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Translations\DictionaryBuilder;

/**
 * The panel's own words, and the languages the site publishes in.
 *
 * Two lists that are meant to be about different things and are confused for one another every
 * time. `webx-localization.locales` is a seed, written into the `locales` table on the first
 * migration and never read again; the table is what the site actually publishes in. A site
 * whose configuration has been edited since therefore believes it has a language it does not,
 * and the symptom is a field that will not save rather than anything about languages.
 *
 * The dictionary is the other half. The panel waits for it before it draws anything, so a
 * dictionary that answers with nothing is a panel that never appears — and it is built from
 * files in every installed package, which means the answer changes with each one of them.
 */
final class Languages implements Check
{
    public function __construct(
        private readonly Repository $config,
        private readonly Locales $locales,
        private readonly DictionaryBuilder $dictionary,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        return [...$this->content(), ...$this->panel()];
    }

    /**
     * The table against the seed that filled it.
     *
     * @return list<Diagnosis>
     */
    private function content(): array
    {
        try {
            $codes = $this->locales->codes();
        } catch (Throwable $failure) {
            return [Diagnosis::fail('Languages', 'the locales table could not be read: '.$failure->getMessage())];
        }

        if ($codes === []) {
            return [Diagnosis::fail(
                'Languages',
                'the locales table is empty, so nothing can be written in any language — run `php artisan migrate`, which seeds it from config/webx-localization.php.',
            )];
        }

        /** @var list<array<string, mixed>> $seed */
        $seed = (array) $this->config->get('webx-localization.locales', []);

        $missing = array_values(array_diff(
            array_values(array_filter(array_map(
                static fn (mixed $entry): ?string => is_array($entry) && is_string($entry['code'] ?? null) ? $entry['code'] : null,
                $seed,
            ))),
            $codes,
        ));

        $found = [];

        if ($missing !== []) {
            $found[] = Diagnosis::warn(
                'Languages',
                'config/webx-localization.php lists '.implode(', ', $missing)
                .', and the locales table does not have them — the config is only a seed, so add them in the panel under Settings.',
            );
        }

        $fallback = $this->locales->fallback();

        if (! in_array($fallback, $codes, true)) {
            $found[] = Diagnosis::fail(
                'Languages',
                "the fallback is [{$fallback}] and the site does not publish in it — every missing translation falls back to nothing. Set `webx-localization.fallback` to one of ".implode(', ', $codes).'.',
            );
        }

        return $found === []
            ? [Diagnosis::ok('Languages', implode(', ', $codes).' — the table and the fallback agree.')]
            : $found;
    }

    /**
     * Whether the dictionary answers, in every language the panel offers.
     *
     * @return list<Diagnosis>
     */
    private function panel(): array
    {
        $codes = array_column($this->locales->panel(), 'code');
        $found = [];

        foreach ($codes as $code) {
            try {
                $dictionary = $this->dictionary->build((string) $code);
            } catch (Throwable $failure) {
                $found[] = Diagnosis::fail('Panel words', "[{$code}] could not be built: ".$failure->getMessage());

                continue;
            }

            $namespaces = is_array($dictionary['namespaces'] ?? null) ? $dictionary['namespaces'] : [];

            if ($namespaces === []) {
                $found[] = Diagnosis::fail(
                    'Panel words',
                    "the dictionary for [{$code}] is empty, and the panel waits for it before it draws anything — check that the packages' lang directories are readable and clear the cache.",
                );
            }
        }

        return $found === []
            ? [Diagnosis::ok('Panel words', 'the dictionary answers in '.implode(', ', $codes).'.')]
            : $found;
    }
}
