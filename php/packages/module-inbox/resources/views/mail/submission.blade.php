{{--
    The notification (§9).

    The body of the letter; the frame around it — the name of the site, the sheet, the line
    below — is the theme's `mail.layout`, or this package's own on a site without a theme
    (Look::frame(), THEMES §7.5). Plain tables and inline styles on purpose: a mail client is a
    browser from 2003, and this letter has to be legible in one that knows no stylesheet, no
    class and no CSS variable. Every colour is a token's value from Look, so the letter repaints
    with the site; `webx:theme:check` finds a literal one (rule 25).

    It is published with `webx-inbox-views`, after which it belongs to the site; a theme
    overrides it in `views/vendor/webx-inbox/mail/submission.blade.php`.
--}}
@php
    $look = app(\WebxUi\Inbox\Mail\Look::class);
    $muted = $look('color-text-muted');
    $border = $look('color-border');
    $title = app(\WebxUi\Admin\Shortcodes\Shortcodes::class)->plain((string) ($form->title ?: $form->slug));
@endphp
@component($look->frame(), ['title' => $title])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <h1 style="margin:0 0 4px 0;font-size:19px;line-height:1.3;font-weight:600;">{{ $title }}</h1>
            <p style="margin:0;color:{{ $muted }};font-size:13px;">
                {{ __('webx-inbox::mail.received', ['date' => optional($submission->created_at)->format('d.m.Y H:i')]) }}
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px 0 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                @forelse ($values as $value)
                    <tr>
                        <td style="padding:8px 12px 8px 0;vertical-align:top;color:{{ $muted }};width:38%;border-bottom:1px solid {{ $border }};">
                            @shortcodesPlain($value->label ?: $value->name)
                        </td>
                        <td style="padding:8px 0;vertical-align:top;border-bottom:1px solid {{ $border }};">
                            {{-- Kept as the visitor typed it, newlines and all: a message from a
                                 textarea is unreadable as one paragraph. --}}
                            {!! nl2br(e($value->readable())) !!}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td style="padding:8px 0;color:{{ $muted }};">{{ __('webx-inbox::mail.no-values') }}</td>
                    </tr>
                @endforelse
            </table>
        </td>
    </tr>

    @if ($files->isNotEmpty())
        <tr>
            <td style="padding:16px 24px 0 24px;">
                <p style="margin:0 0 8px 0;font-weight:600;">{{ __('webx-inbox::mail.files') }}</p>
                <ul style="margin:0;padding-left:20px;">
                    @foreach ($files as $file)
                        {{-- Named, not linked: the bytes are behind the panel's permission, and a
                             link in a letter is a link anybody the letter reaches can follow (§8). --}}
                        <li style="margin:0 0 4px 0;">{{ $file->name }} <span style="color:{{ $muted }};">({{ round($file->size / 1024) }} KB)</span></li>
                    @endforeach
                </ul>
            </td>
        </tr>
    @endif

    @if (! empty($meta))
        <tr>
            <td style="padding:16px 24px 0 24px;">
                <p style="margin:0 0 8px 0;font-weight:600;">{{ __('webx-inbox::mail.meta') }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:13px;color:{{ $muted }};">
                    @foreach (['page', 'referrer', 'locale', 'ip'] as $key)
                        @if (! empty($meta[$key]))
                            <tr>
                                <td style="padding:2px 12px 2px 0;vertical-align:top;width:38%;">{{ __('webx-inbox::mail.meta-'.$key) }}</td>
                                <td style="padding:2px 0;vertical-align:top;word-break:break-all;">{{ $meta[$key] }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @foreach ($meta['utm'] ?? [] as $key => $value)
                        <tr>
                            <td style="padding:2px 12px 2px 0;vertical-align:top;">{{ $key }}</td>
                            <td style="padding:2px 0;vertical-align:top;word-break:break-all;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:24px;">
            <a href="{{ $url }}" style="display:inline-block;padding:10px 18px;border-radius:{{ $look('radius-sm') }};background-color:{{ $look('color-accent') }};color:{{ $look('color-accent-contrast') }};text-decoration:none;font-weight:500;">
                {{ __('webx-inbox::mail.open') }}
            </a>
        </td>
    </tr>
</table>
@endcomponent
