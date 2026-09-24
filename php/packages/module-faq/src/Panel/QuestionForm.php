<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Faq\Http\Resources\QuestionResource;
use WebxUi\Faq\Models\Question;
use WebxUi\Localization\Locales;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§4.5).
 *
 * The screen is `faq.form`, keyed by field name, so what a question is made of is decided by the
 * description: a project's field arrives as a patch and is saved here by being on the screen at
 * all. What this class knows is which names are the question's own; everything else is `extra`.
 *
 * No draft (decision 12): a save is what the site shows, at once.
 */
final class QuestionForm
{
    /**
     * The question's own fields.
     *
     * @var list<string>
     */
    private const OWN = ['question', 'answer', 'published'];

    /**
     * The screen's fields stored beside the question rather than in it.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories'];

    /** The fields that are a map of languages rather than a value. */
    private const TRANSLATED = ['question', 'answer'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * A question and the values of its screen.
     *
     * @return array<string, mixed>
     */
    public function describe(Question $question): array
    {
        return [
            'question' => new QuestionResource($question->loadMissing('categories')),
            'values' => $this->values($question),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Question $question): array
    {
        return [
            // The project's fields first, so that none of them can stand in for one of the
            // question's own.
            ...($question->extraRaw() ?? []),
            'question' => $question->getTranslations('question'),
            'answer' => $question->getTranslations('answer'),
            'published' => $question->published,
            'categories' => $question->categories()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all(),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new question or an existing one,
     * the panel's door and an agent's alike.
     *
     * In one transaction: a question whose categories were refused must not be left behind half
     * written, and a new one must not be left behind at all.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Question $question, array $input, ?callable $can = null): Question
    {
        $split = $this->record->split(Question::SCREEN, $input, self::OWN, self::TAKEN, $can);

        return $this->db->transaction(function () use ($question, $split): Question {
            foreach ($split->own as $field => $value) {
                if ($field === 'published') {
                    $question->published = (bool) $value;

                    continue;
                }

                $this->translate($question, $field, $value);
            }

            if ($split->extra !== []) {
                $question->setAttribute('extra', $this->record->merge(Question::SCREEN, $question->extraRaw(), $split->extra));
            }

            $question->save();

            if (array_key_exists('categories', $split->taken)) {
                $categories = $split->taken['categories'];

                // Through the shared code, which keeps the question's place inside a category it
                // was already in and gives it one by the whole list in a category it was not.
                $question->syncCategories(is_array($categories) ? array_values(array_map(intval(...), $categories)) : []);
            }

            return $question->refresh();
        });
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''` or null and is taken away; a language nobody mentioned is a
     * language nobody meant to delete.
     */
    private function translate(Question $question, string $field, mixed $value): void
    {
        if (! in_array($field, self::TRANSLATED, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->current() => $value];
        $translations = [...$question->getTranslations($field), ...$map];

        $translations = array_filter(
            $translations,
            static fn (mixed $text): bool => is_string($text) && trim($text) !== '',
        );

        $question->setTranslations($field, $translations);
    }
}
