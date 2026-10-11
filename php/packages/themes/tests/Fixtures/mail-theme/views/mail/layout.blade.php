{{-- A comment may say var(--site-color-bg) and #fff: it is not read. --}}
<body style="margin:0;background-color:#f4f5f7;">
<table bgcolor="#ffffff" style="color: var(--site-color-text);">
    <tr><td style="color:{{ theme_token('color-accent', '#2563eb') }};border:1px solid {{ theme_token('color-border') }};">{{ $slot }}</td></tr>
    <tr><td><font color="red">old</font></td></tr>
</table>
<style>
  a { color: rgb(0 0 0); }
</style>
</body>
