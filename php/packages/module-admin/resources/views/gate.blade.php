<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('webx-admin::gate.title') }}</title>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.5 system-ui, sans-serif; }
            main { max-width: 32rem; padding: 24px; text-align: center; }
            h1 { margin: 0 0 8px; font-size: 1.25rem; }
            p { margin: 0; opacity: .75; }
        </style>
    </head>
    <body>
        <main>
            <h1>{{ __('webx-admin::gate.title') }}</h1>
            <p>{{ __('webx-admin::gate.text') }}</p>
        </main>
    </body>
</html>
