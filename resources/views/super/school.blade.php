@extends('layouts.super')
@section('title', $school->name)
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card p-3 mb-3">
            <h1 class="h5 fw-bold">{{ $school->name }}</h1>
            <div class="mb-2">@include('super.status', ['school' => $school])</div>
            <dl class="row small mb-0">
                <dt class="col-5 fw-normal text-muted">Phone</dt><dd class="col-7">{{ $school->phone }}</dd>
                <dt class="col-5 fw-normal text-muted">Email</dt><dd class="col-7">{{ $school->email }}</dd>
                <dt class="col-5 fw-normal text-muted">City</dt><dd class="col-7">{{ $school->city }}{{ $school->state ? ', '.$school->state : '' }}</dd>
                <dt class="col-5 fw-normal text-muted">Joined</dt><dd class="col-7">{{ $school->created_at->format('d M Y') }}</dd>
                <dt class="col-5 fw-normal text-muted">Paid until</dt><dd class="col-7">{{ $school->paid_until?->format('d M Y') ?? '—' }}</dd>
                <dt class="col-5 fw-normal text-muted">₹1 trial used</dt><dd class="col-7">{{ $school->trial_used ? 'Yes' : 'No' }}</dd>
                <dt class="col-5 fw-normal text-muted">Active students</dt><dd class="col-7">{{ $students }}</dd>
                <dt class="col-5 fw-normal text-muted">Online form</dt><dd class="col-7 text-break"><a href="{{ route('apply', $school) }}" target="_blank">{{ route('apply', $school) }}</a></dd>
            </dl>
        </div>
        <div class="card p-3">
            <h2 class="h6 fw-bold">Give access manually</h2>
            <p class="small text-muted">For a school that paid by UPI / cash, or as a goodwill extension.</p>
            <form method="POST" action="{{ route('super.schools.extend', $school) }}" class="d-flex gap-2">
                @csrf
                <select name="days" class="form-select form-select-sm"><option value="7">7 days</option><option value="30" selected>1 month</option><option value="90">3 months</option><option value="365">1 year</option></select>
                <button class="btn btn-sm btn-navy text-nowrap">Extend</button>
            </form>
            <form method="POST" action="{{ route('super.schools.block', $school) }}" class="mt-3">
                @csrf<button class="btn btn-sm {{ $school->is_blocked ? 'btn-outline-success' : 'btn-outline-danger' }}">{{ $school->is_blocked ? 'Unblock school' : 'Block school' }}</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header bg-white">Logins</div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th></tr></thead>
                <tbody>@foreach($school->users as $u)<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->roleLabel() }}</td><td>{{ $u->is_active ? 'Yes' : 'No' }}</td></tr>@endforeach</tbody></table></div>
        </div>
        <div class="card"><div class="card-header bg-white">Autopay</div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Started</th><th>Status</th><th>Monthly</th><th>Trial ends</th><th>Paid till</th><th>Payments</th><th>Razorpay</th></tr></thead>
                <tbody>@forelse($school->subscriptions as $sub)<tr><td>{{ $sub->created_at->format('d M Y') }}</td><td>{{ $sub->label() }}</td><td>₹{{ number_format($sub->monthly_amount / 100) }}</td><td>{{ $sub->trial_ends_at?->format('d M Y') }}</td><td>{{ $sub->current_end?->format('d M Y') }}</td><td>{{ $sub->paid_count }}</td><td class="small">{{ $sub->razorpay_subscription_id }}</td></tr>@empty<tr><td colspan="7" class="text-muted text-center py-3">No Autopay yet.</td></tr>@endforelse</tbody></table></div>
        </div>
    </div>
</div>
@endsection
