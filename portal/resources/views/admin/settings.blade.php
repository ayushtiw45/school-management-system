@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="row g-3" style="max-width:1000px">
    @csrf @method('PUT')
    <div class="col-lg-6"><div class="card p-3 h-100">
        <h2 class="h6 fw-bold">Company & contact (shown on the website)</h2>
        <label class="form-label small fw-semibold mt-2">Company name</label><input name="company_name" value="{{ old('company_name', $settings['company_name']) }}" class="form-control" required>
        <label class="form-label small fw-semibold mt-2">Phone (as shown)</label><input name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}" class="form-control" placeholder="+91 98765 43210">
        <label class="form-label small fw-semibold mt-2">WhatsApp number (digits with country code)</label><input name="contact_whatsapp" value="{{ old('contact_whatsapp', $settings['contact_whatsapp']) }}" class="form-control" placeholder="919876543210">
        <label class="form-label small fw-semibold mt-2">Email</label><input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" class="form-control">
    </div></div>
    <div class="col-12"><div class="card p-3">
        <h2 class="h6 fw-bold">Trial & monthly Autopay</h2>
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small fw-semibold">Trial price (₹)</label><input type="number" min="1" step="1" name="trial_price" value="{{ old('trial_price', $settings['trial_price']) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Trial days (0 = no trial)</label><input type="number" min="0" max="60" name="trial_days" value="{{ old('trial_days', $settings['trial_days']) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Monthly price (₹)</label><input type="number" min="1" step="1" name="monthly_price" value="{{ old('monthly_price', $settings['monthly_price']) }}" class="form-control" required></div>
        </div>
        <p class="small text-muted mb-0 mt-2">Changing the monthly price applies to new subscriptions only; existing Autopay keeps its price.</p>
    </div></div>
    <div class="col-lg-6"><div class="card p-3 h-100">
        <h2 class="h6 fw-bold">One-time prices (optional, ₹, leave empty to hide)</h2>
        <div class="row g-2 mt-1">
            <div class="col-4"><label class="form-label small fw-semibold">1 Year</label><input type="number" min="1" step="1" name="price_1y" value="{{ old('price_1y', $settings['price_1y']) }}" class="form-control"></div>
            <div class="col-4"><label class="form-label small fw-semibold">2 Years</label><input type="number" min="1" step="1" name="price_2y" value="{{ old('price_2y', $settings['price_2y']) }}" class="form-control"></div>
            <div class="col-4"><label class="form-label small fw-semibold">Lifetime</label><input type="number" min="1" step="1" name="price_life" value="{{ old('price_life', $settings['price_life']) }}" class="form-control"></div>
        </div>
        <label class="form-label small fw-semibold mt-2">Installer download link</label><input name="download_url" value="{{ old('download_url', $settings['download_url']) }}" class="form-control" required>
    </div></div>
    <div class="col-12"><div class="card p-3">
        <h2 class="h6 fw-bold">Razorpay</h2>
        <p class="small text-muted mb-2">Razorpay Dashboard → Account & Settings → API Keys. Use <b>Test</b> keys first, then <b>Live</b> keys. For the webhook, add URL <code>{{ route('razorpay.webhook') }}</code> with events <code>subscription.authenticated</code>, <code>subscription.charged</code>, <code>subscription.pending</code>, <code>subscription.halted</code>, <code>subscription.cancelled</code>, <code>subscription.completed</code>, <code>payment.captured</code> and <code>order.paid</code>, and paste its secret here. Subscriptions (UPI Autopay / card recurring) must be enabled on your Razorpay account.</p>
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small fw-semibold">Key ID</label><input name="razorpay_key_id" value="{{ old('razorpay_key_id', $settings['razorpay_key_id']) }}" class="form-control font-monospace" placeholder="rzp_live_…"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Key Secret {!! $hasSecret['razorpay_key_secret'] ? '<span class="text-success">(saved)</span>' : '' !!}</label><input type="password" name="razorpay_key_secret" class="form-control" placeholder="{{ $hasSecret['razorpay_key_secret'] ? 'leave empty to keep' : '' }}" autocomplete="new-password"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Webhook Secret {!! $hasSecret['razorpay_webhook_secret'] ? '<span class="text-success">(saved)</span>' : '' !!}</label><input type="password" name="razorpay_webhook_secret" class="form-control" placeholder="{{ $hasSecret['razorpay_webhook_secret'] ? 'leave empty to keep' : '' }}" autocomplete="new-password"></div>
        </div>
    </div></div>
    <div class="col-12"><button class="btn btn-navy px-4">Save settings</button></div>
</form>
@endsection
