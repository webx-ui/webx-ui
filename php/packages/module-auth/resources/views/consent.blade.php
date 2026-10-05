{{--
    The consent screen: a person has come here from somebody else's application, so there is
    no panel around it — the site's logo, the question, the answer, nothing to navigate.

    It still looks like the panel: the panel's tokens, inlined, and the shapes of its card,
    alert, checkbox and buttons drawn again in a few lines of CSS. Loading the panel's own
    bundle for that would start the panel.

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
    {{-- The theme the panel last used in this browser: same origin, same key as the shell. --}}
    <script>
        try {
            var webxTheme = localStorage.getItem('webx.theme');

            if (webxTheme === 'light' || webxTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', webxTheme);
            }
        } catch (error) {
            /* Private windows, blocked site data: the system's preference decides. */
        }
    </script>
    <style>{!! \WebxUi\Admin\Support\Tokens::css() !!}</style>
    <style>
        :root { color-scheme: light dark; }
        :root[data-theme='light'] { color-scheme: light; }
        :root[data-theme='dark'] { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: start center;
            padding: var(--wx-space-24) var(--wx-space-16) var(--wx-space-48);
            background: var(--wx-bg-body); color: var(--wx-text-default);
            font-family: var(--wx-font-family-sans); font-size: var(--wx-font-size-sm);
            line-height: var(--wx-font-line-height-normal);
            -webkit-font-smoothing: antialiased;
        }
        main { width: 100%; max-width: 30rem; }

        .brand { display: flex; justify-content: center; margin: var(--wx-space-16) 0 var(--wx-space-24); }
        .brand img { max-width: 200px; max-height: 56px; }
        .brand span { color: var(--wx-text-strong); font-size: var(--wx-font-size-xl); font-weight: var(--wx-font-weight-semibold); }

        .card {
            display: flex; flex-direction: column; gap: var(--wx-space-16);
            padding: var(--wx-space-24); border-radius: var(--wx-radius-md);
            background: var(--wx-bg-surface); box-shadow: var(--wx-shadow-card);
        }
        .card p { margin: 0; }

        h1 {
            margin: 0; color: var(--wx-text-strong); font-size: var(--wx-font-size-xl);
            font-weight: var(--wx-font-weight-semibold); line-height: var(--wx-font-line-height-tight);
            overflow-wrap: anywhere;
        }
        .asks { margin-top: var(--wx-space-6) !important; color: var(--wx-text-muted); }
        .return-to {
            display: inline-block; margin-top: var(--wx-space-8); padding: var(--wx-space-2) var(--wx-space-8);
            border-radius: var(--wx-radius-xs); background: var(--wx-bg-muted); color: var(--wx-text-default);
            font-family: var(--wx-font-family-mono); font-size: var(--wx-font-size-xs); overflow-wrap: anywhere;
        }

        .who {
            display: flex; flex-wrap: wrap; align-items: center; gap: var(--wx-space-4) var(--wx-space-12);
            padding: var(--wx-space-12) var(--wx-space-14); border-radius: var(--wx-radius-sm); background: var(--wx-bg-subtle);
        }
        .who__person { display: flex; flex-direction: column; min-width: 0; }
        .who__name { color: var(--wx-text-strong); font-weight: var(--wx-font-weight-medium); }
        .who__email { color: var(--wx-text-muted); overflow-wrap: anywhere; }
        .who form { margin-left: auto; }
        .link {
            padding: 0; border: 0; border-radius: var(--wx-radius-xs); background: none;
            color: var(--wx-text-link); font: inherit; font-weight: var(--wx-font-weight-medium); cursor: pointer;
        }
        .link:hover { text-decoration: underline; }
        .link:focus-visible { outline: none; box-shadow: var(--wx-ring-focus); }

        .rights ul { margin: var(--wx-space-6) 0 0; padding-left: var(--wx-space-18); }
        .rights li { margin: var(--wx-space-2) 0; }

        .alert {
            display: flex; align-items: flex-start; gap: var(--wx-space-10);
            padding: var(--wx-space-12) var(--wx-space-14); border-radius: var(--wx-radius-sm);
            background: var(--wx-color-warning-soft);
        }
        .alert__icon { flex: none; width: 1.25em; height: 1.25em; padding-top: 0.1em; color: var(--wx-color-warning-active); }
        .alert__body p + p { margin-top: var(--wx-space-8); color: var(--wx-text-muted); }

        .check { display: inline-flex; align-items: center; gap: var(--wx-space-8); color: var(--wx-text-default); font-size: var(--wx-font-size-control-md); cursor: pointer; user-select: none; }
        .check__native { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
        .check__box {
            display: inline-flex; flex: none; width: 20px; height: 20px;
            border: 1px solid var(--wx-border-strong); border-radius: var(--wx-radius-xs);
            background: var(--wx-bg-surface); color: transparent;
            transition: border-color var(--wx-duration-fast) var(--wx-easing-standard), color var(--wx-duration-fast) var(--wx-easing-standard), box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
        }
        .check__box svg { width: 100%; height: 100%; }
        .check:hover .check__box { border-color: var(--wx-color-primary); }
        .check__native:checked + .check__box { border-color: var(--wx-color-primary); color: var(--wx-color-primary); }
        .check__native:focus-visible + .check__box { border-color: var(--wx-color-primary); box-shadow: var(--wx-ring-focus); }

        .buttons { display: flex; flex-wrap: wrap; gap: var(--wx-space-12); margin-top: var(--wx-space-8); }
        form.inline { display: contents; }
        .button {
            display: inline-flex; align-items: center; justify-content: center;
            height: var(--wx-size-control-lg); padding: 0 var(--wx-space-24);
            border: 1px solid var(--wx-border-default); border-radius: var(--wx-radius-control);
            background: var(--wx-bg-surface); color: var(--wx-text-default);
            font: inherit; font-size: var(--wx-font-size-control-lg); font-weight: var(--wx-font-weight-semibold);
            line-height: var(--wx-font-line-height-tight); white-space: nowrap; cursor: pointer;
            transition: background-color var(--wx-duration-normal) var(--wx-easing-standard), border-color var(--wx-duration-normal) var(--wx-easing-standard);
        }
        .button:hover { background: var(--wx-bg-fill); }
        .button:active { background: var(--wx-bg-fill-hover); }
        .button:focus-visible { outline: none; box-shadow: var(--wx-ring-focus); }
        .button--primary { border-color: var(--wx-color-primary); background: var(--wx-color-primary); color: var(--wx-color-primary-contrast); }
        .button--primary:hover { border-color: var(--wx-color-primary-hover); background: var(--wx-color-primary-hover); }
        .button--primary:active { border-color: var(--wx-color-primary-active); background: var(--wx-color-primary-active); }

        .foot { margin-top: var(--wx-space-16); color: var(--wx-text-muted); font-size: var(--wx-font-size-xs); text-align: center; }
        .foot p { margin: var(--wx-space-4) 0; }
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
            <div>
                <h1>{{ $client->name }}</h1>
                <p class="asks">{{ __('webx-auth::consent.asks', ['site' => $site]) }}</p>
                <span class="return-to">{{ $returnTo }}</span>
            </div>

            <div class="who">
                <div class="who__person">
                    <span class="who__name">{{ __('webx-auth::consent.signed-in-as', ['name' => $user->name]) }}</span>
                    <span class="who__email">{{ $user->email }}</span>
                </div>
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

            <div class="alert" role="note">
                <svg class="alert__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.3 4.9a2 2 0 0 1 3.4 0l7 12.1a2 2 0 0 1-1.7 3H5a2 2 0 0 1-1.7-3z"/><path d="M12 9.5v4"/><circle cx="12" cy="16.6" r="1.05" fill="currentColor" stroke="none"/></svg>
                <div class="alert__body">
                    <p>{{ __('webx-auth::consent.warning') }}</p>
                    <p>{{ __('webx-auth::consent.liability') }}</p>
                </div>
            </div>

            <form method="post" action="{{ route('webx.auth.consent.approve') }}" id="consent-approve">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <label class="check">
                    <input type="checkbox" name="read_only" value="1" class="check__native">
                    <span class="check__box" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="M3.5 8.5l3 3 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <span>{{ __('webx-auth::consent.read-only') }}</span>
                </label>
            </form>

            <div class="buttons">
                <button type="submit" form="consent-approve" class="button button--primary">{{ __('webx-auth::consent.allow') }}</button>

                <form method="post" action="{{ route('passport.authorizations.deny') }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="button">{{ __('webx-auth::consent.cancel') }}</button>
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
