<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Localization\Locales;
use WebxUi\Reviews\Models\Review;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§4.7).
 *
 * The screen is `reviews.form`, keyed by field name, so what a review is made of is decided by the
 * description: a project's field arrives as a patch and is saved here by being on the screen at
 * all. What this class knows is which names are the review's own; everything else is `extra`.
 *
 * No draft (decision 10): a save is what the site shows, at once.
 */
final class ReviewForm
{
    /**
     * The review's own fields.
     *
     * @var list<string>
     */
    private const OWN = ['name', 'job_title', 'text', 'rating', 'reviewed_on', 'profile_url', 'photo', 'published'];

    /**
     * The screen's fields stored beside the review rather than in it.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories'];

    /** The fields that are a map of languages rather than a value. */
    private const TRANSLATED = ['name', 'job_title', 'text'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * A review and the values of its screen — what `GET`, `POST` and `PUT` answer.
     *
     * @return array<string, mixed>
     */
    public function describe(Review $review): array
    {
        return [
            'review' => [
                'id' => (int) $review->getKey(),
                'name' => ReviewNames::of($review, $this->locales),
                'published' => $review->published,
                'deleted_at' => $review->deleted_at?->toAtomString(),
            ],
            'values' => $this->values($review),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Review $review): array
    {
        return [
            // The project's fields first, so that none of them can stand in for one of the
            // review's own.
            ...($review->extraRaw() ?? []),
            'name' => $review->getTranslations('name'),
            'job_title' => $review->getTranslations('job_title'),
            'text' => $review->getTranslations('text'),
            'rating' => $review->rating,
            'reviewed_on' => $review->reviewed_on?->toDateString(),
            'profile_url' => $review->profile_url,
            'photo' => $review->photo,
            'published' => $review->published,
            'categories' => $review->categories()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all(),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new review or an existing one, the
     * panel's door and an agent's alike.
     *
     * In one transaction: a review whose categories were refused must not be left behind half
     * written, and a new one must not be left behind at all.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Review $review, array $input, ?callable $can = null): Review
    {
        $split = $this->record->split(Review::SCREEN, $input, self::OWN, self::TAKEN, $can);

        $this->checkProfile($split->own['profile_url'] ?? null);

        return $this->db->transaction(function () use ($review, $split): Review {
            foreach ($split->own as $field => $value) {
                $this->write($review, $field, $value);
            }

            if ($split->extra !== []) {
                $review->setAttribute('extra', $this->record->merge(Review::SCREEN, $review->extraRaw(), $split->extra));
            }

            $review->save();

            if (array_key_exists('categories', $split->taken)) {
                $categories = $split->taken['categories'];

                // Through the shared code, which keeps the review's place inside a category it
                // was already in and gives it one by the whole list in a category it was not.
                $review->syncCategories(is_array($categories) ? array_values(array_map(intval(...), $categories)) : []);
            }

            return $review->refresh();
        });
    }

    private function write(Review $review, string $field, mixed $value): void
    {
        switch ($field) {
            case 'published':
                $review->published = (bool) $value;
                break;
            case 'rating':
                // The stars draw 0 when they are cleared: that is no rating, not a bad one.
                $review->rating = is_numeric($value) && (int) $value > 0 ? (int) $value : null;
                break;
            case 'reviewed_on':
                $review->setAttribute('reviewed_on', $this->date($value));
                break;
            case 'profile_url':
                $review->profile_url = is_string($value) && trim($value) !== '' ? trim($value) : null;
                break;
            case 'photo':
                $review->photo = is_array($value) ? $value : null;
                break;
            default:
                $this->translate($review, $field, $value);
        }
    }

    /**
     * A link to where the person is — a profile, a company — and nothing a browser would run:
     * `javascript:` in an `href` on the site is somebody else's script on every page it is on.
     *
     * @throws ValidationException
     */
    private function checkProfile(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $url = trim($value);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return;
        }

        throw ValidationException::withMessages([
            'profile_url' => (string) __('webx-reviews::errors.profile-url'),
        ]);
    }

    /** A day, whatever the picker sent: the date part of the moment, in the offset it came in. */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1
            ? trim($value)
            : Carbon::parse($value)->toDateString();
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''` or null and is taken away; a language nobody mentioned is a
     * language nobody meant to delete.
     */
    private function translate(Review $review, string $field, mixed $value): void
    {
        if (! in_array($field, self::TRANSLATED, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->current() => $value];
        $translations = [...$review->getTranslations($field), ...$map];

        $translations = array_filter(
            $translations,
            static fn (mixed $text): bool => is_string($text) && trim($text) !== '',
        );

        $review->setTranslations($field, $translations);
    }
}
