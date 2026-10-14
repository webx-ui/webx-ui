<?php

declare(strict_types=1);

namespace WebxUi\Blog\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Blog\Models\Author;
use WebxUi\Blog\Panel\AuthorForm;
use WebxUi\Blog\Panel\AuthorRow;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The profiles of the authors, to an agent (§19.7): who writes this blog, and the words about them.
 *
 * Two tools. The list says who has a profile, whose account it is, whether the page answers and
 * where; `update` changes the words — the name for readers, the role, the bio, the address — and
 * the switch of the page. What is deliberately not here is making a profile: that puts a person's
 * name and face on the site, and it is a person's decision, made in the panel.
 */
final class AuthorTools
{
    public function __construct(
        private readonly AuthorForm $form,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return [
            Tool::read(
                'list',
                'The author profiles of this blog: the name a reader sees, whose account it is, how many articles '
                .'the account wrote, whether the author page answers (open) and its address. A profile is what '
                .'the site says about an administrator who writes; an account without one is named by its own '
                .'name under its articles and has no page.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Profiles whose name, or whose account\'s name, contains this.'],
                ]],
                permission: 'blog.authors.manage',
            ),

            Tool::mutating(
                'update',
                'Change an author profile: the name for readers (title), the role, the bio (HTML, a few paragraphs), '
                .'the last part of the page\'s address (slug) and whether the profile has a page on the site '
                .'(is_public). A text field takes one string in the language you are working in, or a map of '
                .'languages; a language you do not mention is left as it is. Turning is_public on puts the '
                .'person\'s page on the site and makes their byline a link to it — say so to a person first. '
                .'A dry run checks everything a save would, the address included.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->update($arguments, $user),
                ['properties' => [
                    'author' => ['type' => ['integer', 'string'], 'description' => 'The profile: its id, or the slug of its page — "anna".'],
                    'title' => ['type' => ['string', 'object'], 'description' => 'The name a reader sees.'],
                    'role' => ['type' => ['string', 'object'], 'description' => 'What they do: "Editor-in-chief".'],
                    'bio' => ['type' => ['string', 'object'], 'description' => 'A few paragraphs for their page, as HTML.'],
                    'slug' => ['type' => ['string', 'object'], 'description' => 'The last part of the page\'s address.'],
                    'is_public' => ['type' => 'boolean', 'description' => 'Whether the profile has a page on the site.'],
                ], 'required' => ['author']],
                permission: 'blog.authors.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Author::query()->with(['user', 'photo'])->withCount('articles')->orderBy('id');
        $term = trim((string) ($arguments['search'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhereHas('user', static fn (Builder $user): Builder => $user->where('name', 'like', $like));
            });
        }

        $authors = $query->get()->map(fn (Author $author): array => AuthorRow::of($author, $this->locales))->values()->all();

        return [
            'enabled' => Author::enabled(),
            'count' => count($authors),
            'authors' => $authors,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $author = $this->author($arguments['author'] ?? null);
        $values = array_intersect_key($arguments, array_flip(['title', 'role', 'bio', 'slug', 'is_public']));

        // One string is the language the agent works in; the screen checks a map of languages.
        foreach (['title', 'role', 'bio', 'slug'] as $field) {
            if (array_key_exists($field, $values) && ! is_array($values[$field])) {
                $values[$field] = [$this->locales->current() => $values[$field]];
            }
        }

        if ($values === []) {
            throw new ToolFailure('Nothing to change: name at least one of title, role, bio, slug, is_public.');
        }

        $can = static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);

        if (($arguments[Tool::DRY_RUN] ?? false) === true) {
            // The save itself, rolled back: the address is checked against every other address of
            // the site, and nothing short of writing it asks that question honestly.
            $this->db->beginTransaction();

            try {
                $saved = $this->form->save($author, $values, can: $can);

                return ['dry_run' => true, 'fields' => array_keys($values), 'author' => AuthorRow::of($saved, $this->locales)];
            } finally {
                $this->db->rollBack();
            }
        }

        $saved = $this->form->save($author, $values, can: $can);

        return ['author' => AuthorRow::of($saved, $this->locales)];
    }

    private function author(mixed $reference): Author
    {
        $author = null;

        try {
            if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
                $author = Author::query()->find((int) $reference);
            } elseif (is_string($reference) && trim($reference) !== '') {
                $author = Author::query()->whereTranslationLikeAny('slug', trim($reference))->first();
            }
        } catch (Throwable) {
            $author = null;
        }

        if (! $author instanceof Author) {
            throw new ToolFailure('No author profile '.json_encode($reference).'. authors_list says which there are.');
        }

        return $author;
    }
}
