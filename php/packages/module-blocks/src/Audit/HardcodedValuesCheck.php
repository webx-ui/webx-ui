<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Audit;

use WebxUi\Admin\Shortcodes\Shortcode;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Blocks\StrayValues;

/**
 * A value typed by hand where a data shortcode holds it (`blocks.hardcoded_values`): the phone
 * number in the text of a block, while «Settings» → «Shortcodes» has `[phone]`. It is right today
 * and wrong the day the number changes, on this page alone.
 *
 * Only the shortcodes the panel defines are compared — they are the ones that hold a value; a
 * site's own `[dot]` is styling. A phone number is found however it is spaced or punctuated, an
 * e-mail or any other value as it is written, case aside. A value shorter than five characters is
 * not compared: too much prose would match it.
 */
final class HardcodedValuesCheck extends ModuleCheck
{
    public const ID = 'blocks.hardcoded_values';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-blocks';

    private const SHORTEST = 5;

    public function __construct(
        private readonly StrayValues $strays,
        private readonly ShortcodeScan $scan,
        private readonly Shortcodes $shortcodes,
    ) {}

    public function run(AuditContext $context): iterable
    {
        $patterns = $this->patterns();

        if ($patterns === []) {
            return;
        }

        $records = null;

        foreach ($this->strays->entities() as $entity) {
            $rows = [];

            foreach ($this->scan->texts($entity) as $text) {
                foreach ($patterns as $name => [$pattern, $value]) {
                    if (preg_match($pattern, $text['text']) === 1) {
                        $rows[] = [
                            'block' => $text['block'],
                            'field' => $text['field'],
                            'value' => $value.' → ['.$name.']',
                            'published' => $text['published'],
                        ];
                    }
                }
            }

            if ($rows === []) {
                continue;
            }

            $records ??= $this->scan->records();
            $key = StrayValuesCheck::key($entity);
            [$label, $edit] = $records[$key] ?? [class_basename($entity).' #'.$entity->getKey(), null];

            yield $this->found(
                'hardcoded-values',
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
     * A pattern per data shortcode, with the value it looks for.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function patterns(): array
    {
        $patterns = [];

        foreach ($this->shortcodes->all() as $name => $shortcode) {
            $pattern = $shortcode->origin === 'settings' ? self::pattern($shortcode) : null;

            if ($pattern !== null) {
                $patterns[$name] = [$pattern, $shortcode->plain()];
            }
        }

        return $patterns;
    }

    /** How a value is found in text, or null when it is too short to look for. */
    public static function pattern(Shortcode $shortcode): ?string
    {
        $value = trim($shortcode->plain());

        if (mb_strlen($value) < self::SHORTEST) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        // A phone number: its digits in order, with whatever people put between them, and not
        // the middle of a longer number.
        if (preg_match('/^\+?[\d\s().\-\/]+$/', $value) === 1 && strlen($digits) >= 6) {
            return '/(?<!\d)'.implode('[\s().\-\/]*', str_split($digits)).'(?!\d)/';
        }

        return '/'.preg_quote($value, '/').'/iu';
    }
}
