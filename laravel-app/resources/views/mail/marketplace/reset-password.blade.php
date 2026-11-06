@extends('mail.layouts.branded')

@section('hero')
    @include('mail.marketplace._hero', ['icon' => '🔑'])
@endsection

@section('content')
    <p style="margin:0 0 14px; font-size:16px; font-weight:600;">{{ $copy->common('greeting') }}</p>
    <p style="margin:0;">{{ $copy->get('intro') }}</p>

    @include('mail.partials.button', ['label' => $copy->get('cta')])

    @include('mail.partials.closing')
@endsection
