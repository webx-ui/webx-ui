{{--
    The frame every letter of the site stands in (spec §7.5): the name of the site above, a sheet
    of paper with the letter on it, a line below saying who sent it. A module's letter asks for
    it by name — `@component('mail.layout', ['title' => …])`, its body in the slot — so a local
    theme that overrides `views/mail/layout.blade.php` re-dresses every letter at once.

    A mail client is a browser from 2003: no CSS variable, often no <style>, no class, no
    @container. So every value is a token's, taken on the server with theme_token() and written
    into style="…" — a preset or a change of tokens.json repaints the letters with the
    site — and the layout is tables. Sizes are pixels rather than the theme's rem and clamp():
    a mail client's root size is anybody's guess. The font is the theme's family with its
    fallbacks; the theme's own files are not loaded by mail, so the reader's system font draws it.
--}}
@php
    $page = theme_token('color-surface');
    $paper = theme_token('color-bg');
    $text = theme_token('color-text');
    $muted = theme_token('color-text-muted');
    $border = theme_token('color-border');
    $accent = theme_token('color-accent');
    $font = theme_token('font-body');
    $radius = theme_token('radius-md');
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
<body style="margin:0;padding:0;background-color:{{ $page }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $page }};">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;">
                <tr>
                    <td style="padding:0 4px 12px 4px;font-family:{{ $font }};font-size:15px;line-height:1.4;font-weight:600;">
                        <a href="{{ $home }}" style="color:{{ $accent }};text-decoration:none;">{{ $site }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:{{ $paper }};border:1px solid {{ $border }};border-radius:{{ $radius }};font-family:{{ $font }};font-size:15px;line-height:1.5;color:{{ $text }};">
                        {{ $slot }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 4px 0 4px;font-family:{{ $font }};font-size:12px;line-height:1.5;color:{{ $muted }};">
                        {{ __('theme::theme.mail.sent', ['site' => $site]) }}
                        <a href="{{ $home }}" style="color:{{ $muted }};">{{ preg_replace('~^https?://~', '', $home) }}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
