<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

/**
 * The languages a site publishes in, written into `config/webx-localization.php`.
 *
 * They are configuration rather than an environment variable because the table is what an
 * installation actually reads and the panel can add one without a deploy; the config is the
 * seed a fresh installation gets. Which makes this the one answer `webx:setup` cannot write
 * with a `.env` line, so it edits the file — and only the file it recognises. A list somebody
 * has already changed is an answer, and the command says the line instead of taking it back.
 */
final class LocalesConfig
{
    /**
     * The file with the languages in it, or null when it is not the one that shipped.
     *
     * @param  list<string>  $codes  The first one is the default.
     */
    public static function rewrite(string $contents, array $codes): ?string
    {
        if ($codes === []) {
            return null;
        }

        $matched = preg_match("/('locales'\s*=>\s*\[\n)(.*?)(\n\s*\],)/s", $contents, $parts);

        if ($matched !== 1) {
            return null;
        }

        // Only over the single English entry the package ships with. Anything else — a second
        // language, a comment, a reordering — is somebody's decision.
        if (preg_match("/^\s*\['code'\s*=>\s*'en',\s*'default'\s*=>\s*true\],\s*$/", $parts[2]) !== 1) {
            return null;
        }

        $lines = [];

        foreach (array_values($codes) as $index => $code) {
            $lines[] = $index === 0
                ? "        ['code' => '{$code}', 'default' => true],"
                : "        ['code' => '{$code}'],";
        }

        return str_replace(
            $parts[0],
            $parts[1].implode("\n", $lines).$parts[3],
            $contents,
        );
    }

    /**
     * The codes a list of answers boils down to: lower case, deduplicated, in the order given.
     *
     * @return list<string>
     */
    public static function codes(string $answer): array
    {
        $codes = array_filter(
            array_map(
                static fn (string $code): string => strtolower(trim($code)),
                explode(',', $answer),
            ),
            static fn (string $code): bool => preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/i', $code) === 1,
        );

        return array_values(array_unique($codes));
    }
}
