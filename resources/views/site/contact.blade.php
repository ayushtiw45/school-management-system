@extends('layouts.site')
@section('title', __('Contact us'))
@section('content')
@php($wa = preg_replace('/\D/', '', (string) \App\Models\PlatformSetting::get('contact_whatsapp')))
<div class="container py-5" style="max-width:820px">
    <h1 class="fw-bold mb-2">{{ __('Contact us') }}</h1>
    <p class="text-muted">{{ __('We help you install, set up fees and start using the software.') }}</p>
    <div class="row g-3 mt-2">
        @if($wa)<div class="col-md-4"><a class="card p-4 text-decoration-none h-100" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp fs-2 text-success"></i><div class="fw-semibold mt-2">WhatsApp</div><div class="text-muted">+{{ $wa }}</div></a></div>@endif
        @if(\App\Models\PlatformSetting::get('contact_phone'))<div class="col-md-4"><a class="card p-4 text-decoration-none h-100" href="tel:{{ preg_replace('/[^\d+]/', '', \App\Models\PlatformSetting::get('contact_phone')) }}"><i class="bi bi-telephone fs-2"></i><div class="fw-semibold mt-2">{{ __('Phone') }}</div><div class="text-muted">{{ \App\Models\PlatformSetting::get('contact_phone') }}</div></a></div>@endif
        @if(\App\Models\PlatformSetting::get('contact_email'))<div class="col-md-4"><a class="card p-4 text-decoration-none h-100" href="mailto:{{ \App\Models\PlatformSetting::get('contact_email') }}"><i class="bi bi-envelope fs-2"></i><div class="fw-semibold mt-2">{{ __('Email') }}</div><div class="text-muted">{{ \App\Models\PlatformSetting::get('contact_email') }}</div></a></div>@endif
    </div>
    <p class="small text-muted mt-4">{{ \App\Models\PlatformSetting::get('company_name') }}</p>
</div>
@endsection
