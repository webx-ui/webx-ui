<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

use WebxUi\Audit\Contracts\AuditCheck;

/**
 * The three texts every check carries (decision 11) — what was found, why it matters, how to fix
 * it — plus its title, read from the dictionary of whoever registered it.
 *
 * A module's check names its texts `<namespace>::checks.<id>`; the audit's own live under
 * `webx-audit`. A check without texts shows its id rather than a key.
 */
final class CheckTexts
{
    /**
     * @return array{title: string, found: string, why: string, fix: string}
     */
    public static function of(AuditCheck $check): array
    {
        $texts = [];

        foreach (['title', 'found', 'why', 'fix'] as $part) {
            $key = self::namespace($check).'::checks.'.$check->id().'.'.$part;
            $line = __($key);
            $texts[$part] = is_string($line) && $line !== $key ? $line : ($part === 'title' ? $check->id() : '');
        }

        return $texts;
    }

    /**
     * A summary or a column label stored as a dictionary key, said in the reader's language.
     *
     * @param  array<string, mixed>  $params
     */
    public static function line(string $key, array $params = []): string
    {
        $line = __($key, array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $params));

        return is_string($line) ? $line : $key;
    }

    private static function namespace(AuditCheck $check): string
    {
        return method_exists($check, 'textNamespace') ? (string) $check->textNamespace() : 'webx-audit';
    }
}
