<?php

declare(strict_types=1);

namespace WebxUi\Templates;

/**
 * What a template may use, and how a name it uses is looked up: `knows()` of `@webx-ui/core` is the
 * same walk in the browser.
 *
 * @template T of Variable
 */
final readonly class Variables
{
    /**
     * @param  list<T>  $variables
     */
    public function __construct(public array $variables) {}

    /**
     * The variable a template name stands for, a family included.
     *
     * @return T|null
     */
    public function variable(string $name): ?Variable
    {
        foreach ($this->variables as $variable) {
            if ($variable->matches($name)) {
                return $variable;
            }
        }

        return null;
    }

    /**
     * Whether a name a template uses, inside these sections (innermost last), is one of these: a
     * field of an enclosing list first — `first` and `last` are every list's — then a variable, how
     * Mustache looks it up.
     *
     * @param  list<string>  $sections
     */
    public function knows(string $name, array $sections = []): bool
    {
        foreach (array_reverse($sections) as $section) {
            $list = $this->variable($section);

            if ($list !== null && $list->isList() && (array_key_exists($name, $list->fields) || in_array($name, Template::POSITIONS, true))) {
                return true;
            }
        }

        return $this->variable($name) !== null;
    }

    /**
     * The example values, for a test message and the editor's preview.
     *
     * @return array<string, string|list<array<string, string>>>
     */
    public function examples(): array
    {
        $examples = [];

        foreach ($this->variables as $variable) {
            if (! $variable->isFamily()) {
                $examples[$variable->name] = $variable->example;
            }
        }

        return $examples;
    }
}
