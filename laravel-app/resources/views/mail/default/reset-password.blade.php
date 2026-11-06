{{-- Generic template for tenant types without their own (add mail/{type}/reset-password.blade.php to brand one) --}}
@extends('mail.layouts.branded')

@section('hero')
    @php($c = fn (string $name) => $brand->color($name))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="padding:32px 34px 0; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
                <h1 style="margin:0; font-size:24px; line-height:1.3; color:{{ $c('text') }};">{{ $copy->get('title') }}</h1>
            </td>
        </tr>
    </table>
@endsection

@section('content')
    <p style="margin:0 0 14px; font-size:16px; font-weight:600;">{{ $copy->common('greeting') }}</p>
    <p style="margin:0;">{{ $copy->get('intro') }}</p>

    @include('mail.partials.button', ['label' => $copy->get('cta')])

    @include('mail.partials.closing')
@endsection
