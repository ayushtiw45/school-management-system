@extends('layouts.app')
@section('title', 'License')
@section('content')
@php
    $badge = ['licensed' => ['success', 'Licensed'], 'trial' => ['warning', 'Free Trial'], 'expired' => ['danger', 'Expired']][$status['state']];
    $waText = "Hello, I want to buy a license for TDS School Management Software.\nSchool: ".school('name')."\nInstallation ID: ".$installationId;
    $wa = $vendor['whatsapp'] ? 'https://wa.me/'.preg_replace('/\D/', '', $vendor['whatsapp']).'?text='.rawurlencode($waText) : null;
@endphp
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">License Status</div>
            <div class="card-body">
                <div class="mb-3"><span class="badge text-bg-{{ $badge[0] }} fs-6">{{ $badge[1] }}</span></div>
                <dl class="row mb-0">
                    @if($status['state'] === 'licensed')
                        <dt class="col-5 fw-normal text-muted">Plan</dt><dd class="col-7">{{ ucfirst($status['plan'] ?? '') }}</dd>
                        @if($status['school'])<dt class="col-5 fw-normal text-muted">Licensed to</dt><dd class="col-7">{{ $status['school'] }}</dd>@endif
                        <dt class="col-5 fw-normal text-muted">Valid till</dt><dd class="col-7">{{ $status['expires'] ? $status['expires']->format('d M Y').' ('.$status['days_left'].' days left)' : 'Lifetime' }}</dd>
                    @elseif($status['state'] === 'trial')
                        <dt class="col-5 fw-normal text-muted">Trial ends</dt><dd class="col-7">{{ $status['expires']->format('d M Y') }} ({{ $status['days_left'] }} days left)</dd>
                    @else
                        <dt class="col-5 fw-normal text-muted">Ended on</dt><dd class="col-7">{{ $status['expires']?->format('d M Y') }}</dd>
                        <dd class="col-12 small text-muted mt-2">The software is in <strong>read-only mode</strong>. All your data is safe: you can view, print and download reports. Activate a license to add or change records again.</dd>
                    @endif
                    <dt class="col-5 fw-normal text-muted">Version</dt><dd class="col-7">{{ config('license.version') }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Buy / Renew License</div>
            <div class="card-body">
                <p class="small mb-2">1. Send this <strong>Installation ID</strong> to us:</p>
                <div class="input-group mb-3">
                    <input class="form-control font-monospace fw-semibold" value="{{ $installationId }}" id="instId" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('instId').value); this.textContent='Copied'">Copy</button>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if($vendor['website'])<a href="{{ rtrim($vendor['website'], '/') }}/buy?installation_id={{ urlencode($installationId) }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><i class="bi bi-cart"></i> Buy online</a>@endif
                    @if($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Send on WhatsApp</a>@endif
                    @if($vendor['phone'])<span class="small align-self-center"><i class="bi bi-telephone"></i> {{ $vendor['phone'] }}</span>@endif
                    @if($vendor['email'])<a href="mailto:{{ $vendor['email'] }}?subject=License%20{{ $installationId }}" class="small align-self-center">{{ $vendor['email'] }}</a>@endif
                    @if($vendor['website'])<a href="{{ $vendor['website'] }}" target="_blank" rel="noopener" class="small align-self-center">{{ preg_replace('#^https?://#', '', $vendor['website']) }}</a>@endif
                </div>
                <p class="small mb-2">2. Paste the license key you receive:</p>
                @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('license.activate') }}">
                        @csrf
                        <textarea name="key" rows="3" class="form-control font-monospace small mb-2 @error('key') is-invalid @enderror" placeholder="TDS1.xxxxxxxx…" required>{{ old('key') }}</textarea>
                        @error('key')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <button class="btn btn-primary"><i class="bi bi-key"></i> Activate</button>
                    </form>
                @else
                    <p class="small text-muted mb-0">Ask your admin to enter the license key.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
