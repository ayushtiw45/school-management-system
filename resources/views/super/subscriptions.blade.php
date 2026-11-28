@extends('layouts.super')
@section('title', 'Autopay')
@section('content')
<div class="card">
    <div class="card-header bg-white d-flex flex-wrap gap-3 align-items-center">
        <form class="d-flex gap-2"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All (started)</option>@foreach(['authenticated' => 'On ₹1 trial', 'active' => 'Active', 'pending' => 'Payment retrying', 'halted' => 'Halted', 'cancelled' => 'Cancelled', 'created' => 'Not completed'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }} ({{ $counts[$k] ?? 0 }})</option>@endforeach</select></form>
        <span class="ms-auto">Monthly recurring revenue: <strong>₹{{ number_format($mrr) }}</strong></span>
    </div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Started</th><th>School</th><th>Status</th><th>Monthly</th><th>Trial ends</th><th>Paid till</th><th>Payments</th><th>Razorpay</th></tr></thead>
        <tbody>
        @forelse($subs as $s)
            <tr>
                <td>{{ $s->created_at->format('d M Y') }}</td>
                <td><a href="{{ route('super.schools.show', $s->school) }}">{{ $s->school->name }}</a><div class="small text-muted">{{ $s->school->phone }}</div></td>
                <td><span class="badge {{ ['active' => 'text-bg-success', 'authenticated' => 'text-bg-info', 'pending' => 'text-bg-warning', 'halted' => 'text-bg-danger', 'cancelled' => 'text-bg-secondary'][$s->status] ?? 'text-bg-light' }}">{{ $s->label() }}</span></td>
                <td>₹{{ number_format($s->monthly_amount / 100) }}@if($s->coupon)<div class="small text-success">{{ $s->coupon->code }}</div>@endif</td><td>{{ $s->trial_ends_at?->format('d M Y') }}</td><td>{{ $s->current_end?->format('d M Y') }}</td><td>{{ $s->paid_count }}</td>
                <td class="small">{{ $s->razorpay_subscription_id }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No subscriptions yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($subs->hasPages())<div class="card-footer bg-white">{{ $subs->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
