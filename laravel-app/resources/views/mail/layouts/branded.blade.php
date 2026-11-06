{{--
    Shared shell of every branded email. Theme templates (mail/{theme}/*.blade.php)
    fill `hero` and `content`; colours come from config/branding.php via $brand.
    Email-client safe: tables, inline styles, no external images or web fonts.
--}}
@php
    /** @var \App\Mail\Branding\MailBrand $brand */
    $c = fn (string $name) => $brand->color($name);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $copy->get('subject') }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $c('background') }}; -webkit-text-size-adjust:100%;">
    {{-- Inbox preview text --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        {{ $copy->get('intro') }}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $c('background') }};">
        <tr>
            <td align="center" style="padding:32px 14px 40px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;">

                    {{-- Brand row --}}
                    <tr>
                        <td style="padding:0 4px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="40" height="40" align="center" valign="middle" bgcolor="{{ $c('primary') }}"
                                        style="width:40px; height:40px; border-radius:12px; background-color:{{ $c('primary') }}; background-image:linear-gradient(135deg, {{ $c('primary') }}, {{ $c('accent') }}); color:#ffffff; font-family:Arial, sans-serif; font-size:20px; line-height:40px; font-weight:700;">
                                        {{ $brand->glyph }}
                                    </td>
                                    <td style="padding-left:12px; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                                        <div style="font-size:18px; font-weight:700; color:{{ $c('text') }};">{{ $brand->organization }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:{{ $c('surface') }}; border:1px solid {{ $c('border') }}; border-radius:22px; overflow:hidden;">
                            @yield('hero')
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:30px 34px 34px; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; line-height:1.6; color:{{ $c('text') }};">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:22px 24px 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif; font-size:12px; line-height:1.6; color:{{ $c('muted') }};">
                            {{ $copy->common('footer') }}<br>
                            <a href="{{ $brand->frontendUrl }}" style="color:{{ $c('primary') }}; text-decoration:none; font-weight:600;">{{ $brand->organization }}</a>
                            &nbsp;·&nbsp; © {{ date('Y') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
