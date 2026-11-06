{{-- Expiry, safety note, raw link fallback and signature: the same for every theme --}}
@php($c = fn (string $name) => $brand->color($name))
@if ($expiresIn)
    <p style="margin:0 0 18px; font-size:13px; color:{{ $c('muted') }};">⏱&nbsp; {{ $copy->common('expires', ['time' => $expiresIn]) }}</p>
@endif

<p style="margin:0 0 22px; font-size:14px; color:{{ $c('muted') }};">{{ $copy->get('ignore') }}</p>

<p style="margin:0; font-size:15px;">
    {{ $copy->common('signature') }}
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px; border-top:1px solid {{ $c('border') }};">
    <tr>
        <td style="padding-top:16px; font-size:12px; line-height:1.55; color:{{ $c('muted') }};">
            {{ $copy->common('fallback') }}<br>
            <a href="{{ $url }}" style="color:{{ $c('primary') }}; word-break:break-all;">{{ $url }}</a>
        </td>
    </tr>
</table>
