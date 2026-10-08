<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Audit;

use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Blocks\StrayValues;

/**
 * Brackets that were meant as a shortcode and are not one (`blocks.unknown_shortcodes`): a page
 * prints `[phnoe]` to its visitors exactly as typed.
 *
 * Not every bracket is a shortcode — `[1]`, `[sic]` and `[citation needed]` are prose — so only a
 * name that is nearly a registered one (a letter or two off, or the same letters in another
 * order) or that carries `key=value` arguments is reported. A literal `[[name]]` is never.
 */
final class UnknownShortcodesCheck extends ModuleCheck
{
    public const ID = 'blocks.unknown_shortcodes';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-blocks';

    public function __construct(
        private readonly StrayValues $strays,
        private readonly ShortcodeScan $scan,
        private readonly Shortcodes $shortcodes,
    ) {}

    public function run(AuditContext $context): iterable
    {
        $known = array_keys($this->shortcodes->all());
        $records = null;

        foreach ($this->strays->entities() as $entity) {
            $rows = [];

            foreach ($this->scan->texts($entity) as $text) {
                foreach ($this->shortcodes->names($text['text']) as $found) {
                    if ($found['known'] || $found['escaped'] || ! self::meant($found['name'], $found['args'], $known)) {
                        continue;
                    }

                    $near = self::nearest($found['name'], $known);
                    $rows[] = [
                        'block' => $text['block'],
                        'field' => $text['field'],
                        'value' => '['.$found['raw'].']'.($near !== null ? ' → ['.$near.']' : ''),
                        'published' => $text['published'],
                    ];
                }
            }

            if ($rows === []) {
                continue;
            }

            $records ??= $this->scan->records();
            $key = StrayValuesCheck::key($entity);
            [$label, $edit] = $records[$key] ?? [class_basename($entity).' #'.$entity->getKey(), null];

            yield $this->found(
                'unknown-shortcodes',
                ['entity' => $label, 'count' => count($rows)],
                key: $key,
                table: [
                    'columns' => [
                        Finding::column('block'),
                        Finding::column('field'),
                        Finding::column('value'),
                        Finding::column('published', 'bool'),
                        Finding::column('edit', 'edit'),
                    ],
                    'rows' => array_map(static fn (array $row): array => [...$row, 'edit' => $edit], $rows),
                ],
            );
        }
    }

    /**
     * Whether a bracket was meant as a shortcode: it has arguments, or its name is a slip of a
     * registered one.
     *
     * @param  array<string, string>  $args
     * @param  list<string>  $known
     */
    public static function meant(string $name, array $args, array $known): bool
    {
        return $args !== [] || self::nearest($name, $known) !== null;
    }

    /**
     * The registered name this one is a slip of: the same letters in another order, or two
     * edits off for a name of four letters and more, one for a shorter one.
     *
     * @param  list<string>  $known
     */
    public static function nearest(string $name, array $known): ?string
    {
        $letters = static function (string $word): string {
            $chars = str_split($word);
            sort($chars);

            return implode('', $chars);
        };

        foreach ($known as $candidate) {
            $distance = levenshtein($name, $candidate);
            $allowed = min(strlen($name), strlen($candidate)) >= 4 ? 2 : 1;

            if ($distance <= $allowed || ($letters($name) === $letters($candidate) && strlen($name) > 2)) {
                return $candidate;
            }
        }

        return null;
    }
}
