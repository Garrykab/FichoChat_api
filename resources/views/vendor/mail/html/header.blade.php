@props(['url'])
<tr>
<td class="header" style="background-color: #000000; border-radius: 16px 16px 0 0; padding: 28px 24px 20px;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@php
    $logoUrl = rtrim((string) config('app.url'), '/').'/branding/fichochat-logo.png';
@endphp
<img
    src="{{ $logoUrl }}"
    class="logo"
    alt="{{ config('app.name', 'FichoChat') }}"
    width="160"
    style="border-radius: 16px; max-height: 160px; max-width: 160px; width: 160px; height: auto;"
>
</a>
</td>
</tr>
