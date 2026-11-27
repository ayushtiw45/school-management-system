@extends('layouts.site')
@section('title', __('Activate software'))
@section('content')
<div class="bg-light py-5">
<div class="container" style="max-width:680px">
    @include('partials-flash')

    @if(session('activated'))
        <div class="card p-4 p-md-5 text-center border-success border-2">
            <div class="display-5 text-success"><i class="bi bi-check-circle"></i></div>
            <h1 class="h3 fw-bold mt-2">{{ __('Payment successful!') }}</h1>
            <p class="mb-1">{{ __('Your software is now activated.') }}</p>
            <p class="text-muted">{{ __('Go back to the software: it picks up the license automatically within a few seconds. If not, open License and click "Check now".') }}</p>
            <a href="{{ route('account') }}" class="btn btn-outline-dark mt-2">{{ __('My Account') }}</a>
        </div>
    @elseif(! $installationId)
        <div class="card p-4">
            <h1 class="h4 fw-bold">{{ __('Activate software') }}</h1>
            <p class="text-muted">{{ __('Open the software, go to License and click "Start trial" — this page opens with your computer\'s Installation ID. Or enter it here:') }}</p>
            <form method="GET" action="{{ route('activate') }}" class="d-flex gap-2">
                <input name="installation_id" class="form-control font-monospace text-uppercase" placeholder="TDS-XXXX-XXXX-XXXX" required>
                <button class="btn btn-navy">{{ __('Continue') }}</button>
            </form>
        </div>
    @else
        <div class="card p-4 p-md-5">
            <div class="small text-muted">{{ __('Computer') }}: <span class="font-monospace fw-semibold text-dark">{{ $installationId }}</span> · {{ auth()->user()->school_name }}</div>

            @if($expiry === 'life')
                <h1 class="h4 fw-bold mt-3">{{ __('Lifetime license') }}</h1>
                <p class="text-muted mb-0">{{ __('This computer already has a lifetime license. Nothing to pay.') }}</p>
            @elseif($live)
                <h1 class="h4 fw-bold mt-3">{{ __('Autopay is active') }}</h1>
                <p class="mb-1">{{ __($live->label()) }}@if($expiry instanceof \Carbon\Carbon) · {{ __('valid till') }} <strong>{{ $expiry->format('d M Y') }}</strong>@endif</p>
                <p class="text-muted small">{{ __('₹:monthly is charged automatically every month. You can cancel any time in My Account.', ['monthly' => number_format($monthlyPrice)]) }}</p>
                <a href="{{ route('account') }}" class="btn btn-outline-dark">{{ __('Manage in My Account') }}</a>
            @elseif(! $ready)
                <h1 class="h4 fw-bold mt-3">{{ __('Activate software') }}</h1>
                <p class="text-muted mb-0">{{ __('Online payment is being set up. Please contact us on WhatsApp to buy a license.') }}</p>
            @else
                @if($trial)
                    <h1 class="h3 fw-bold mt-3">{{ __('Start your :days-day trial for ₹:price', ['days' => $trialDays, 'price' => $trialPrice + 0]) }}</h1>
                    <ul class="list-unstyled my-4 lh-lg">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Pay ₹:price now. All features unlock immediately for :days days.', ['price' => $trialPrice + 0, 'days' => $trialDays]) }}</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('After the trial, ₹:monthly per month is paid automatically (UPI Autopay / card).', ['monthly' => number_format($monthlyPrice)]) }}</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Cancel any time from My Account. No charge if you cancel during the trial.') }}</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Your data always stays on your laptop.') }}</li>
                    </ul>
                @else
                    <h1 class="h3 fw-bold mt-3">{{ __('Activate for ₹:monthly per month', ['monthly' => number_format($monthlyPrice)]) }}</h1>
                    <p class="text-muted">{{ __('The free trial has already been used for this account or computer. Pay monthly by Autopay; cancel any time.') }}</p>
                @endif
                <div class="alert alert-danger d-none" id="subErr"></div>
                <button class="btn btn-amber btn-lg w-100" id="subBtn">
                    <i class="bi bi-lock me-1"></i>{{ $trial ? __('Pay ₹:price & start trial', ['price' => $trialPrice + 0]) : __('Pay ₹:monthly & activate', ['monthly' => number_format($monthlyPrice)]) }}
                </button>
                <p class="small text-muted text-center mt-2 mb-0">{{ __('Secure payment by Razorpay. Your bank may show a one-time Autopay approval.') }}</p>
                <form method="POST" action="{{ route('subscribe.verify') }}" id="subVerify" class="d-none">
                    @csrf<input name="razorpay_payment_id"><input name="razorpay_subscription_id"><input name="razorpay_signature">
                </form>
            @endif
        </div>
    @endif
</div>
</div>
@endsection
@push('scripts')
@if($installationId && $ready && ! $live && $expiry !== 'life' && ! session('activated'))
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const btn = document.getElementById('subBtn'), err = document.getElementById('subErr');
    btn.addEventListener('click', async function () {
        err.classList.add('d-none'); btn.disabled = true;
        try {
            const res = await fetch(@json(route('subscribe')), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                body: JSON.stringify({installation_id: @json($installationId)})
            });
            const data = await res.json();
            if (!res.ok) { throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Error')); }
            const rzp = new Razorpay({
                key: data.key, subscription_id: data.subscription_id, name: data.name, description: data.description,
                prefill: data.prefill, theme: {color: '#14213d'},
                handler: function (r) {
                    const f = document.getElementById('subVerify');
                    f.razorpay_payment_id.value = r.razorpay_payment_id; f.razorpay_subscription_id.value = r.razorpay_subscription_id; f.razorpay_signature.value = r.razorpay_signature;
                    f.submit();
                },
                modal: {ondismiss: function () { btn.disabled = false; }}
            });
            rzp.on('payment.failed', function (r) { err.textContent = r.error.description; err.classList.remove('d-none'); btn.disabled = false; });
            rzp.open();
        } catch (ex) { err.textContent = ex.message; err.classList.remove('d-none'); btn.disabled = false; }
    });
})();
</script>
@endif
@endpush
