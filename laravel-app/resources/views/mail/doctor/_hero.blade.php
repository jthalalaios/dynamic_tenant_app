{{-- Clinic hero: calm, airy band with a medical cross badge --}}
@php($c = fn (string $name) => $brand->color($name))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" bgcolor="{{ $c('soft') }}"
            style="padding:38px 28px 30px; background-color:{{ $c('soft') }}; background-image:linear-gradient(180deg, {{ $c('soft') }} 0%, {{ $c('surface') }} 100%); border-bottom:1px solid {{ $c('border') }}; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td width="68" height="68" align="center" valign="middle" bgcolor="{{ $c('surface') }}"
                        style="width:68px; height:68px; border-radius:50%; background-color:{{ $c('surface') }}; border:2px solid {{ $c('border') }}; box-shadow:0 10px 26px rgba(15, 102, 240, 0.16); color:{{ $c('primary') }}; font-family:Arial, sans-serif; font-size:32px; line-height:64px; font-weight:700;">
                        {{ $icon ?? '✚' }}
                    </td>
                </tr>
            </table>
            <h1 style="margin:20px 0 6px; font-size:24px; line-height:1.3; font-weight:700; color:{{ $c('text') }};">{{ $copy->get('title') }}</h1>
            <p style="margin:0; font-size:13px; letter-spacing:0.14em; text-transform:uppercase; font-weight:700; color:{{ $c('primary') }};">{{ $copy->get('eyebrow') }}</p>
        </td>
    </tr>
</table>
