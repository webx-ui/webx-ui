<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use RuntimeException;

/**
 * Publishing a version that takes `localized` off a field would keep one language of what the
 * pages hold and drop the others ({@see LanguageShapes}). Not done without being asked: the
 * caller repeats the publication with the drop agreed to, or goes and moves the words first.
 *
 * @phpstan-import-type Flip from LanguageShapes
 * @phpstan-import-type Affected from LanguageShapes
 */
final class DropsTranslations extends RuntimeException
{
    /**
     * @param  list<Flip>  $flips
     * @param  list<Affected>  $entities  Only those that would lose words.
     */
    public function __construct(
        public readonly array $flips,
        public readonly array $entities,
    ) {
        parent::__construct(self::sentence($flips, $entities));
    }

    /**
     * @param  list<Flip>  $flips
     * @param  list<Affected>  $entities
     */
    private static function sentence(array $flips, array $entities): string
    {
        $fields = array_map(
            static fn (array $flip): string => $flip['child'] === null ? $flip['field'] : "{$flip['field']}.*.{$flip['child']}",
            array_values(array_filter($flips, static fn (array $flip): bool => ! $flip['localized'])),
        );

        $where = array_map(
            static fn (array $entity): string => class_basename($entity['model']).' #'.$entity['id'].($entity['title'] !== null ? " ({$entity['title']})" : ''),
            array_slice($entities, 0, 10),
        );

        return (string) __('webx-blocks::page.drops-translations', [
            'fields' => implode(', ', $fields),
            'count' => count($entities),
            'pages' => implode('; ', $where),
        ]);
    }
}
