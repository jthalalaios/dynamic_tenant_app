@extends('mail.layouts.branded')

@section('hero')
    @include('mail.doctor._hero', ['icon' => '✚'])
@endsection

@section('content')
    @php($c = fn (string $name) => $brand->color($name))
    <p style="margin:0 0 14px; font-size:16px; font-weight:600;">{{ $copy->common('greeting') }}</p>
    <p style="margin:0;">{{ $copy->get('intro') }}</p>

    @include('mail.partials.button', ['label' => $copy->get('cta')])

    @if ($highlights = $copy->items('highlights'))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:24px 0 24px; border:1px solid {{ $c('border') }}; border-radius:16px; background-color:{{ $c('background') }};">
            <tr>
                <td style="padding:18px 20px 8px; font-size:14px; font-weight:700; color:{{ $c('text') }};">{{ $copy->get('highlights_title') }}</td>
            </tr>
            @foreach ($highlights as $line)
                <tr>
                    <td style="padding:0 20px 12px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td width="24" height="24" align="center" valign="middle" bgcolor="{{ $c('soft') }}"
                                    style="width:24px; height:24px; border-radius:50%; background-color:{{ $c('soft') }}; color:{{ $c('primary') }}; font-family:Arial, sans-serif; font-size:13px; font-weight:700; line-height:24px;">✓</td>
                                <td style="padding-left:12px; font-size:14px; color:{{ $c('text') }};">{{ $line }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endforeach
            <tr><td style="font-size:0; line-height:6px;">&nbsp;</td></tr>
        </table>
    @endif

    @include('mail.partials.closing')
@endsection
