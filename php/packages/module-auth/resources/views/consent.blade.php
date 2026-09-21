{{--
    The consent screen: a person has come here from somebody else's application, so there is
    no panel around it — the site's logo, the question, the answer, nothing to navigate.

    The client's name and logo are its own choice, made at registration; the address the
    answer goes to is the part it cannot choose, so that is shown twice.
--}}
@php
    /** @var \Laravel\Passport\Client $client */
    /** @var \WebxUi\Auth\Models\CmsUser $user */
    /** @var \WebxUi\Admin\Manifest\Branding $brand */
    /** @var list<array{module: string, level: 'view'|'edit'}>|null $rights */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('webx-auth::consent.page-title', ['client' => $client->name]) }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --ink: #18181b; --muted: #71717a; --line: #e4e4e7; --paper: #fff; --back: #f4f4f5;
            --accent: #2563eb; --accent-ink: #fff; --warn-back: #fffbeb; --warn-line: #fcd34d; --warn-ink: #78350f;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #fafafa; --muted: #a1a1aa; --line: #3f3f46; --paper: #18181b; --back: #09090b;
                --warn-back: #292524; --warn-line: #a16207; --warn-ink: #fde68a;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: start center; padding: 24px 16px 48px;
            background: var(--back); color: var(--ink);
            font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        main { width: 100%; max-width: 480px; }
        .brand { display: flex; justify-content: center; margin: 16px 0 24px; }
        .brand img { max-width: 200px; max-height: 56px; }
        .brand span { font-size: 20px; font-weight: 600; }
        .card { padding: 28px 24px; border: 1px solid var(--line); border-radius: 12px; background: var(--paper); }
        h1 { margin: 0; font-size: 20px; line-height: 1.3; }
        h1 small { font-weight: 400; color: var(--muted); font-size: 15px; }
        .asks { margin: 4px 0 20px; color: var(--muted); }
        .who { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: baseline; margin: 0 0 20px; padding: 12px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .who .email { color: var(--muted); }
        .who form { margin-left: auto; }
        .link { padding: 0; border: 0; background: none; color: var(--accent); font: inherit; cursor: pointer; text-decoration: underline; }
        .rights { margin: 0 0 20px; }
        .rights p { margin: 0 0 8px; }
        .rights ul { margin: 0; padding-left: 20px; }
        .rights li { margin: 2px 0; }
        .warn { margin: 0 0 20px; padding: 12px 14px; border: 1px solid var(--warn-line); border-radius: 8px; background: var(--warn-back); color: var(--warn-ink); }
        .warn p { margin: 0; }
        .warn p + p { margin-top: 10px; }
        .read-only { display: flex; gap: 10px; align-items: flex-start; margin: 0 0 24px; cursor: pointer; }
        .read-only input { margin: 4px 0 0; width: 18px; height: 18px; flex: none; }
        .buttons { display: flex; gap: 12px; }
        form.inline { display: contents; }
        button.action { padding: 10px 20px; border-radius: 8px; border: 1px solid var(--line); background: transparent; color: inherit; font: inherit; cursor: pointer; }
        button.approve { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }
        .foot { margin: 20px 0 0; color: var(--muted); font-size: 13px; text-align: center; }
        .foot p { margin: 4px 0; }
    </style>
</head>
<body>
    <main>
        <div class="brand">
            @if ($brand->logo !== null)
                <img src="{{ $brand->logo->url }}" alt="{{ $brand->title }}">
            @else
                <span>{{ $brand->title }}</span>
            @endif
        </div>

        <div class="card">
            <h1>{{ $client->name }} <small>({{ $returnTo }})</small></h1>
            <p class="asks">{{ __('webx-auth::consent.asks', ['site' => $site]) }}</p>

            <div class="who">
                <span>{{ __('webx-auth::consent.signed-in-as', ['name' => $user->name]) }}</span>
                <span class="email">{{ $user->email }}</span>
                <form method="post" action="{{ route('webx.auth.consent.switch') }}">
                    @csrf
                    <input type="hidden" name="next" value="{{ $here }}">
                    <button type="submit" class="link">{{ __('webx-auth::consent.switch') }}</button>
                </form>
            </div>

            <div class="rights">
                @if ($rights === null)
                    <p>{{ __('webx-auth::consent.everything') }}</p>
                @elseif ($rights === [])
                    <p>{{ __('webx-auth::consent.nothing') }}</p>
                @else
                    <p>{{ __('webx-auth::consent.can-do') }}</p>
                    <ul>
                        @foreach ($rights as $right)
                            <li>{{ __('webx-auth::consent.'.$right['level'], ['module' => $right['module']]) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="warn">
                <p>&#9888; {{ __('webx-auth::consent.warning') }}</p>
                <p>{{ __('webx-auth::consent.liability') }}</p>
            </div>

            <form method="post" action="{{ route('webx.auth.consent.approve') }}" id="consent-approve">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <label class="read-only">
                    <input type="checkbox" name="read_only" value="1">
                    <span>{{ __('webx-auth::consent.read-only') }}</span>
                </label>
            </form>

            <div class="buttons">
                <button type="submit" form="consent-approve" class="action approve">{{ __('webx-auth::consent.allow') }}</button>

                <form method="post" action="{{ route('passport.authorizations.deny') }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="action">{{ __('webx-auth::consent.cancel') }}</button>
                </form>
            </div>
        </div>

        <div class="foot">
            <p>{{ __('webx-auth::consent.disconnect') }}</p>
            <p>{{ __('webx-auth::consent.returns-to', ['host' => $returnTo]) }}</p>
        </div>
    </main>
</body>
</html>
