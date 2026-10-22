<?php

// The table the panel transliterates titles with while they are typed
// (packages/module-admin/src/transliteration.json), taken from the one the server uses —
// `Str::slug($text, '-', $locale)`, which is what WebxUi\Localization\Slug calls. The field
// shows the address the server is going to make, so the two must not drift apart; the
// localization package's TransliterationTableTest includes this file and compares.
//
// Letter by letter: Latin with diacritics (U+00C0–U+024F) and Cyrillic (U+0400–U+04FF), for the
// languages the packages ship; any other falls back to `en`. `en` is whole, every other language
// keeps only where it differs from `en`.
//
// Run from the repository root after updating voku/portable-ascii, then prettier the JSON:
//   php scripts/transliteration.php && npx prettier --write packages/module-admin/src/transliteration.json

declare(strict_types=1);

use Illuminate\Support\Str;

$table = static function (): array {
    $characters = [];

    foreach ([[0x00C0, 0x024F], [0x0400, 0x04FF]] as [$from, $to]) {
        for ($code = $from; $code <= $to; $code++) {
            $character = mb_chr($code, 'UTF-8');

            if ($character !== false && preg_match('/^\p{L}$/u', $character) === 1) {
                $characters[] = $character;
            }
        }
    }

    $table = [];

    foreach (['en', 'de', 'es', 'fr', 'it', 'pl', 'pt', 'ru', 'tr', 'uk'] as $locale) {
        $map = [];

        foreach ($characters as $character) {
            $ascii = Str::ascii($character, $locale);

            if ($ascii !== ($locale === 'en' ? $character : ($table['en'][$character] ?? $character))) {
                $map[$character] = $ascii;
            }
        }

        $table[$locale] = $map;
    }

    return $table;
};

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__.'/../php/vendor/autoload.php';

    $json = json_encode($table(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
    file_put_contents(__DIR__.'/../packages/module-admin/src/transliteration.json', $json."\n");
}

return $table;
