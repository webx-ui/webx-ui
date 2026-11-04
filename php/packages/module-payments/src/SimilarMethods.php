<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * Which methods a buyer could mistake for one another (groups spec §5): two enabled methods of one
 * group with the same marks, whichever order they are in. «Card with Apple Pay» beside «only Apple
 * Pay» is not that: the sets differ, and so do the lines the buyer reads.
 *
 * A method without marks has nothing to compare, and a switched-off one warns of nothing until it
 * is switched on. Pure: it reads the types and the config, no query.
 */
final readonly class SimilarMethods
{
    public function __construct(
        private MethodTypes $types,
        private MethodGroups $groups,
        private PaymentMarks $marks,
    ) {}

    /**
     * @param  list<Method>  $methods
     * @return array<string, list<string>> a method's key → the keys of those like it; the rest are not here
     */
    public function of(array $methods): array
    {
        $alike = [];

        foreach ($methods as $method) {
            if (! $method->enabled || ! $this->types->has($method->type)) {
                continue;
            }

            $type = $this->types->get($method->type);
            $marks = $this->marks->of($method, $type);

            if ($marks !== []) {
                // `of()` gives the marks in the order of the registry, so equal sets read the same.
                $alike[$this->groups->of($method, $type)."\0".implode(' ', $marks)][] = $method->key;
            }
        }

        $similar = [];

        foreach ($alike as $keys) {
            foreach (count($keys) > 1 ? $keys : [] as $key) {
                $similar[$key] = array_values(array_diff($keys, [$key]));
            }
        }

        return $similar;
    }
}
