<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Mail\MailRoutes;

/**
 * Where the site's letters go, as the application sees it right now — and whether that is what
 * `.env` says ({@see MailRoutes}).
 *
 * A trap — the `log` mailer, a test sandbox — is what a staging site wants, so there it is a note;
 * on production it is a warning, because there it is nobody's plan. A config cached before `.env`
 * was edited is a warning everywhere: the site sends by settings nobody can see any more.
 */
final class Mail implements Check
{
    private const string SUBJECT = 'Mail';

    public function __construct(
        private readonly MailRoutes $routes,
        private readonly Application $app,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $route = $this->routes->site();
        $found = [];

        if ($route->isTrap()) {
            $detail = "the site's mail goes through {$route->mailer} ({$route->label()}), which delivers to nobody — "
                .'letters to the panel\'s people and the forms\' notifications are taken and never arrive. '
                .'Set MAIL_MAILER and MAIL_HOST to a real server, or add it to `webx-admin.mail.traps` if this is not a trap.';

            $found[] = $this->app->environment('production')
                ? Diagnosis::warn(self::SUBJECT, $detail)
                : Diagnosis::note(self::SUBJECT, $detail);
        }

        if ($this->routes->staleConfig()) {
            $found[] = Diagnosis::warn(
                self::SUBJECT,
                '.env was changed after the config was cached, so its settings are not in effect — mail included. '
                .'Rebuild the cache: `php artisan webx:boot` (or `php artisan config:cache`).',
            );
        }

        return $found === [] ? [Diagnosis::ok(self::SUBJECT, $route->label())] : $found;
    }
}
