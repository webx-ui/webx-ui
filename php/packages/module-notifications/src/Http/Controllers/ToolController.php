<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Contracts\FindsRecipients;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Providers;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\FoundRecipient;
use WebxUi\Notifications\Sending\Renderer;

/**
 * The form's buttons (§10.3, §10.5): send a test, preview, check the settings, find chats — on the
 * saved channel or on the values of a form not saved yet, which are never written. And `meta`:
 * what providers and events the site has.
 */
final class ToolController
{
    public function __construct(
        private readonly ChannelForm $form,
        private readonly Providers $providers,
        private readonly NotificationEvents $events,
        private readonly Notifications $notifications,
        private readonly Renderer $renderer,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    /** Providers with their settings and abilities, events with their variables. */
    public function meta(): JsonResponse
    {
        return ApiResponse::data([
            'providers' => array_values(array_map(static fn (Provider $provider): array => [
                'key' => $provider->key(),
                'label' => (string) __($provider->label()),
                'icon' => $provider->icon(),
                'fields' => array_map(static fn ($field): array => $field->toArray(), $provider->fields()),
                'subject' => $provider->hasSubject(),
                'address' => $provider->address(),
                'limit' => $provider->limit(),
                'find_recipients' => $provider instanceof FindsRecipients,
            ], $this->providers->all())),
            'events' => array_values(array_map(static fn (EventDefinition $event): array => $event->toArray(), $this->events->all())),
            'environment' => app()->environment(),
            'mode' => $this->notifications->mode(),
        ]);
    }

    /**
     * `{ values?, recipient? }` — now, past the queue, with the event's example values (§8.5).
     * Ten a minute for one administrator: the button writes to real people.
     */
    public function test(Request $request, ?Channel $channel = null): JsonResponse
    {
        $key = 'webx-notifications:test:'.($request->user()?->getAuthIdentifier() ?? $request->ip());
        $limit = max(1, (int) $this->config->get('webx-notifications.tests_per_minute', 10));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return ApiResponse::message((string) __('webx-notifications::errors.tests-too-many', ['seconds' => RateLimiter::availableIn($key)]), 429);
        }

        RateLimiter::hit($key, 60);

        $subject = $this->subject($request, $channel);
        $recipient = $request->input('recipient');

        return ApiResponse::data([
            'results' => $this->notifications->test($subject, is_string($recipient) ? $recipient : null),
        ]);
    }

    /** `{ values?, locale? }` — the message on the example values, sent nowhere. */
    public function preview(Request $request, ?Channel $channel = null): JsonResponse
    {
        $subject = $this->subject($request, $channel);
        $provider = $this->providers->find($subject->provider);
        $event = $this->notifications->eventOf($subject);

        if ($provider === null) {
            throw ValidationException::withMessages(['provider' => (string) __('webx-notifications::errors.provider', ['provider' => $subject->provider, 'providers' => implode(', ', $this->providers->keys())])]);
        }

        $locale = $request->input('locale');
        $rendered = $this->renderer->render($subject, $provider, $event?->examples() ?? [], is_string($locale) ? $locale : null, $event);

        return ApiResponse::data([
            'subject' => $rendered['message']->subject,
            'body' => $rendered['message']->body,
            'plain' => $rendered['message']->plain,
            'truncated' => $rendered['message']->truncated,
            'unknown_variables' => $rendered['unknown'],
        ]);
    }

    /** `{ values?, channel? }` — the settings tried without sending: Telegram's getMe, an SMTP handshake. */
    public function check(Request $request): JsonResponse
    {
        $channel = $this->saved($request);
        $subject = $this->subject($request, $channel);
        $provider = $this->providers->find($subject->provider);

        if ($provider === null) {
            throw ValidationException::withMessages(['provider' => (string) __('webx-notifications::errors.provider', ['provider' => $subject->provider, 'providers' => implode(', ', $this->providers->keys())])]);
        }

        $result = $provider->check($subject->secrets(), $subject->options());

        if ($result->ok && $channel !== null && isset($result->details['bot']) && ! $this->typedSecret($request, $provider)) {
            $this->cache->put(ChannelForm::botKey($channel), $result->details['bot'], 3600);
        }

        return ApiResponse::data($result->toArray());
    }

    /**
     * `{ provider, token? | channel? }` — "Find chats" (§6.3): with the saved token or one typed
     * into the form, used for this request and written nowhere.
     */
    public function findRecipients(Request $request): JsonResponse
    {
        $channel = $this->saved($request);
        $input = $this->input($request);

        if ($channel === null && ! isset($input['provider']) && is_string($request->input('provider'))) {
            $input['provider'] = $request->input('provider');
        }

        if (is_string($request->input('token')) && $request->input('token') !== '') {
            $input['token'] = $request->input('token');
        }

        $subject = $this->form->unsaved($input, $channel);
        $provider = $this->providers->find($subject->provider);

        if (! $provider instanceof FindsRecipients) {
            throw ValidationException::withMessages(['provider' => (string) __('webx-notifications::errors.cannot-find')]);
        }

        try {
            $found = $provider->find($subject->secrets());
        } catch (DeliveryFailed $failed) {
            return ApiResponse::message($subject->secrets()->mask($failed->getMessage()), 422);
        }

        return ApiResponse::data([
            'recipients' => array_map(static fn (FoundRecipient $one): array => $one->toArray(), $found),
            'hint' => $found === [] ? (string) __('webx-notifications::errors.telegram.nothing-found') : null,
        ]);
    }

    /**
     * The administrators an e-mail channel can write to (§6.2), switched on, by name — what the
     * form's «Add an administrator» offers. Not filtered by a permission: a notification is a
     * letter, not a link into the panel somebody would need a right to open.
     */
    public function administrators(): JsonResponse
    {
        return ApiResponse::data(CmsUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (CmsUser $admin): array => [
                'id' => (int) $admin->getKey(),
                'name' => (string) $admin->name,
                'email' => (string) $admin->email,
            ])
            ->values()
            ->all());
    }

    /** The channel the request is about, as the form has it now: saved values under typed ones. */
    private function subject(Request $request, ?Channel $channel): Channel
    {
        $input = $this->input($request);

        if ($input === [] && $channel !== null) {
            return $channel;
        }

        return $this->form->unsaved($input, $channel);
    }

    private function saved(Request $request): ?Channel
    {
        $id = $request->input('channel');

        return is_numeric($id) ? Channel::query()->findOrFail((int) $id) : null;
    }

    /** Whether the form brought a secret of its own — then what the check learned is not the saved channel's. */
    private function typedSecret(Request $request, Provider $provider): bool
    {
        $input = $this->input($request);
        $credentials = is_array($input['credentials'] ?? null) ? $input['credentials'] : [];

        foreach ($provider->fields() as $field) {
            $typed = $input[$field->name] ?? $credentials[$field->name] ?? null;

            if ($field->secret && is_string($typed) && $typed !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }
}
