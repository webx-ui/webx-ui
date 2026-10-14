<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;
use WebxUi\Blog\Panel\AuthorForm;
use WebxUi\Blog\Panel\AuthorRow;
use WebxUi\Localization\Locales;

/**
 * The profiles of the authors, for the section «Authors» (§19.5).
 *
 * The list is short — the people who write one site — so it is one page, searched by name. The
 * accounts a new profile can be made for are their own answer: an account belongs to one profile
 * at most, and the select must not offer one that would be refused.
 */
final class AuthorController
{
    public function __construct(
        private readonly AuthorForm $form,
        private readonly Locales $locales,
    ) {
        abort_unless(Author::enabled(), 404);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Author::query()->with(['user', 'photo'])->withCount('articles');
        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhereHas('user', static fn (Builder $user): Builder => $user->where('name', 'like', $like));
            });
        }

        return new JsonResponse([
            'data' => $query->orderBy('id')->get()
                ->map(fn (Author $author): array => AuthorRow::of($author, $this->locales))
                ->values()
                ->all(),
        ]);
    }

    /**
     * The accounts without a profile — who a new one can be made for. The ones who wrote
     * something first: they are why the section was opened.
     */
    public function accounts(): JsonResponse
    {
        $accounts = CmsUser::query()
            ->whereNotIn('id', Author::query()->select('user_id'))
            // Counted here rather than through a relation: the account is `module-auth`'s and knows
            // nothing about the blog.
            ->select('cms_users.*')
            ->selectSub(Article::query()->selectRaw('count(*)')->whereColumn('author_id', 'cms_users.id'), 'articles_count')
            ->orderByDesc('articles_count')
            ->orderBy('name')
            ->get();

        return new JsonResponse([
            'data' => $accounts->map(static fn (CmsUser $user): array => [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'articles_count' => (int) $user->getAttribute('articles_count'),
            ])->values()->all(),
        ]);
    }

    public function show(Author $author): JsonResponse
    {
        return ApiResponse::data($this->form->describe($author));
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');

        $author = $this->form->save(
            new Author,
            $this->values($request),
            is_numeric($userId) ? (int) $userId : null,
            $this->can($request),
        );

        return ApiResponse::data($this->form->describe($author), 201);
    }

    public function update(Request $request, Author $author): JsonResponse
    {
        $author = $this->form->save($author, $this->values($request), can: $this->can($request));

        return ApiResponse::data($this->form->describe($author));
    }

    /**
     * The profile goes; the articles stay the account's, and their byline goes back to its name.
     */
    public function destroy(Author $author): JsonResponse
    {
        $author->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
