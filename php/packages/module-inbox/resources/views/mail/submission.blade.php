{{--
    The notification (§9).

    Plain HTML and inline styles on purpose: a mail client is a browser from 2003, and this
    letter has to be legible in one that knows no stylesheet, no class and no dark mode. It is
    published with `webx-inbox-views`, after which it belongs to the site — which is the point,
    because every client wants their own letterhead on it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $form->title ?: $form->slug }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#1f2430;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr>
        <td style="padding:24px 24px 8px 24px;">
            <h1 style="margin:0 0 4px 0;font-size:19px;font-weight:600;">{{ $form->title ?: $form->slug }}</h1>
            <p style="margin:0;color:#6b7280;font-size:13px;">
                {{ __('webx-inbox::mail.received', ['date' => optional($submission->created_at)->format('d.m.Y H:i')]) }}
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:8px 24px 0 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                @forelse ($values as $value)
                    <tr>
                        <td style="padding:8px 12px 8px 0;vertical-align:top;color:#6b7280;width:38%;border-bottom:1px solid #eceef1;">
                            {{ $value->label ?: $value->name }}
                        </td>
                        <td style="padding:8px 0;vertical-align:top;border-bottom:1px solid #eceef1;">
                            {{-- Kept as the visitor typed it, newlines and all: a message from a
                                 textarea is unreadable as one paragraph. --}}
                            {!! nl2br(e($value->value)) !!}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td style="padding:8px 0;color:#6b7280;">{{ __('webx-inbox::mail.no-values') }}</td>
                    </tr>
                @endforelse
            </table>
        </td>
    </tr>

    @if ($files->isNotEmpty())
        <tr>
            <td style="padding:16px 24px 0 24px;">
                <p style="margin:0 0 8px 0;font-weight:600;">{{ __('webx-inbox::mail.files') }}</p>
                <ul style="margin:0;padding-left:20px;color:#1f2430;">
                    @foreach ($files as $file)
                        {{-- Named, not linked: the bytes are behind the panel's permission, and a
                             link in a letter is a link anybody the letter reaches can follow (§8). --}}
                        <li style="margin:0 0 4px 0;">{{ $file->name }} <span style="color:#6b7280;">({{ round($file->size / 1024) }} KB)</span></li>
                    @endforeach
                </ul>
            </td>
        </tr>
    @endif

    @if (! empty($meta))
        <tr>
            <td style="padding:16px 24px 0 24px;">
                <p style="margin:0 0 8px 0;font-weight:600;">{{ __('webx-inbox::mail.meta') }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:13px;color:#6b7280;">
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
            <a href="{{ $url }}" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:500;">
                {{ __('webx-inbox::mail.open') }}
            </a>
        </td>
    </tr>
</table>
</body>
</html>
