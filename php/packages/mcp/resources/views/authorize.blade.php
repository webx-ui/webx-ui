{{--
    The plain consent page.

    Passport ships no view of its own, so without one the whole flow ends in "not
    instantiable" at the last step — the one the person is looking at. This is that page at
    its smallest: who is asking, where they will be sent back to, and two buttons. A panel
    that wants its own replaces it with `Passport::authorizationView()`.

    The address is shown, not only the name: a client chooses its own name, and "Site panel"
    costs nothing to type.
--}}
@php
    $redirect = $request->query('redirect_uri') ?: ($client->redirect_uris[0] ?? '');
    $host = parse_url((string) $redirect, PHP_URL_HOST) ?: (string) $redirect;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $client->name }}</title>
    <style>
        :root { color-scheme: light dark; --ink: #18181b; --muted: #71717a; --line: #e4e4e7; --paper: #fff; --back: #f4f4f5; --accent: #2563eb; }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #fafafa; --muted: #a1a1aa; --line: #3f3f46; --paper: #18181b; --back: #09090b; }
        }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
               background: var(--back); color: var(--ink);
               font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 420px; padding: 32px; border: 1px solid var(--line);
               border-radius: 12px; background: var(--paper); }
        h1 { margin: 0 0 16px; font-size: 20px; }
        dl { margin: 0 0 24px; display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; }
        dt { color: var(--muted); }
        dd { margin: 0; overflow-wrap: anywhere; }
        ul { margin: 0 0 24px; padding-left: 20px; color: var(--muted); }
        form { display: inline; }
        button { padding: 10px 20px; border-radius: 8px; border: 1px solid var(--line);
                 background: transparent; color: inherit; font: inherit; cursor: pointer; }
        button.approve { background: var(--accent); border-color: var(--accent); color: #fff; }
        .buttons { display: flex; gap: 12px; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $client->name }} is asking for access</h1>

        <dl>
            <dt>Signed in as</dt>
            <dd>{{ $user->name }}</dd>
            <dt>Sends you back to</dt>
            <dd>{{ $host }}</dd>
        </dl>

        @if (count($scopes) > 0)
            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        <p>It will act as you, and can do whatever your account can do. Allow it only if you
           recognise both the application and the address above.</p>

        <div class="buttons">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">Allow</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit">Cancel</button>
            </form>
        </div>
    </main>
</body>
</html>
