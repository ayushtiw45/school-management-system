@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
@if(! \App\Models\Setting::get('razorpay_key_id') || ! \App\Models\Setting::get('razorpay_webhook_secret'))
    <div class="alert alert-warning">Online sales are off: add your Razorpay keys and webhook secret in <a href="{{ route('admin.settings') }}">Settings</a>.</div>
@endif
<div class="row g-3 mb-3">
    @foreach([
        ['Schools signed up', $stats['schools'], '+'.$stats['schools_month'].' this month'],
        ['Downloads', $stats['downloads'], $stats['downloaded_schools'].' schools downloaded'],
        ['Active licenses', $stats['licensed'], $stats['expiring'].' expiring in 30 days'],
        ['Autopay active', $stats['subs_active'], $stats['subs_trial'].' on ₹1 trial · ₹'.number_format($stats['mrr']).'/month'],
        ['One-time revenue', '₹'.number_format($stats['revenue']), '₹'.number_format($stats['revenue_month']).' this month'],
    ] as [$l, $v, $s])
        <div class="col-6 col-xl"><div class="card stat p-3"><div class="text-muted small">{{ $l }}</div><div class="v">{{ $v }}</div><div class="small text-muted">{{ $s }}</div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card">
        <div class="card-header bg-white d-flex">New schools <a href="{{ route('admin.schools') }}" class="ms-auto small">All schools</a></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>School</th><th>City</th><th>Phone</th><th>Joined</th><th>DL</th></tr></thead>
            <tbody>@forelse($recentSchools as $s)<tr><td><a href="{{ route('admin.schools.show', $s) }}">{{ $s->school_name }}</a></td><td>{{ $s->city }}</td><td>{{ $s->phone }}</td><td>{{ $s->created_at->format('d M') }}</td><td>{{ $s->downloads_count }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No schools yet.</td></tr>@endforelse</tbody>
        </table></div>
    </div></div>
    <div class="col-lg-6"><div class="card">
        <div class="card-header bg-white d-flex">Recent payments <a href="{{ route('admin.payments') }}" class="ms-auto small">All payments</a></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>School</th><th>Plan</th><th class="text-end">Amount</th></tr></thead>
            <tbody>@forelse($recentPayments as $p)<tr><td>{{ $p->paid_at->format('d M') }}</td><td>{{ $p->user->school_name }}</td><td>{{ \App\Services\LicenseIssuer::LABELS[$p->plan] ?? $p->plan }}</td><td class="text-end">₹{{ number_format($p->rupees()) }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No payments yet.</td></tr>@endforelse</tbody>
        </table></div>
    </div></div>
</div>
@endsection
