<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Doctor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\QueueBacklog;
use WebxUi\Notifications\Contracts\HasMailRoute;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Providers;

/**
 * The notifications in the command a deploy already runs (§13): how they leave without a
 * worker, rows nobody took off the queue, what failed lately, and channels that cannot send —
 * secrets written with another key, a provider or an event that went away, a letter through a
 * mailer that writes to the log.
 */
final class NotificationsCheck implements Check
{
    private const string SUBJECT = 'Notifications';

    public function __construct(
        private readonly Notifications $notifications,
        private readonly Providers $providers,
        private readonly NotificationEvents $events,
        private readonly Application $app,
    ) {}

    public function run(): array
    {
        $found = [];

        if ($this->notifications->mode() === 'after_response') {
            $found[] = Diagnosis::ok(self::SUBJECT, 'sent after the response, with three quick attempts and no retries later: run a queue worker for retries in the background.');
        }

        $stuck = Delivery::query()
            ->where('status', Delivery::QUEUED)
            ->where('is_test', false)
            ->where('created_at', '<', Carbon::now()->subMinutes(Delivery::STUCK_MINUTES));
        $count = $stuck->count();

        if ($count > 0) {
            $found[] = Diagnosis::warn(self::SUBJECT, sprintf(
                '%d %s for a queue worker since %s — is `queue:work` running? %s.',
                $count,
                $count === 1 ? 'notification waits' : 'notifications wait',
                (string) $stuck->min('created_at'),
                ucfirst(QueueBacklog::advice()),
            ));
        }

        $failed = Delivery::query()->where('status', Delivery::FAILED)->where('is_test', false)->where('created_at', '>=', Carbon::now()->subDay())->count();

        if ($failed > 0) {
            $found[] = Diagnosis::content(self::SUBJECT, sprintf(
                '%d %s not sent in the last day — the log is in the section "Notifications".',
                $failed,
                $failed === 1 ? 'notification was' : 'notifications were',
            ));
        }

        foreach (Channel::query()->where('enabled', true)->orderBy('id')->get() as $channel) {
            $found = [...$found, ...$this->channel($channel)];
        }

        return $found === [] ? [Diagnosis::ok(self::SUBJECT, 'every enabled channel can send.')] : $found;
    }

    /**
     * @return list<Diagnosis>
     */
    private function channel(Channel $channel): array
    {
        $name = "channel \"{$channel->name}\" (#{$channel->id})";
        $found = [];

        if (! $this->providers->has($channel->provider)) {
            $found[] = Diagnosis::warn(self::SUBJECT, "{$name} is of the provider \"{$channel->provider}\", which nothing registers any more — it sends nothing.");
        }

        if ($channel->event !== null && ! $this->events->has($channel->event)) {
            $found[] = Diagnosis::warn(self::SUBJECT, "{$name} is for the event \"{$channel->event}\", which nothing declares any more — the module that sent it is gone.");
        }

        if (! $channel->readable()) {
            $found[] = Diagnosis::warn(self::SUBJECT, "{$name}: its secrets do not decrypt — the application key changed. Enter them again, or put the old key into APP_PREVIOUS_KEYS.");
        }

        $provider = $this->providers->find($channel->provider);
        $route = $provider instanceof HasMailRoute ? $provider->route($channel->options()) : null;

        // A trap is what a staging site wants, and nobody's plan on production — the same line
        // the site's own mail is held to (`Mail` of the frame's doctor).
        if ($route !== null && $route->isTrap()) {
            $detail = $route->mailer === 'channel'
                ? "{$name} sends through its own server {$route->label()}, a test mailbox: the letters reach nobody outside it. Give it the real server."
                : "{$name} sends through the site's mail, and its mailer is \"{$route->label()}\": the letters go nowhere. Set MAIL_MAILER, or give the channel its own SMTP server.";

            $found[] = $this->app->environment('production')
                ? Diagnosis::warn(self::SUBJECT, $detail)
                : Diagnosis::note(self::SUBJECT, $detail);
        }

        return $found;
    }
}
