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
    <div class="col-lg-6"><div class="card p-3 h-100">
        <h2 class="h6 fw-bold">License prices (₹, leave empty to hide a plan)</h2>
        <div class="row g-2 mt-1">
            <div class="col-4"><label class="form-label small fw-semibold">1 Year</label><input type="number" min="1" step="1" name="price_1y" value="{{ old('price_1y', $settings['price_1y']) }}" class="form-control"></div>
            <div class="col-4"><label class="form-label small fw-semibold">2 Years</label><input type="number" min="1" step="1" name="price_2y" value="{{ old('price_2y', $settings['price_2y']) }}" class="form-control"></div>
            <div class="col-4"><label class="form-label small fw-semibold">Lifetime</label><input type="number" min="1" step="1" name="price_life" value="{{ old('price_life', $settings['price_life']) }}" class="form-control"></div>
        </div>
        <label class="form-label small fw-semibold mt-3">Free trial days (shown on the website; the software's trial is set when the installer is built)</label><input type="number" name="trial_days" value="{{ old('trial_days', $settings['trial_days']) }}" class="form-control" required>
        <label class="form-label small fw-semibold mt-2">Installer download link</label><input name="download_url" value="{{ old('download_url', $settings['download_url']) }}" class="form-control" required>
    </div></div>
    <div class="col-12"><div class="card p-3">
        <h2 class="h6 fw-bold">Razorpay</h2>
        <p class="small text-muted mb-2">Razorpay Dashboard → Account & Settings → API Keys. Use <b>Test</b> keys first, then <b>Live</b> keys. For the webhook, add URL <code>{{ route('razorpay.webhook') }}</code> with events <code>payment.captured</code> and <code>order.paid</code>, and paste its secret here.</p>
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small fw-semibold">Key ID</label><input name="razorpay_key_id" value="{{ old('razorpay_key_id', $settings['razorpay_key_id']) }}" class="form-control font-monospace" placeholder="rzp_live_…"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Key Secret {!! $hasSecret['razorpay_key_secret'] ? '<span class="text-success">(saved)</span>' : '' !!}</label><input type="password" name="razorpay_key_secret" class="form-control" placeholder="{{ $hasSecret['razorpay_key_secret'] ? 'leave empty to keep' : '' }}" autocomplete="new-password"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Webhook Secret {!! $hasSecret['razorpay_webhook_secret'] ? '<span class="text-success">(saved)</span>' : '' !!}</label><input type="password" name="razorpay_webhook_secret" class="form-control" placeholder="{{ $hasSecret['razorpay_webhook_secret'] ? 'leave empty to keep' : '' }}" autocomplete="new-password"></div>
        </div>
    </div></div>
    <div class="col-12"><button class="btn btn-navy px-4">Save settings</button></div>
</form>
@endsection
