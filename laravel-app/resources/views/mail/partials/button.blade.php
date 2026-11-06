{{-- Bulletproof call-to-action button: a table cell, so it renders even where CSS is limited (Outlook) --}}
@php($c = fn (string $name) => $brand->color($name))
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 8px;">
    <tr>
        <td align="center" bgcolor="{{ $c('primary') }}"
            style="border-radius:14px; background-color:{{ $c('primary') }}; background-image:linear-gradient(135deg, {{ $c('primary') }}, {{ $c('accent') }}); box-shadow:0 10px 24px rgba(15, 23, 42, 0.14);">
            <a href="{{ $url }}" target="_blank" rel="noopener"
               style="display:inline-block; padding:15px 30px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:14px;">
                {{ $label }} &rarr;
            </a>
        </td>
    </tr>
</table>
