{{-- Plain-text part of every branded call-to-action email (unescaped: this is not HTML) --}}
{!! $copy->get('title') !!}

{!! $copy->common('greeting') !!}

{!! $copy->get('intro') !!}

{!! $copy->get('cta') !!}:
{!! $url !!}

@if ($expiresIn)
{!! $copy->common('expires', ['time' => $expiresIn]) !!}

@endif
{!! $copy->get('ignore') !!}

{!! $copy->common('signature') !!}

--
{!! $copy->common('footer') !!}
{!! $brand->frontendUrl !!}
