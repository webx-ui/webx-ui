<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Visitors\AnswersUnverified;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Users\Http\Controllers\VerifyController;
use WebxUi\Users\Http\KeepPrivate;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\UserList;

/**
 * The seam of the panel (spec §4) answered by this module: the visitor signed in on the guard
 * `site`, looked up by id or by exact address, and where a guest goes to sign in.
 *
 * Nothing is remembered between calls: the guard already holds the visitor of the request, and
 * an instance shared by a long-running worker must not carry one request's visitor to the next.
 */
final readonly class UserVisitors implements AnswersUnverified, Visitors
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
        private Views $views,
    ) {}

    public function current(): ?Visitor
    {
        $user = $this->auth->guard(Urls::guard())->user();

        return $user instanceof User ? $user->toVisitor() : null;
    }

    public function find(int $id): ?Visitor
    {
        return User::query()->find($id)?->toVisitor();
    }

    public function findByEmail(string $email): ?Visitor
    {
        $email = User::normaliseEmail($email);

        return $email === '' ? null : User::query()->byEmail($email)->first()?->toVisitor();
    }

    /**
     * Visitors by a part of the address, the name or the phone — what «Customers» of the panel
     * finds — for a module that lets a manager pick one: a guest's order linked to an account.
     * Unlike {@see findByEmail()} it is a search, and so a tool of the panel, never of a page of
     * the site. The exact address comes first, then the newest accounts.
     *
     * @return list<Visitor>
     */
    public function search(string $term, int $limit = 10): array
    {
        $term = trim($term);

        if ($term === '' || $limit < 1) {
            return [];
        }

        $query = User::query()->with('groups:users_groups.id,users_groups.code');
        UserList::match($query, $term);

        return $query
            ->orderByRaw('case when email = ? then 0 else 1 end', [User::normaliseEmail($term)])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (User $user): Visitor => $user->toVisitor())
            ->values()
            ->all();
    }

    public function signInUrl(?string $back = null): string
    {
        return $this->urls->to('sign-in', $back);
    }

    /**
     * A page behind `webx.visitor:verified` to a visitor who has not confirmed (spec §8.4):
     * «Confirm your e-mail» and the letter once more, with 403 — the page is not what was asked.
     */
    public function unverifiedResponse(Request $request): Response
    {
        $user = $this->auth->guard(Urls::guard())->user();
        $request->attributes->set(KeepPrivate::ATTRIBUTE, true);
        $view = VerifyController::notice($this->views, $this->urls, $user instanceof User ? $user : null, 'unverified');

        return new HttpResponse($view->render(), 403, ['Cache-Control' => 'no-store, private']);
    }
}
