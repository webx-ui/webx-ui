{{--
    The frame of this package's letters on a site without a theme (THEMES §7.5). Under a theme
    the letter stands in the theme's `mail.layout` instead — see Look::frame(). Same shape as
    theme-default's: the name of the site, a sheet with the letter, the address of the site.

    Tables and inline styles, values from Look — a mail client reads no CSS variable and no class.
--}}
@php
    $look = app(\WebxUi\Inbox\Mail\Look::class);
    $site = (string) config('app.name');
    $home = url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $site }}</title>
</head>
<body style="margin:0;padding:0;background-color:{{ $look('color-surface') }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $look('color-surface') }};">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;">
                <tr>
                    <td style="padding:0 4px 12px 4px;font-family:{{ $look('font-body') }};font-size:15px;line-height:1.4;font-weight:600;">
                        <a href="{{ $home }}" style="color:{{ $look('color-accent') }};text-decoration:none;">{{ $site }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:{{ $look('color-bg') }};border:1px solid {{ $look('color-border') }};border-radius:{{ $look('radius-md') }};font-family:{{ $look('font-body') }};font-size:15px;line-height:1.5;color:{{ $look('color-text') }};">
                        {{ $slot }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
