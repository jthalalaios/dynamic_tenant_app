{{-- Store hero: bold full-bleed gradient banner with white type --}}
@php($c = fn (string $name) => $brand->color($name))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td bgcolor="{{ $c('primary') }}"
            style="padding:34px 34px 32px; background-color:{{ $c('primary') }}; background-image:linear-gradient(120deg, {{ $c('primary') }} 0%, {{ $c('accent') }} 100%); font-family:'Segoe UI', Helvetica, Arial, sans-serif; color:#ffffff;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td valign="middle">
                        <span style="display:inline-block; padding:5px 12px; border-radius:999px; background-color:rgba(255, 255, 255, 0.22); font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:#ffffff;">
                            {{ $copy->get('eyebrow') }}
                        </span>
                        <h1 style="margin:14px 0 0; font-size:27px; line-height:1.25; font-weight:800; color:#ffffff;">{{ $copy->get('title') }}</h1>
                    </td>
                    <td width="76" align="right" valign="middle" style="font-size:52px; line-height:1;">{{ $icon ?? '🛍' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
