@extends('layouts.app')
@section('title', 'License')
@section('content')
@php
    $badge = ['licensed' => ['success', 'Active'], 'trial' => ['warning', 'Free Trial'], 'expired' => ['danger', $status['plan'] === 'none' ? 'Not activated' : 'Expired']][$status['state']];
    $waText = "Hello, I need help with TDS School Management Software.\nSchool: ".school('name')."\nInstallation ID: ".$installationId;
    $wa = $vendor['whatsapp'] ? 'https://wa.me/'.preg_replace('/\D/', '', $vendor['whatsapp']).'?text='.rawurlencode($waText) : null;
    $needsAction = $status['state'] !== 'licensed' || ($status['days_left'] !== null && $status['days_left'] <= 3);
@endphp

@if($status['state'] !== 'licensed')
    <div class="card mb-3 border-2" style="border-color:#e9a23b">
        <div class="card-body p-4 text-center">
            @if($status['plan'] === 'none')
                <h2 class="h4 fw-bold">Start your 7-day trial for ₹1</h2>
                <p class="text-muted mb-3">Pay ₹1 online to unlock every feature for 7 days. After that the monthly plan continues by Autopay, and you can cancel any time.</p>
            @else
                <h2 class="h4 fw-bold">{{ $status['plan'] === 'trial' && $status['state'] === 'trial' ? 'Activate your software' : 'Renew to keep adding records' }}</h2>
                <p class="text-muted mb-3">Your data is safe. Renew online and the software unlocks automatically.</p>
            @endif
            @if($activateUrl)
                <a href="{{ $activateUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg px-4"><i class="bi bi-box-arrow-up-right"></i> {{ $status['plan'] === 'none' ? 'Start trial for ₹1' : 'Renew online' }}</a>
                <div class="small text-muted mt-3" id="waitMsg"><span class="spinner-border spinner-border-sm"></span> After paying, come back here: the software unlocks automatically.</div>
                <button type="button" class="btn btn-link btn-sm" id="checkNow">Check now</button>
            @else
                <p class="small text-muted mb-0">Contact us to activate this computer.</p>
            @endif
        </div>
    </div>
@endif

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
                        @if($status['expires'])<dd class="col-12 small text-muted mt-2">With Autopay, the software renews itself every month when it is connected to the internet.</dd>@endif
                    @elseif($status['state'] === 'trial')
                        <dt class="col-5 fw-normal text-muted">Trial ends</dt><dd class="col-7">{{ $status['expires']->format('d M Y') }} ({{ $status['days_left'] }} days left)</dd>
                    @elseif($status['plan'] !== 'none')
                        <dt class="col-5 fw-normal text-muted">Ended on</dt><dd class="col-7">{{ $status['expires']?->format('d M Y') }}</dd>
                        <dd class="col-12 small text-muted mt-2">The software is in <strong>read-only mode</strong>. All your data is safe: you can view, print and download reports.</dd>
                    @endif
                    <dt class="col-5 fw-normal text-muted">Installation ID</dt><dd class="col-7 font-monospace">{{ $installationId }} <button class="btn btn-link btn-sm p-0" type="button" onclick="navigator.clipboard.writeText('{{ $installationId }}'); this.textContent='Copied'">Copy</button></dd>
                    <dt class="col-5 fw-normal text-muted">Version</dt><dd class="col-7">{{ config('license.version') }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Help & manual activation</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp support</a>@endif
                    @if($vendor['phone'])<span class="small align-self-center"><i class="bi bi-telephone"></i> {{ $vendor['phone'] }}</span>@endif
                    @if($vendor['email'])<a href="mailto:{{ $vendor['email'] }}?subject=License%20{{ $installationId }}" class="small align-self-center">{{ $vendor['email'] }}</a>@endif
                    @if($vendor['website'])<a href="{{ $vendor['website'] }}" target="_blank" rel="noopener" class="small align-self-center">{{ preg_replace('#^https?://#', '', $vendor['website']) }}</a>@endif
                </div>
                <p class="small mb-2">Have a license key? Paste it here:</p>
                @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('license.activate') }}">
                        @csrf
                        <textarea name="key" rows="3" class="form-control font-monospace small mb-2 @error('key') is-invalid @enderror" placeholder="TDS1.xxxxxxxx…" required>{{ old('key') }}</textarea>
                        @error('key')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-key"></i> Activate key</button>
                    </form>
                @else
                    <p class="small text-muted mb-0">Ask your admin to enter the license key.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
@if($needsAction && $activateUrl)
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    let tries = 0;
    async function check(manual) {
        try {
            const r = await fetch(@json(route('license.check')), {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
            const d = await r.json();
            if (d.activated) { location.reload(); return; }
            if (manual) { document.getElementById('waitMsg').textContent = 'No payment found yet. Finish the payment in the browser, then try again.'; }
        } catch (e) {
            if (manual) { document.getElementById('waitMsg').textContent = 'Could not reach the internet. Check your connection.'; }
        }
    }
    document.getElementById('checkNow')?.addEventListener('click', () => check(true));
    // poll for 30 minutes while the school pays in the browser
    const t = setInterval(() => { if (++tries > 180) { clearInterval(t); } else { check(false); } }, 10000);
})();
</script>
@endif
@endpush
