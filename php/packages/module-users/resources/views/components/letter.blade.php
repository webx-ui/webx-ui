{{--
    The frame of every letter of the module (spec §8.6): a greeting, what happened, one link, and
    what to do for whoever did not ask. Plain on purpose — no colours of its own: a letter in the
    reader's mail client's colours never clashes with the site's (THEMES §7.5, rule 25).

    Override: <layer>/views/vendor/webx-users/components/letter.blade.php — or one letter at a
    time, mail/<name>.blade.php.
--}}
@props(['title', 'name', 'link' => null, 'button' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:15px;line-height:1.5;">
    <p>{{ __('webx-users::mail.hello', ['name' => $name]) }}</p>
    {{ $slot }}
    @if ($link !== null && $button !== null)
        <p style="font-size:17px;font-weight:600;"><a href="{{ $link }}">{{ $button }}</a></p>
    @endif
    @isset($note)
        <p style="font-size:13px;">{{ $note }}</p>
    @endisset
</body>
</html>
