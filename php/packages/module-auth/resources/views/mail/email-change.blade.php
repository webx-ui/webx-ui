{{--
    The letter that moves a sign-in address. Plain on purpose: one sentence of why, one link, one
    sentence for whoever did not ask for it. No colours of its own — this package knows no theme,
    and a letter in the reader's mail client's own colours is never one that clashes with the
    site's (THEMES §7.5, rule 25). Published like any other view for a site that wants its own
    letterhead.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('webx-auth::profile.email-letter-subject', ['site' => $site]) }}</title>
</head>
<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:15px;line-height:1.5;">
    <p>{{ __('webx-auth::profile.email-letter-hello', ['name' => $name]) }}</p>
    <p>{{ __('webx-auth::profile.email-letter-text', ['site' => $site]) }}</p>
    <p style="font-size:17px;font-weight:600;">
        <a href="{{ $link }}">{{ __('webx-auth::profile.email-letter-button') }}</a>
    </p>
    <p style="font-size:13px;">{{ __('webx-auth::profile.email-letter-expires', ['minutes' => $minutes]) }}</p>
    <p style="font-size:13px;">{{ __('webx-auth::profile.email-letter-ignore') }}</p>
</body>
</html>
