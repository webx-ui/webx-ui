{{-- The frame of a channel's letter: the body, HTML already — a text template escaped with its links, or an HTML one with its values escaped. No colours of its own — the mail client's are the reader's. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
    <div style="max-width:600px;margin:0 auto;font-size:15px;line-height:1.5;">
        {{ $body }}
    </div>
</body>
</html>
