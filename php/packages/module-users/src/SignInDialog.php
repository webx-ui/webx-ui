<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Users\Fields\FieldValues;
use WebxUi\Users\Http\Controllers\ForgotController;
use WebxUi\Users\Http\Controllers\RegisterController;
use WebxUi\Users\Http\Controllers\SignInController;
use WebxUi\Users\Http\ScriptController;
use WebxUi\Users\Models\User;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * The dialog of the sign-in for a checkout (spec §12.1): the sign-in, «Forgot your password?» and
 * the registration in one `<x-webx-dialog>`, switched without leaving the page.
 *
 * Printed once a page, before `</body>`, when the page has an opener — `data-webx-sign-in` on a
 * link or a button, or a link to `#webx-sign-in` — as the forms of the widgets in a dialog are
 * (WIDGETS §6.4): a link in a menu or a block opens it without a template. `<x-webx-users::sign-in-dialog />`
 * prints it where a template wants it; the page then gets no second one.
 *
 * Nothing personal in it: the page around it may be cached for everybody (§11.6). A form sent
 * from it says so (`webx_dialog`); a signed-in answer to such a form sent in place is
 * {@see self::answer()} — the script closes the dialog, says `webx:users:signed-in` and reloads
 * the page unless a listener took it over. Sent without JavaScript, the form is a plain POST and
 * the visitor goes back to the page.
 */
final class SignInDialog
{
    public const string ID = 'webx-sign-in';

    /** The hidden input of the dialog's forms. */
    public const string INPUT = 'webx_dialog';

    /** The ids of the dialog's forms, by view: a page that came back from one opens on it. */
    public const array FORMS = [
        'sign-in' => 'webx-sign-in-form',
        'forgot' => 'webx-sign-in-forgot',
        'register' => 'webx-sign-in-register',
    ];

    private const string OPENER = '~<(?:a|button)\b[^>]*>~i';

    private const string ATTRIBUTE = '~\sdata-webx-sign-in(?:=|[\s>/])~i';

    private const string HREF = '~\shref=(["\'])#'.self::ID.'\1~i';

    private bool $printed = false;

    public function __construct(
        private readonly Views $views,
        private readonly Urls $urls,
    ) {}

    /** Whether the page has a link or a button that opens the dialog. */
    public static function opened(string $html): bool
    {
        preg_match_all(self::OPENER, $html, $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match(self::ATTRIBUTE, $tag) === 1 || preg_match(self::HREF, $tag) === 1) {
                return true;
            }
        }

        return false;
    }

    /** The page with the dialog before `</body>`, when it has an opener and no dialog yet. */
    public function into(string $html): string
    {
        if ($this->printed || str_contains($html, 'id="'.self::ID.'"') || ! self::opened($html)) {
            return $html;
        }

        $body = strripos($html, '</body>');

        if ($body === false) {
            return $html;
        }

        return substr($html, 0, $body).$this->render()."\n".substr($html, $body);
    }

    /** The dialog, once a page: a second call prints nothing. */
    public function render(): string
    {
        if ($this->printed) {
            return '';
        }

        $this->printed = true;
        $request = request();
        $back = $this->urls->local($request->getRequestUri());
        $register = SignIn::registrationOpen();

        return $this->views->make('webx-users::sign-in-dialog', [
            'id' => self::ID,
            'input' => self::INPUT,
            'forms' => self::FORMS,
            'view' => self::returned($request, $register),
            'back' => $back,
            'signIn' => [
                'action' => $this->urls->to('sign-in'),
                'page' => $this->urls->to('sign-in', $back),
                ...SignInController::form(),
            ],
            'forgot' => [
                'action' => $this->urls->to('forgot'),
                'page' => $this->urls->to('forgot', $back),
                ...ForgotController::form(),
            ],
            'register' => $register ? [
                'action' => $this->urls->to('register'),
                'page' => $this->urls->to('register', $back),
                ...RegisterController::form(app(FieldValues::class)),
            ] : null,
            'script' => route('webx-users.script.sign-in', ['v' => ScriptController::version('sign-in')]),
        ])->render();
    }

    /**
     * What a form of the dialog answers a visitor who signed in by it in place: what the page's
     * script says to whoever listens (`webx:users:signed-in`) — the name and whether the address
     * is confirmed —, and no redirect, so the script decides. Null for any other request: the
     * page of the sign-in, the dialog without JavaScript — they go back by a redirect.
     */
    public static function answer(Request $request, User $user): ?JsonResponse
    {
        if (! $request->boolean(self::INPUT) || ! $request->expectsJson()) {
            return null;
        }

        $visitor = $user->toVisitor();

        return new JsonResponse([
            ...FormResponse::ok()->toArray(),
            'visitor' => ['name' => $visitor->name(), 'verified' => $visitor->verified],
        ]);
    }

    /** Forget the dialog: the next request of a long-lived worker prints it again. */
    public function flush(): void
    {
        $this->printed = false;
    }

    /** The view a page sent back from one of the dialog's forms opens on; the sign-in otherwise. */
    private static function returned(Request $request, bool $register): string
    {
        if (! $request->hasSession()) {
            return 'sign-in';
        }

        $session = $request->session();
        $flash = $session->get(FormResponse::FLASH);
        $form = is_array($flash) && is_string($flash['form'] ?? null) ? $flash['form'] : $session->getOldInput(FormResponse::FORM);
        $view = is_string($form) ? array_search($form, self::FORMS, true) : false;

        return is_string($view) && ($view !== 'register' || $register) ? $view : 'sign-in';
    }
}
