<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Widgets\Captcha;

/**
 * The checkout is a form that writes to the database and sends letters (spec §28): what costs the
 * buyer nothing guards it — a field nobody sees, a mark of when the page was drawn, the origin.
 * The limits are the routes' (`throttle:webx-orders-checkout`), the captcha is the site's.
 *
 * The page is never cached (`no-store`), so unlike the inbox's forms the mark is always the
 * moment the buyer opened it: one too fresh, missing or unreadable is refused. «Opened» is the
 * first drawing of the checkout in the session, not of the column: the script draws the column
 * again after every step and choice, and a buyer who picks the payment and presses «Place the
 * order» at once is not a bot.
 */
final readonly class CheckoutAntispam
{
    /** The field a person never sees: filled — a bot, answered as if it worked. */
    public const string HONEYPOT = 'webx_hp';

    /** When the confirmation was drawn, encrypted. */
    public const string TIMESTAMP = 'webx_ts';

    /** The session's first drawing of a confirmation, as a Unix time. */
    public const string DRAWN = 'webx-orders.checkout.drawn';

    /** The v3 actions of the two forms: a token asked for one is no answer for the other. */
    public const string PLACE_ACTION = 'webx_checkout';

    public const string QUICK_ACTION = 'webx_checkout_quick';

    public function __construct(
        private Config $config,
        private Encrypter $encrypter,
        private CheckoutSettings $settings,
        private Captcha $captcha,
        private Session $session,
    ) {}

    /** Whether the forms draw the site's captcha (`orders.checkout.captcha`). */
    public function wantsCaptcha(): bool
    {
        return $this->settings->captcha();
    }

    /**
     * The site's captcha, when the shop turned it on (§28): refused — the words under the widget,
     * `captcha`, as the form engine and the page without JavaScript put them. A refusal the visitor
     * can do nothing about — no keys, a v3 score — is «Try again», like the rest of the antispam.
     *
     * @throws ValidationException
     */
    public function checkCaptcha(Request $request, string $action): void
    {
        if (! $this->wantsCaptcha()) {
            return;
        }

        $refusal = $this->captcha->check($request, $action, form: $action);

        if ($refusal !== null) {
            throw ValidationException::withMessages([
                'captcha' => [$this->captcha->message($refusal) ?? (string) __('webx-orders::checkout.errors.retry')],
            ]);
        }
    }

    public function trapped(Request $request): bool
    {
        return trim((string) $request->input(self::HONEYPOT, '')) !== '';
    }

    /**
     * The value of the mark of a confirmation drawn now: when this session first drew one — a
     * column drawn again does not start the clock again.
     */
    public function timestamp(): string
    {
        $now = Carbon::now()->getTimestamp();
        $drawn = $this->session->get(self::DRAWN);

        if (! is_int($drawn) || $drawn > $now) {
            $this->session->put(self::DRAWN, $drawn = $now);
        }

        return $this->encrypter->encrypt((string) $drawn);
    }

    /** Sooner than `min_seconds` after the page was drawn, or no mark of one at all. */
    public function tooFast(Request $request): bool
    {
        $minimum = (int) $this->config->get('webx-orders.checkout.min_seconds', 3);

        if ($minimum <= 0) {
            return false;
        }

        $mark = $request->input(self::TIMESTAMP);

        if (! is_string($mark) || $mark === '') {
            return true;
        }

        try {
            $drawn = (int) $this->encrypter->decrypt($mark);
        } catch (Throwable) {
            return true;
        }

        return Carbon::now()->getTimestamp() - $drawn < $minimum;
    }

    /**
     * A POST from somebody else's page is not the buyer pressing the button. Neither `Origin` nor
     * `Referer` — mostly a script, which the mark and the limits answer.
     */
    public function foreign(Request $request): bool
    {
        $claimed = $request->headers->get('origin') ?: $request->headers->get('referer');

        if (! is_string($claimed) || $claimed === '') {
            return false;
        }

        $host = parse_url($claimed, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return true;
        }

        $hosts = [strtolower($request->getHost())];
        $own = parse_url((string) $this->config->get('app.url', ''), PHP_URL_HOST);

        if (is_string($own) && $own !== '') {
            $hosts[] = strtolower($own);
        }

        return ! in_array(strtolower($host), $hosts, true);
    }
}
