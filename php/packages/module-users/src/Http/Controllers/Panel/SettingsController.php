<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\Panel\SettingsState;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProviders;

/**
 * `/api/cms/users/settings` (spec §16.3), behind `users.settings`.
 *
 * Two halves. The registration and how much the forms ask for are the site's settings
 * (`users.registration`, `users.fields.<name>`), described by the screen `users.settings` and
 * stored where every setting is — there is nothing to store them in on a site without
 * `module-settings`, and the screen says so. The keys of the providers are `users_providers`:
 * a card per provider of the registry, a secret answered only with «set» and replaced or
 * forgotten, never read back — not here, not in the journal.
 */
final readonly class SettingsController
{
    public function __construct(
        private SettingsState $state,
        private SocialProviders $providers,
        private ProviderKeys $keys,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::data($this->state->answer());
    }

    public function update(Request $request): JsonResponse
    {
        if ($this->state->settings() === null) {
            abort(404);
        }

        $input = $request->input('values');
        $user = $request->user();

        $this->state->save($this->state->validate(
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        ));

        return ApiResponse::data($this->state->answer());
    }

    /**
     * One provider's card saved: on or off, the open fields, a secret only when a new one was
     * typed, a secret forgotten when asked. A field left empty falls back on `.env` (§9.3).
     */
    public function provider(Request $request, string $key): JsonResponse
    {
        $provider = $this->providers->find($key) ?? abort(404);

        $row = ProviderSettings::query()->find($key) ?? new ProviderSettings(['key' => $key]);
        $options = $row->options ?? [];
        $credentials = $row->credentials ?? [];
        $errors = [];

        $sent = $request->input('options');
        $secrets = $request->input('credentials');
        $forget = array_map('strval', (array) $request->input('forget', []));

        foreach ($provider->fields() as $field) {
            if ($field->secret) {
                $value = is_array($secrets) ? ($secrets[$field->name] ?? null) : null;

                if (in_array($field->name, $forget, true)) {
                    unset($credentials[$field->name]);
                } elseif (is_string($value) && trim($value) !== '') {
                    if (mb_strlen($value) > $field->max) {
                        $errors['credentials.'.$field->name] = [(string) trans('validation.max.string', ['attribute' => (string) __($field->label), 'max' => $field->max])];
                    }

                    $credentials[$field->name] = trim($value);
                }

                continue;
            }

            if (! is_array($sent) || ! array_key_exists($field->name, $sent)) {
                continue;
            }

            $value = $sent[$field->name];
            $value = is_scalar($value) ? trim((string) $value) : null;

            if ($value !== null && mb_strlen($value) > $field->max) {
                $errors['options.'.$field->name] = [(string) trans('validation.max.string', ['attribute' => (string) __($field->label), 'max' => $field->max])];
            }

            if ($value === null || $value === '') {
                unset($options[$field->name]);
            } else {
                $options[$field->name] = $value;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($request->has('enabled')) {
            $row->enabled = $request->boolean('enabled');
        }

        $row->options = $options === [] ? null : $options;
        $row->credentials = $credentials === [] ? null : $credentials;
        $row->save();

        $this->keys->forget();

        return ApiResponse::data($this->state->answer());
    }
}
