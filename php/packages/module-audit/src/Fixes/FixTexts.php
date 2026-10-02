<?php

declare(strict_types=1);

namespace WebxUi\Audit\Fixes;

use WebxUi\Audit\Contracts\AuditFix;

/**
 * A fix's button and what it says it does, from the dictionary of whoever registered it:
 * `<namespace>::fixes.<id>.title` and `.description`. The audit's own live under `webx-audit`;
 * a module's fix names its namespace with `textNamespace()`, as a check does.
 */
final class FixTexts
{
    /**
     * @return array{title: string, description: string}
     */
    public static function of(AuditFix $fix): array
    {
        $namespace = method_exists($fix, 'textNamespace') ? (string) $fix->textNamespace() : 'webx-audit';
        $texts = [];

        foreach (['title', 'description'] as $part) {
            $key = $namespace.'::fixes.'.$fix->id().'.'.$part;
            $line = __($key);
            $texts[$part] = is_string($line) && $line !== $key ? $line : ($part === 'title' ? $fix->id() : '');
        }

        return $texts;
    }
}
