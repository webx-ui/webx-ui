<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Panel;

use RuntimeException;
use WebxUi\Configurator\Schema\Problem;

/**
 * A write the configurator does not take: a key that is taken, a form that does not exist, or a
 * publication of a schema with problems — then `$problems` lists them, path by path. `$reason` is
 * which rule refused it — `key.taken`, `form.unknown` — for the panel to say in its own words
 * under the field it names.
 */
final class Refused extends RuntimeException
{
    /**
     * @param  list<Problem>  $problems
     */
    public function __construct(string $message, public readonly array $problems = [], public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }

    /** The field a refusal is about — the first word of its reason. */
    public function field(): ?string
    {
        return $this->reason === null ? null : explode('.', $this->reason)[0];
    }

    /**
     * @param  list<Problem>  $problems
     */
    public static function schema(array $problems): self
    {
        $lines = array_map(
            static fn (Problem $problem): string => ($problem->path === '' ? '' : $problem->path.': ').$problem->message().' ['.$problem->code.']',
            array_slice($problems, 0, 20),
        );

        $more = count($problems) > 20 ? ' …and '.(count($problems) - 20).' more.' : '';

        return new self('The schema has problems: '.implode('; ', $lines).$more, $problems);
    }
}
