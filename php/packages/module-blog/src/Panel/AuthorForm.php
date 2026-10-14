<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Author;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Seo\Fields;

/**
 * The profile's screen on the server side: what its fields hold and what a save writes (§19.5).
 *
 * `blog.author-form`, keyed by field name, like the person of the team: no draft — a profile is
 * what the site shows the moment it is saved (§19.1, decision 6). Two fields are not columns: the
 * photo, which the media field edits as a library key and the table holds as the id of a file, as
 * an article's cover; and the SEO card, which `module-seo` put on the screen and keeps in its own
 * table.
 *
 * The account is chosen once, when the profile is made, and is not a field of the screen: whose
 * profile this is does not change, and a select that could move it would move every byline.
 */
final class AuthorForm
{
    /**
     * The profile's own fields.
     *
     * @var list<string>
     */
    private const OWN = ['title', 'slug', 'role', 'bio', 'photo', 'is_public'];

    /** The fields that are a map of languages rather than a value. */
    private const TRANSLATED = ['title', 'slug', 'role', 'bio'];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
        private readonly MediaFiles $files,
    ) {}

    /**
     * A profile and the values of its screen — what `GET`, `POST` and `PUT` answer.
     *
     * @return array{author: array<string, mixed>, values: array<string, mixed>}
     */
    public function describe(Author $author): array
    {
        $author->loadMissing('user');

        return [
            'author' => AuthorRow::of($author, $this->locales),
            'values' => $this->values($author),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Author $author): array
    {
        $photo = $author->photo_id === null ? null : MediaFile::query()->find($author->photo_id);

        return [
            'title' => $author->getTranslations('title'),
            'slug' => $author->getTranslations('slug'),
            'role' => $author->getTranslations('role'),
            'bio' => $author->getTranslations('bio'),
            // The key alone: the field asks the library where that key lives now.
            'photo' => $photo instanceof MediaFile ? ['path' => $photo->path] : null,
            'is_public' => $author->is_public,
            Fields::SCREEN => $author->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new profile or an existing one.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Author $author, array $input, ?int $userId = null, ?callable $can = null): Author
    {
        if (! $author->exists) {
            $author->user_id = $this->account($userId);
        }

        $split = $this->record->split(Author::SCREEN, $input, self::OWN, [Fields::SCREEN], $can);

        return $this->db->transaction(function () use ($author, $split): Author {
            foreach ($split->own as $field => $value) {
                $this->write($author, $field, $value);
            }

            $this->checkName($author);

            $author->save();

            // Only when it travelled: a save of the profile alone must not empty a card nobody
            // opened.
            if (array_key_exists(Fields::SCREEN, $split->taken)) {
                $value = $split->taken[Fields::SCREEN];

                $author->saveSeo(is_array($value) ? $value : null);
            }

            return $author->refresh();
        });
    }

    private function write(Author $author, string $field, mixed $value): void
    {
        switch ($field) {
            case 'is_public':
                $author->is_public = (bool) $value;
                break;
            case 'photo':
                $path = is_array($value) ? $value['path'] ?? null : null;
                $file = is_string($path) && $path !== '' ? $this->files->find($path) : null;
                $author->photo_id = $file === null ? null : (int) $file->getKey();
                break;
            default:
                $this->translate($author, $field, $value);
        }
    }

    /**
     * An account that exists and has no profile yet — one profile per account (§19.2), under the
     * field the panel draws it in.
     *
     * @throws ValidationException
     */
    private function account(?int $userId): int
    {
        if ($userId === null || ! CmsUser::query()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => (string) __('webx-blog::author.account-missing')]);
        }

        if (Author::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => (string) __('webx-blog::author.account-taken')]);
        }

        return $userId;
    }

    /**
     * The one required field: a name in the default language — what the byline and the page say
     * where nobody translated it. Under the language, so the form shows it on the chip of that
     * language.
     *
     * @throws ValidationException
     */
    private function checkName(Author $author): void
    {
        $default = $this->locales->defaultCode();
        $name = $author->getTranslation('title', $default, fallback: false);

        if (is_string($name) && trim($name) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            "title.{$default}" => (string) __('webx-blog::author.name-required'),
        ]);
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied is taken away; a language nobody mentioned is a language nobody meant to delete.
     */
    private function translate(Author $author, string $field, mixed $value): void
    {
        if (! in_array($field, self::TRANSLATED, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->current() => $value];
        $translations = [...$author->getTranslations($field), ...$map];

        $translations = array_filter(
            $translations,
            static fn (mixed $text): bool => is_string($text) && trim($text) !== '',
        );

        $author->setTranslations($field, $translations);
    }
}
