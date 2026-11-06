@extends('mail.layouts.branded')

@section('hero')
    @include('mail.marketplace._hero', ['icon' => '🛍'])
@endsection

@section('content')
    @php
        $c = fn (string $name) => $brand->color($name);
        // One icon per highlight, in order: products, orders, insights
        $icons = ['📦', '🚚', '📈'];
    @endphp
    <p style="margin:0 0 14px; font-size:16px; font-weight:600;">{{ $copy->common('greeting') }}</p>
    <p style="margin:0;">{{ $copy->get('intro') }}</p>

    @include('mail.partials.button', ['label' => $copy->get('cta')])

    @if ($highlights = $copy->items('highlights'))
        <p style="margin:26px 0 12px; font-size:14px; font-weight:700; color:{{ $c('text') }};">{{ $copy->get('highlights_title') }}</p>
        @foreach ($highlights as $i => $line)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="margin:0 0 10px; border-radius:14px; background-color:{{ $c('soft') }}; border-left:4px solid {{ $i % 2 ? $c('accent') : $c('primary') }};">
                <tr>
                    <td width="52" align="center" valign="middle" style="padding:14px 0 14px 8px; font-size:22px; line-height:1;">{{ $icons[$i] ?? '✨' }}</td>
                    <td valign="middle" style="padding:14px 16px 14px 6px; font-size:14px; font-weight:600; color:{{ $c('text') }};">{{ $line }}</td>
                </tr>
            </table>
        @endforeach
        <div style="height:16px; line-height:16px; font-size:0;">&nbsp;</div>
    @endif

    @include('mail.partials.closing')
@endsection
