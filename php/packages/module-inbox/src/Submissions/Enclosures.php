<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use WebxUi\Inbox\Contracts\Enclosure;
use WebxUi\Inbox\Models\Form;

/**
 * The enclosures modules have registered (§2.20), and the ones a request asks for.
 *
 * A singleton: a module registers its own in `boot()`, when the inbox is installed, and the
 * public door asks it after the rules of the fields.
 */
final class Enclosures
{
    /** The hidden field a form names its enclosures in. */
    public const string FIELD = 'webx_enclosure';

    /** The `type` of the answer row an enclosure is written as. */
    public const string TYPE = 'enclosure';

    /** @var array<string, Enclosure> */
    private array $enclosures = [];

    public function register(Enclosure $enclosure): void
    {
        $this->enclosures[$enclosure->name()] = $enclosure;
    }

    public function has(string $name): bool
    {
        return isset($this->enclosures[$name]);
    }

    /**
     * What the request encloses, by name, in the order it named them — or the errors of all of
     * them at once, so a visitor reads every problem in one answer.
     *
     * A name nobody registered is ignored rather than refused: the module that printed it may have
     * been removed since the page was cached, and the visitor's contacts are worth more than the
     * envelope.
     *
     * @return array<string, Enclosed>
     *
     * @throws ValidationException
     */
    public function receive(Form $form, Request $request): array
    {
        $errors = new MessageBag;
        $enclosed = [];

        foreach ($this->requested($request) as $name) {
            $enclosure = $this->enclosures[$name];

            if (! $enclosure->accepts($form, $request)) {
                $errors->add($name, (string) trans('webx-inbox::errors.enclosure', ['name' => $name]));

                continue;
            }

            try {
                $enclosed[$name] = $enclosure->make($form, $request);
            } catch (ValidationException $refused) {
                $errors->merge($refused->errors());
            }
        }

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages($errors->toArray());
        }

        return $enclosed;
    }

    /**
     * The registered names the request asked for, each once.
     *
     * @return list<string>
     */
    public function requested(Request $request): array
    {
        $asked = $request->input(self::FIELD);
        $asked = is_array($asked) ? $asked : [$asked];
        $names = [];

        foreach ($asked as $name) {
            if (is_string($name) && $this->has($name) && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
