@extends('layouts.super')
@section('title', 'Dashboard')
@section('content')
@if(! \App\Models\PlatformSetting::get('razorpay_key_id') || ! \App\Models\PlatformSetting::get('razorpay_webhook_secret'))
    <div class="alert alert-warning">Payments are off: add your Razorpay keys and webhook secret in <a href="{{ route('super.settings') }}">Settings</a>.</div>
@endif
<div class="row g-3 mb-3">
    @foreach([
        ['Schools registered', $stats['schools'], '+'.$stats['new_month'].' this month'],
        ['Locked (₹1 not paid)', $stats['locked'], 'signed up, not started'],
        ['Active', $stats['active'], $stats['on_trial'].' on ₹1 trial · '.$stats['autopay'].' paying'],
        ['Plan ended', $stats['expired'], 'read-only'],
        ['Monthly revenue (Autopay)', '₹'.number_format($stats['mrr']), 'from active subscriptions'],
    ] as [$l, $v, $s])
        <div class="col-6 col-xl"><div class="card stat p-3 h-100"><div class="text-muted small">{{ $l }}</div><div class="v">{{ $v }}</div><div class="small text-muted">{{ $s }}</div></div></div>
    @endforeach
</div>
<div class="card">
    <div class="card-header bg-white d-flex">Newest schools <a href="{{ route('super.schools') }}" class="ms-auto small">All schools</a></div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>School</th><th>City</th><th>Phone</th><th>Joined</th><th>Users</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($recent as $s)
            <tr><td><a href="{{ route('super.schools.show', $s) }}">{{ $s->name }}</a></td><td>{{ $s->city }}</td><td>{{ $s->phone }}</td><td>{{ $s->created_at->format('d M Y') }}</td><td>{{ $s->users_count }}</td><td>@include('super.status', ['school' => $s])</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No schools yet. Share your website link so schools can register.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
