@extends('layouts.site')
@section('title', __('My Account'))
@section('content')
<div class="bg-light py-4 py-md-5">
<div class="container">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0">{{ $user->school_name }}</h1>
            <div class="text-muted small">{{ $user->name }} · {{ $user->phone }} · {{ $user->city }}</div>
        </div>
    </div>
    @include('partials-flash')
    @if(request('start_download'))
        <div class="alert alert-success"><i class="bi bi-download"></i> {{ __('Your download is starting… If it does not start,') }} <a href="{{ route('download') }}">{{ __('click here') }}</a>.</div>
    @endif

    <div class="row g-3">
        {{-- Download --}}
        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <h2 class="h5 fw-bold"><i class="bi bi-windows text-primary"></i> {{ __('Download the software') }}</h2>
                <p class="text-muted small">{{ __('Windows 10 / 11 (64-bit). :days-day trial for ₹:trial, then ₹:monthly/month.', ['days' => $trialDays, 'trial' => $trialPrice + 0, 'monthly' => number_format($monthlyPrice)]) }}</p>
                <a href="{{ route('download') }}" class="btn btn-amber btn-lg w-100" id="dlBtn"><i class="bi bi-download me-2"></i>{{ __('Download TDS-School-Setup.exe') }}</a>
                <ol class="small text-muted mt-3 mb-0 ps-3">
                    <li>{{ __('Open the downloaded file and click Next → Install.') }}</li>
                    <li>{{ __('If Windows shows "Windows protected your PC", click More info → Run anyway.') }}</li>
                    <li>{{ __('Enter your school name and create your admin login.') }}</li>
                </ol>
            </div>
        </div>

        {{-- Activate / buy --}}
        <div class="col-lg-7" id="buy">
            <div class="card p-4 h-100">
                <h2 class="h5 fw-bold"><i class="bi bi-key text-warning"></i> {{ __('Activate a computer') }}</h2>
                <p class="text-muted small">{{ __('In the software open License and click "Start trial" — or copy the Installation ID and enter it here.') }}</p>
                <form method="GET" action="{{ route('activate') }}" class="row g-2">
                    <div class="col-12"><label class="form-label">{{ __('Installation ID') }}</label>
                        <input name="installation_id" value="{{ old('installation_id', $prefillId) }}" class="form-control form-control-lg font-monospace text-uppercase" placeholder="TDS-XXXX-XXXX-XXXX" required></div>
                    <div class="col-12"><button class="btn btn-navy btn-lg w-100">{{ __('Continue') }}</button></div>
                </form>
                <p class="small text-muted mt-3 mb-0">{{ __(':days days for ₹:trial, then ₹:monthly per month by Autopay. Cancel any time.', ['days' => $trialDays, 'trial' => $trialPrice + 0, 'monthly' => number_format($monthlyPrice)]) }}</p>

                @if($plans && $razorpayReady)
                    <details class="mt-3"><summary class="small fw-semibold">{{ __('Or pay once for a longer period') }}</summary>
                    <form id="buyForm" class="row g-2 mt-1">
                        <div class="col-12"><input name="installation_id" value="{{ old('installation_id', $prefillId) }}" class="form-control font-monospace text-uppercase" placeholder="TDS-XXXX-XXXX-XXXX" required></div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            @foreach($plans as $plan => $price)
                                <input type="radio" class="btn-check" name="plan" value="{{ $plan }}" id="p{{ $plan }}" @checked($loop->first)>
                                <label class="btn btn-outline-dark btn-sm" for="p{{ $plan }}">{{ __(\App\Services\LicenseIssuer::PLANS[$plan]['label']) }} · ₹{{ number_format($price) }}</label>
                            @endforeach
                        </div>
                        <div class="col-12"><div class="alert alert-danger d-none mb-0" id="buyErr"></div></div>
                        <div class="col-12"><button class="btn btn-outline-dark w-100" id="payBtn">{{ __('Pay once') }}</button></div>
                    </form>
                    <form method="POST" action="{{ route('checkout.verify') }}" id="verifyForm" class="d-none">
                        @csrf
                        <input name="razorpay_order_id"><input name="razorpay_payment_id"><input name="razorpay_signature">
                    </form>
                    </details>
                @endif
            </div>
        </div>

        {{-- Autopay --}}
        @if($subscriptions->count())
            <div class="col-12">
                <div class="card p-4">
                    <h2 class="h5 fw-bold">{{ __('Autopay') }}</h2>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Installation ID') }}</th><th>{{ __('Status') }}</th><th>{{ __('Monthly') }}</th><th>{{ __('Paid till') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($subscriptions as $sub)
                            <tr>
                                <td class="font-monospace">{{ $sub->installation_id }}</td>
                                <td>{{ __($sub->label()) }}</td>
                                <td>₹{{ number_format($sub->monthly_amount / 100) }}</td>
                                <td>{{ ($sub->current_end ?? $sub->trial_ends_at)?->format('d M Y') }}</td>
                                <td class="text-end">@if($sub->isLive())
                                    <form method="POST" action="{{ route('subscriptions.cancel', $sub) }}" onsubmit="return confirm('{{ __('Cancel Autopay? The software keeps working until the paid period ends.') }}')">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Cancel Autopay') }}</button></form>
                                @endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            </div>
        @endif

        {{-- Licenses --}}
        <div class="col-12">
            <div class="card p-4">
                <h2 class="h5 fw-bold">{{ __('My license keys') }}</h2>
                @forelse($licenses as $l)
                    <div class="border rounded-3 p-3 mb-2 {{ session('new_license') && $loop->first ? 'border-warning border-2' : '' }}">
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <span class="font-monospace fw-semibold">{{ $l->installation_id }}</span>
                            <span class="badge {{ $l->isActive() ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $l->isActive() ? __('Active') : __('Expired') }}</span>
                            <span class="small text-muted">{{ __(\App\Services\LicenseIssuer::LABELS[$l->plan] ?? 'Custom') }} · {{ __('valid till') }} {{ $l->expires_on ? $l->expires_on->format('d M Y') : __('Lifetime') }}</span>
                        </div>
                        <div class="input-group">
                            <input class="form-control font-monospace small" value="{{ $l->license_key }}" id="key{{ $l->id }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('key{{ $l->id }}').value); this.textContent='{{ __('Copied') }}'">{{ __('Copy key') }}</button>
                        </div>
                        <div class="form-text">{{ __('In the software: License → paste this key → Activate.') }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('No license yet. You are on the free trial.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Payments --}}
        @if($payments->where('status', 'paid')->count())
            <div class="col-12">
                <div class="card p-4">
                    <h2 class="h5 fw-bold">{{ __('Payments') }}</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Installation ID') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Payment ID') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                            <tbody>
                            @foreach($payments->where('status', 'paid') as $p)
                                <tr><td>{{ $p->paid_at?->format('d M Y') }}</td><td class="font-monospace">{{ $p->installation_id }}</td><td>{{ __(\App\Services\LicenseIssuer::LABELS[$p->plan] ?? $p->plan) }}</td><td class="small">{{ $p->razorpay_payment_id }}</td><td class="text-end">₹{{ number_format($p->rupees(), 2) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Profile --}}
        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <h2 class="h5 fw-bold">{{ __('Profile') }}</h2>
                <form method="POST" action="{{ route('account.profile') }}" class="row g-2">
                    @csrf @method('PUT')
                    <div class="col-12"><label class="form-label">{{ __('School name') }}</label><input name="school_name" value="{{ old('school_name', $user->school_name) }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Your name') }}</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Mobile number') }}</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">{{ __('City') }}</label><input name="city" value="{{ old('city', $user->city) }}" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">{{ __('State') }}</label><input name="state" value="{{ old('state', $user->state) }}" class="form-control"></div>
                    <div class="col-12"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
                    <div class="col-12"><button class="btn btn-outline-dark">{{ __('Save') }}</button></div>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <h2 class="h5 fw-bold">{{ __('Change password') }}</h2>
                <form method="POST" action="{{ route('account.password') }}" class="row g-2">
                    @csrf @method('PUT')
                    <div class="col-12"><label class="form-label">{{ __('Current password') }}</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">{{ __('New password') }}</label><input type="password" name="password" minlength="8" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">{{ __('Confirm password') }}</label><input type="password" name="password_confirmation" class="form-control" required></div>
                    <div class="col-12"><button class="btn btn-outline-dark">{{ __('Change password') }}</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
@push('scripts')
@if($plans && $razorpayReady)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const form = document.getElementById('buyForm'), err = document.getElementById('buyErr'), btn = document.getElementById('payBtn');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        err.classList.add('d-none'); btn.disabled = true;
        try {
            const res = await fetch(@json(route('checkout.order')), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                body: JSON.stringify({installation_id: form.installation_id.value, plan: form.plan.value})
            });
            const data = await res.json();
            if (!res.ok) { throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Error')); }
            const rzp = new Razorpay({
                key: data.key, order_id: data.order_id, amount: data.amount, currency: 'INR',
                name: data.name, description: data.description, prefill: data.prefill, theme: {color: '#14213d'},
                handler: function (r) {
                    const v = document.getElementById('verifyForm');
                    v.razorpay_order_id.value = r.razorpay_order_id; v.razorpay_payment_id.value = r.razorpay_payment_id; v.razorpay_signature.value = r.razorpay_signature;
                    v.submit();
                },
                modal: {ondismiss: function () { btn.disabled = false; }}
            });
            rzp.on('payment.failed', function (r) { err.textContent = r.error.description; err.classList.remove('d-none'); btn.disabled = false; });
            rzp.open();
        } catch (ex) {
            err.textContent = ex.message; err.classList.remove('d-none'); btn.disabled = false;
        }
    });
})();
</script>
@endif
@if(request('start_download'))
<script>setTimeout(function () { window.location.href = @json(route('download')); }, 900);</script>
@endif
@endpush
